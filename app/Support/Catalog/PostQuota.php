<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Models\Client;
use App\Models\Installation;
use App\Models\SecurityCompany;
use App\Models\SupervisorPost;
use Illuminate\Validation\ValidationException;

/** Puestos por instalación y empleados asignados por puesto según el plan (más ampliaciones del superadmin). */
final class PostQuota
{
    public static function postsLimit(Installation $installation, ?SecurityCompany $company = null): int
    {
        if ($installation->max_posts !== null) {
            return (int) $installation->max_posts;
        }

        return CompanyEntitlements::for($company ?? self::companyOf($installation->client_id))->postsPerInstallation();
    }

    public static function activePosts(Installation $installation, ?int $exceptPostId = null): int
    {
        return SupervisorPost::query()
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('installation_id', $installation->id)
            ->where('is_active', true)
            ->when($exceptPostId !== null, fn ($q) => $q->whereKeyNot($exceptPostId))
            ->count();
    }

    public static function assertPostRoom(Installation $installation, string $action, ?int $exceptPostId = null): void
    {
        if (! $installation->is_active) {
            throw ValidationException::withMessages([
                'installation_id' => $action.' la instalación «'.$installation->name.'» está archivada. Restáurala primero.',
            ]);
        }

        $company = self::companyOf($installation->client_id);
        $client = $installation->client()->withoutGlobalScopes()->first();
        if ($client && $client->has_supervision && $company->hasSupervisionPackage() && ! $company->hasUnlimitedSupervision()) {
            $cap = CompanyEntitlements::for($company)->supervisionSeats();
            $usedCompany = InstallationQuota::for($company)->usedPosts();
            if ($exceptPostId !== null) {
                $except = SupervisorPost::query()->withoutGlobalScopes()->find($exceptPostId);
                if ($except && $except->is_active) {
                    $usedCompany = max(0, $usedCompany - 1);
                }
            }
            if ($usedCompany >= $cap) {
                throw ValidationException::withMessages([
                    'installation_id' => $action.' el plan de Supervisión cubre '.$cap.' puestos y ya usas '.$usedCompany.'. Archiva un puesto o amplía Supervisión en Facturación.',
                ]);
            }
        }

        $limit = self::postsLimit($installation, $company);
        if ($limit <= 0) {
            return;
        }

        $used = self::activePosts($installation, $exceptPostId);
        if ($used < $limit) {
            return;
        }

        throw ValidationException::withMessages([
            'installation_id' => $action.' «'.$installation->name.'» permite '.$limit.' puestos activos y ya tiene '.$used.'. Archiva un puesto o pide más con «Solicitar ampliación».',
        ]);
    }

    public static function employeesLimit(SupervisorPost $post, ?SecurityCompany $company = null): int
    {
        if ($post->max_employees !== null) {
            return (int) $post->max_employees;
        }

        return CompanyEntitlements::for($company ?? self::companyOf($post->client_id))->employeesPerPost();
    }

    public static function assignedEmployees(SupervisorPost $post, ?int $exceptEmployeeId = null): int
    {
        if (! $post->exists) {
            return 0;
        }

        return $post->employees()
            ->where('employees.is_active', true)
            ->when($exceptEmployeeId !== null, fn ($q) => $q->where('employees.id', '!=', $exceptEmployeeId))
            ->count();
    }

    public static function assertEmployeesFit(SupervisorPost $post, int $total, string $action = 'No puedes asignar más empleados:'): void
    {
        $limit = self::employeesLimit($post);
        if ($limit <= 0 || $total <= $limit) {
            return;
        }

        throw ValidationException::withMessages([
            'employee_ids' => $action.' el puesto «'.$post->name.'» admite '.$limit.' empleados asignados y quedaría con '.$total.'. Quita alguno o pide más con «Solicitar ampliación».',
        ]);
    }

    private static function companyOf(mixed $clientId): SecurityCompany
    {
        $companyId = Client::query()->whereKey((int) $clientId)->value('security_company_id');

        return SecurityCompany::query()->find($companyId) ?? new SecurityCompany;
    }
}
