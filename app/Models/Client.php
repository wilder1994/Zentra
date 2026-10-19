<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClientLifecycle;
use App\Enums\ClientPlanTier;
use App\Enums\PartyType;
use App\Support\Client\ClientPanelModules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'security_company_id',
        'supervisor_zone_id',
        'name',
        'party_type',
        'legal_name',
        'document_type',
        'tax_id',
        'email',
        'phone',
        'representative_name',
        'representative_email',
        'structure_type_id',
        'slug',
        'login_suffix',
        'address',
        'city',
        'department',
        'latitude',
        'longitude',
        'plan_tier',
        'max_structures',
        'logo_path',
        'access_url',
        'is_active',
        'has_access',
        'has_supervision',
        'has_observatory',
        'access_line_archived_at',
        'supervision_line_archived_at',
        'observatory_line_archived_at',
        'show_personnel_folders',
        'panel_modules',
        'service_started_at',
        'service_hours',
        'revista_target_per_day',
        'lifecycle',
        'released_at',
        'archived_at',
        'archived_with_parent',
        'tenant_data_purged_at',
    ];

    protected function casts(): array
    {
        return [
            'party_type' => PartyType::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'plan_tier' => ClientPlanTier::class,
            'max_structures' => 'integer',
            'is_active' => 'boolean',
            'has_access' => 'boolean',
            'has_supervision' => 'boolean',
            'has_observatory' => 'boolean',
            'access_line_archived_at' => 'datetime',
            'supervision_line_archived_at' => 'datetime',
            'observatory_line_archived_at' => 'datetime',
            'show_personnel_folders' => 'boolean',
            'panel_modules' => 'array',
            'service_started_at' => 'date',
            'service_hours' => 'integer',
            'revista_target_per_day' => 'integer',
            'lifecycle' => ClientLifecycle::class,
            'released_at' => 'datetime',
            'archived_at' => 'datetime',
            'archived_with_parent' => 'boolean',
            'tenant_data_purged_at' => 'datetime',
        ];
    }

    /** Al reactivar la empresa vuelven los clientes que archivó ella; los archivados a mano siguen archivados. */
    public static function restoreFromCompanyArchive(int $companyId): void
    {
        self::query()
            ->where('security_company_id', $companyId)
            ->where('lifecycle', ClientLifecycle::ArchivedCompany)
            ->where('archived_with_parent', true)
            ->update([
                'archived_at' => null,
                'archived_with_parent' => false,
                'is_active' => true,
            ]);

        self::query()
            ->where('security_company_id', $companyId)
            ->where('lifecycle', ClientLifecycle::ArchivedCompany)
            ->update(['lifecycle' => ClientLifecycle::Active]);
    }

    public function securityCompany(): BelongsTo
    {
        return $this->belongsTo(SecurityCompany::class);
    }

    public function structureType(): BelongsTo
    {
        return $this->belongsTo(StructureType::class);
    }

    /** Zona principal de Supervisión (una sola). */
    public function supervisorZone(): BelongsTo
    {
        return $this->belongsTo(SupervisorZone::class, 'supervisor_zone_id');
    }

    /** Zonas de Supervisión con las que se comparte (además de la principal). */
    public function sharedSupervisorZones(): BelongsToMany
    {
        return $this->belongsToMany(SupervisorZone::class, 'supervisor_zone_shared_clients')->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ClientUserAssignment::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'client_user_assignments')
            ->withPivot(['is_primary', 'assigned_at'])
            ->withTimestamps();
    }

    public function loginDomain(): string
    {
        return '@'.$this->login_suffix;
    }

    public function isCatalogOnly(): bool
    {
        return ! $this->has_access && ! $this->has_supervision && ! $this->has_observatory;
    }

    public function supervisorShiftReviews(): HasMany
    {
        return $this->hasMany(SupervisorShiftReview::class);
    }

    public function supervisorFieldLogs(): HasMany
    {
        return $this->hasMany(SupervisorFieldLog::class);
    }

    public function installations(): HasMany
    {
        return $this->hasMany(Installation::class);
    }

    public function supervisorPosts(): HasMany
    {
        return $this->hasMany(SupervisorPost::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function memberTypes(): HasMany
    {
        return $this->hasMany(MemberType::class);
    }

    public function observatoryReportTypes(): HasMany
    {
        return $this->hasMany(ObservatoryReportType::class)->orderBy('sort_order')->orderBy('name');
    }

    public function hasDoors(): bool
    {
        return Location::query()
            ->withoutGlobalScopes()
            ->where('client_id', $this->id)
            ->where('is_active', true)
            ->where('type', 'access_point')
            ->exists();
    }

    /** @return array<string, bool> */
    public function panelModules(): array
    {
        return ClientPanelModules::normalized($this->panel_modules);
    }

    public function panelModuleEnabled(string $key): bool
    {
        $enabled = $this->panelModules()[$key] ?? false;

        if ($key === ClientPanelModules::DOORS) {
            return $enabled && $this->hasDoors();
        }

        if ($key === ClientPanelModules::OBSERVATORY) {
            return $enabled && (bool) $this->has_observatory;
        }

        return $enabled;
    }
}
