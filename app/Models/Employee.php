<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BloodGroup;
use App\Enums\Sex;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class Employee extends Model
{
    protected $fillable = [
        'security_company_id',
        'job_title_id',
        'document_type',
        'document_number',
        'last_name_paternal',
        'last_name_maternal',
        'first_names',
        'sex',
        'birth_date',
        'collaborator_type_id',
        'email',
        'nationality',
        'blood_group',
        'birth_department',
        'birth_city',
        'emergency_phone',
        'emergency_contact',
        'has_disability',
        'document_issue_department',
        'document_issue_city',
        'document_issued_at',
        'same_cost_center',
        'is_active',
        'indexing_enabled',
        'ceased_at',
        'photo_path',
        'education',
        'marital_status',
        'children_count',
        'phone',
        'residence_city',
        'address',
        'engagement_type',
        'contributor_type',
        'labor_contract_type',
        'hired_on',
        'labor_contract_ends_on',
        'left_on',
        'eps_code',
        'eps_name',
        'afp_code',
        'afp_name',
        'compensation_fund',
        'arl_name',
        'arl_risk_level',
    ];

    protected function casts(): array
    {
        return [
            'sex' => Sex::class,
            'birth_date' => 'date',
            'blood_group' => BloodGroup::class,
            'has_disability' => 'boolean',
            'document_issued_at' => 'date',
            'same_cost_center' => 'boolean',
            'is_active' => 'boolean',
            'indexing_enabled' => 'boolean',
            'ceased_at' => 'date',
            'children_count' => 'integer',
            'hired_on' => 'date',
            'labor_contract_ends_on' => 'date',
            'left_on' => 'date',
        ];
    }

    public function securityCompany(): BelongsTo
    {
        return $this->belongsTo(SecurityCompany::class);
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(CompanyJobTitle::class, 'job_title_id');
    }

    public function collaboratorType(): BelongsTo
    {
        return $this->belongsTo(CompanyCollaboratorType::class, 'collaborator_type_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function supervisorPosts(): BelongsToMany
    {
        return $this->belongsToMany(SupervisorPost::class, 'supervisor_post_employee')
            ->withTimestamps();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function documentBatches(): HasMany
    {
        return $this->hasMany(EmployeeDocumentBatch::class);
    }

    public function scopeAssignedToClient(Builder $query, int $clientId): void
    {
        $query->whereHas('supervisorPosts', fn (Builder $posts) => $posts->where('client_id', $clientId));
    }

    public function scopeAssignedToInstallations(Builder $query, array $installationIds): void
    {
        $ids = array_values(array_filter(array_map('intval', $installationIds)));
        if ($ids === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereHas('supervisorPosts', fn (Builder $posts) => $posts->whereIn('installation_id', $ids));
    }

    public function getFullNameAttribute(): string
    {
        return $this->fullName();
    }

    public function fullName(): string
    {
        return trim(preg_replace('/\s+/u', ' ', "{$this->first_names} {$this->last_name_paternal} {$this->last_name_maternal}") ?? '');
    }

    public function age(): ?int
    {
        return $this->birth_date?->age;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/u', $this->fullName()) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $letters !== '' ? $letters : 'EM';
    }

    public function photoUrl(): ?string
    {
        if ($this->photo_path === null || $this->photo_path === '') {
            return null;
        }

        if (! Storage::disk('local')->exists($this->photo_path)) {
            return null;
        }

        return route('company.employees.photo', $this);
    }

    public function photoFileResponse(): ?BinaryFileResponse
    {
        if ($this->photo_path === null || $this->photo_path === '') {
            return null;
        }

        if (! Storage::disk('local')->exists($this->photo_path)) {
            return null;
        }

        $absolute = Storage::disk('local')->path($this->photo_path);
        $mime = Storage::disk('local')->mimeType($this->photo_path) ?: 'image/jpeg';

        return response()->file($absolute, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
