<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Enums\QuotaRequestKind;
use App\Models\Client;
use App\Models\Installation;
use App\Models\QuotaRequest;
use App\Models\SecurityCompany;
use App\Models\SupervisorPost;
use App\Models\User;
use App\Support\Catalog\CompanyEntitlements;
use App\Support\Catalog\PostQuota;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Ampliaciones fuera del plan: las autoriza el superadmin y se cobran por fuera del sistema. */
final class ManageQuotaService
{
    /**
     * @param  array{extra_employees: int, posts_per_installation: ?int, employees_per_post: ?int, quota_note: ?string}  $data
     */
    public function updateCompany(SecurityCompany $company, array $data): void
    {
        $company->update([
            'extra_employees' => max(0, (int) $data['extra_employees']),
            'posts_per_installation' => $this->nullablePositive($data['posts_per_installation'] ?? null),
            'employees_per_post' => $this->nullablePositive($data['employees_per_post'] ?? null),
            'quota_note' => $this->note($data['quota_note'] ?? null),
        ]);
    }

    public function updateInstallation(SecurityCompany $company, int $installationId, ?int $maxPosts, ?string $note): Installation
    {
        $installation = $this->installationOf($company, $installationId);
        $installation->update([
            'max_posts' => $this->nullablePositive($maxPosts),
            'max_posts_note' => $maxPosts === null ? null : $this->note($note),
        ]);

        return $installation;
    }

    public function updatePost(SecurityCompany $company, int $postId, ?int $maxEmployees, ?string $note): SupervisorPost
    {
        $post = $this->postOf($company, $postId);
        $post->update([
            'max_employees' => $this->nullablePositive($maxEmployees),
            'max_employees_note' => $maxEmployees === null ? null : $this->note($note),
        ]);

        return $post;
    }

    public function request(SecurityCompany $company, User $user, QuotaRequestKind $kind, int $quantity, ?int $installationId, ?int $postId, ?string $message): QuotaRequest
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'Indica cuántos necesitas.']);
        }

        $installation = $kind === QuotaRequestKind::Posts && $installationId ? $this->installationOf($company, $installationId) : null;
        $post = $kind === QuotaRequestKind::Assignments && $postId ? $this->postOf($company, $postId) : null;

        return QuotaRequest::query()->create([
            'security_company_id' => $company->id,
            'requested_by' => $user->id,
            'kind' => $kind,
            'installation_id' => $installation?->id,
            'supervisor_post_id' => $post?->id,
            'quantity' => $quantity,
            'message' => $this->note($message),
            'status' => QuotaRequest::PENDING,
        ]);
    }

    public function approve(QuotaRequest $request, User $admin, ?string $note): void
    {
        $this->assertPending($request);

        DB::transaction(function () use ($request, $admin, $note): void {
            $company = SecurityCompany::query()->findOrFail($request->security_company_id);
            $qty = (int) $request->quantity;
            $reason = 'Solicitud #'.$request->id.($note ? ' · '.$note : '');

            match ($request->kind) {
                QuotaRequestKind::Employees => $company->update([
                    'extra_employees' => (int) $company->extra_employees + $qty,
                    'quota_note' => $this->note($reason),
                ]),
                QuotaRequestKind::Posts => $request->installation_id
                    ? $this->updateInstallation(
                        $company,
                        (int) $request->installation_id,
                        PostQuota::postsLimit($this->installationOf($company, (int) $request->installation_id), $company) + $qty,
                        $reason,
                    )
                    : $company->update([
                        'posts_per_installation' => CompanyEntitlements::for($company)->postsPerInstallation() + $qty,
                        'quota_note' => $this->note($reason),
                    ]),
                QuotaRequestKind::Assignments => $request->supervisor_post_id
                    ? $this->updatePost(
                        $company,
                        (int) $request->supervisor_post_id,
                        PostQuota::employeesLimit($this->postOf($company, (int) $request->supervisor_post_id), $company) + $qty,
                        $reason,
                    )
                    : $company->update([
                        'employees_per_post' => CompanyEntitlements::for($company)->employeesPerPost() + $qty,
                        'quota_note' => $this->note($reason),
                    ]),
            };

            $request->update([
                'status' => QuotaRequest::APPROVED,
                'resolved_by' => $admin->id,
                'resolved_at' => now(),
                'resolution_note' => $this->note($note),
            ]);
        });
    }

    public function reject(QuotaRequest $request, User $admin, ?string $note): void
    {
        $this->assertPending($request);

        $request->update([
            'status' => QuotaRequest::REJECTED,
            'resolved_by' => $admin->id,
            'resolved_at' => now(),
            'resolution_note' => $this->note($note),
        ]);
    }

    private function assertPending(QuotaRequest $request): void
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages(['request' => 'Esta solicitud ya fue resuelta.']);
        }
    }

    private function installationOf(SecurityCompany $company, int $installationId): Installation
    {
        $installation = Installation::query()->withoutGlobalScopes()->whereNull('deleted_at')->find($installationId);
        $clientCompany = $installation ? Client::query()->whereKey($installation->client_id)->value('security_company_id') : null;
        if ($installation === null || (int) $clientCompany !== (int) $company->id) {
            throw ValidationException::withMessages(['installation_id' => 'La instalación no pertenece a esta empresa.']);
        }

        return $installation;
    }

    private function postOf(SecurityCompany $company, int $postId): SupervisorPost
    {
        $post = SupervisorPost::query()->withoutGlobalScopes()->whereNull('deleted_at')->find($postId);
        $clientCompany = $post ? Client::query()->whereKey($post->client_id)->value('security_company_id') : null;
        if ($post === null || (int) $clientCompany !== (int) $company->id) {
            throw ValidationException::withMessages(['supervisor_post_id' => 'El puesto no pertenece a esta empresa.']);
        }

        return $post;
    }

    private function nullablePositive(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
    }

    private function note(?string $value): ?string
    {
        $text = trim((string) $value);

        return $text !== '' ? mb_substr($text, 0, 255) : null;
    }
}
