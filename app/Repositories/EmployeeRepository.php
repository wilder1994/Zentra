<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Employee;
use BackedEnum;
use App\Models\EmployeeDocument;
use App\Support\Auth\ConstrainEmployeesByGrants;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

final class EmployeeRepository
{
    public function paginateForCompany(
        int $companyId,
        int $perPage = 15,
        ?string $search = null,
        string $status = 'active',
    ): LengthAwarePaginator {
        $query = Employee::query()
            ->with(['jobTitle', 'collaboratorType', 'user'])
            ->where('security_company_id', $companyId);
        $this->constrainByGrants($query);

        if ($status === 'archived') {
            $query->where('is_active', false);
        } elseif ($status !== 'all') {
            $query->where('is_active', true);
        }

        if ($search !== null && $search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term): void {
                $q->where('first_names', 'like', $term)
                    ->orWhere('last_name_paternal', 'like', $term)
                    ->orWhere('last_name_maternal', 'like', $term)
                    ->orWhere('document_number', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            });
        }

        return $query
            ->orderBy('last_name_paternal')
            ->orderBy('first_names')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return list<array{id: int, document_number: string, name: string, email: ?string, job_title: ?string}>
     */
    public function searchWithoutUser(int $companyId, string $query, int $limit = 15): array
    {
        $term = trim($query);
        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%'.$term.'%';

        return Employee::query()
            ->with('jobTitle')
            ->where('security_company_id', $companyId)
            ->where('is_active', true)
            ->whereNull('ceased_at')
            ->whereDoesntHave('user')
            ->where(function ($q) use ($like, $term): void {
                $q->where('document_number', 'like', $term.'%')
                    ->orWhere('document_number', 'like', $like)
                    ->orWhere('first_names', 'like', $like)
                    ->orWhere('last_name_paternal', 'like', $like)
                    ->orWhere('last_name_maternal', 'like', $like);
            })
            ->orderBy('document_number')
            ->limit($limit)
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'document_number' => $employee->document_number,
                'name' => $employee->fullName(),
                'email' => $employee->email,
                'job_title' => $employee->jobTitle?->name,
            ])
            ->values()
            ->all();
    }

    public function paginateForPersonnelDocuments(
        int $companyId,
        ?string $search = null,
        int $perPage = 24,
        ?int $assignedToClientId = null,
        ?array $assignedInstallationIds = null,
        ?string $tenure = null,
        ?string $indexing = null,
        ?string $folder = null,
    ): LengthAwarePaginator {
        $query = Employee::query()
            ->select('employees.*')
            ->where('security_company_id', $companyId)
            ->where('is_active', true)
            ->withCount([
                'documents as documents_count' => fn (Builder $q) => $q
                    ->where('not_applicable', false)
                    ->where('disk_path', '!=', ''),
            ])
            ->addSelect([
                'folders_count' => EmployeeDocument::query()
                    ->selectRaw('count(distinct folder)')
                    ->whereColumn('employee_id', 'employees.id')
                    ->where('not_applicable', false)
                    ->where('disk_path', '!=', ''),
            ]);

        if ($assignedToClientId !== null) {
            $query->assignedToClient($assignedToClientId);
        }
        $installationIds = $assignedInstallationIds ?? [];
        if ($installationIds !== []) {
            $query->assignedToInstallations($installationIds);
        }
        $this->constrainByGrants($query, 'documents');
        $this->applyDocumentSearch($query, $search);
        $this->applyTenure($query, $tenure);
        $this->applyIndexingState($query, $indexing);
        $this->applyDocumentFolder($query, $folder);

        return $query
            ->orderBy('last_name_paternal')
            ->orderBy('first_names')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array{folders: list<array{key: string, workers: int, bytes: int, files: int}>, workers: int, bytes: int, files: int}
     */
    public function personnelFolderStats(int $companyId): array
    {
        $employees = Employee::query()
            ->where('security_company_id', $companyId)
            ->where('is_active', true);
        $this->constrainByGrants($employees, 'documents');

        $files = EmployeeDocument::query()
            ->whereIn('employee_id', $employees->select('employees.id'))
            ->where('not_applicable', false)
            ->where('disk_path', '!=', '');

        $byFolder = (clone $files)
            ->groupBy('folder')
            ->selectRaw('folder, count(distinct employee_id) as workers, coalesce(sum(size_bytes), 0) as bytes, count(*) as files')
            ->get();

        $total = (clone $files)
            ->selectRaw('count(distinct employee_id) as workers, coalesce(sum(size_bytes), 0) as bytes, count(*) as files')
            ->first();

        $folders = [];
        foreach ($byFolder as $row) {
            $key = $row->folder instanceof BackedEnum ? $row->folder->value : (string) $row->folder;
            $folders[] = [
                'key' => $key,
                'workers' => (int) $row->workers,
                'bytes' => (int) $row->bytes,
                'files' => (int) $row->files,
            ];
        }

        return [
            'folders' => $folders,
            'workers' => (int) ($total->workers ?? 0),
            'bytes' => (int) ($total->bytes ?? 0),
            'files' => (int) ($total->files ?? 0),
        ];
    }

    private function applyDocumentFolder(Builder $query, ?string $folder): void
    {
        if ($folder === null || $folder === '') {
            return;
        }

        $query->whereHas('documents', function (Builder $docs) use ($folder): void {
            $docs->where('not_applicable', false)->where('disk_path', '!=', '');
            if ($folder !== 'all') {
                $docs->where('folder', $folder);
            }
        });
    }

    private function applyDocumentSearch(Builder $query, ?string $search): void
    {
        $words = preg_split('/\s+/u', trim((string) $search)) ?: [];
        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            $term = '%'.addcslashes($word, '\\%_').'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('first_names', 'like', $term)
                    ->orWhere('last_name_paternal', 'like', $term)
                    ->orWhere('last_name_maternal', 'like', $term)
                    ->orWhere('document_number', 'like', $term);
            });
        }
    }

    private function applyTenure(Builder $query, ?string $tenure): void
    {
        if ($tenure === null || $tenure === '') {
            return;
        }

        if ($tenure === 'none') {
            $query->whereNull('hired_on');

            return;
        }

        $today = now()->startOfDay();
        match ($tenure) {
            'lt1' => $query->whereDate('hired_on', '>', $today->copy()->subYear()),
            'y1_3' => $query
                ->whereDate('hired_on', '<=', $today->copy()->subYear())
                ->whereDate('hired_on', '>', $today->copy()->subYears(3)),
            'y3_5' => $query
                ->whereDate('hired_on', '<=', $today->copy()->subYears(3))
                ->whereDate('hired_on', '>', $today->copy()->subYears(5)),
            'gt5' => $query->whereDate('hired_on', '<=', $today->copy()->subYears(5)),
            default => null,
        };
    }

    private function applyIndexingState(Builder $query, ?string $indexing): void
    {
        if ($indexing === 'on') {
            $query->where('indexing_enabled', true);
        } elseif ($indexing === 'off') {
            $query->where('indexing_enabled', false);
        }
    }

    public function paginateForClientPosts(
        int $companyId,
        int $clientId,
        ?array $installationIds,
        int $perPage = 25,
        ?string $search = null,
    ): LengthAwarePaginator {
        $query = Employee::query()
            ->with(['jobTitle'])
            ->where('security_company_id', $companyId)
            ->where('is_active', true)
            ->assignedToClient($clientId);
        if ($installationIds !== null && $installationIds !== []) {
            $query->assignedToInstallations($installationIds);
        }
        $this->constrainByGrants($query, 'employees');
        if ($search !== null && $search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('first_names', 'like', $term)
                    ->orWhere('last_name_paternal', 'like', $term)
                    ->orWhere('last_name_maternal', 'like', $term)
                    ->orWhere('document_number', 'like', $term);
            });
        }

        return $query
            ->orderBy('last_name_paternal')
            ->orderBy('first_names')
            ->paginate($perPage)
            ->withQueryString();
    }

    private function constrainByGrants(Builder $query, string $module = 'employees'): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        app(ConstrainEmployeesByGrants::class)->apply($query, $user, $module);
    }
}
