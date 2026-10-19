<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccessGrantScope;
use App\Services\Auth\AllocateLoginUsername;
use App\Support\Company\OperateAsRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    use HasRoles {
        hasPermissionTo as private spatieHasPermissionTo;
    }

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        $name = is_string($permission) ? $permission : ($permission->name ?? null);
        if (is_string($name)) {
            $simulated = app(OperateAsRole::class)->allows($name);
            if ($simulated !== null) {
                return $simulated;
            }
        }

        return $this->spatieHasPermissionTo($permission, $guardName);
    }

    protected $fillable = [
        'name',
        'username',
        'job_title',
        'avatar_path',
        'email',
        'corporate_email',
        'password',
        'is_active',
        'last_login_at',
        'must_change_password',
        'area_key',
        'security_company_id',
        'employee_id',
        'admin_origin',
        'document_number',
        'primary_client_id',
        'supervisor_code',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'supervisor_code',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'must_change_password' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (filled($user->username)) {
                return;
            }
            $seed = filled($user->email)
                ? (string) $user->email
                : 'user'.Str::lower(Str::random(8)).'@local.test';
            $user->username = app(AllocateLoginUsername::class)->fromEmail($seed);
        });
    }

    public function securityCompany(): BelongsTo
    {
        return $this->belongsTo(SecurityCompany::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function primaryClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'primary_client_id');
    }

    public function moduleGrants(): HasMany
    {
        return $this->hasMany(UserModuleGrant::class);
    }

    public function clientAssignments(): HasMany
    {
        return $this->hasMany(ClientUserAssignment::class);
    }

    public function installationAssignments(): HasMany
    {
        return $this->hasMany(ClientUserInstallationAssignment::class);
    }

    public function assignedInstallations(): BelongsToMany
    {
        return $this->belongsToMany(Installation::class, 'client_user_installation_assignments')
            ->withPivot('installation_permission')
            ->withTimestamps();
    }

    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_user_assignments')
            ->withPivot(['is_primary', 'assigned_at'])
            ->withTimestamps();
    }

    /** @return list<int> */
    public function assignedClientIds(): array
    {
        if ($this->hasRole('super-admin')) {
            return Client::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if ($this->hasRole('company-admin') && $this->security_company_id) {
            return Client::query()
                ->where('security_company_id', $this->security_company_id)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if ($this->hasRole('colaborador')) {
            return $this->collaboratorClientIds();
        }

        return $this->clients()
            ->pluck('clients.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function canAccessClient(int $clientId): bool
    {
        return in_array($clientId, $this->assignedClientIds(), true);
    }

    /**
     * null = todas las instalaciones del cliente activo.
     *
     * @return list<int>|null
     */
    public function assignedInstallationIds(): ?array
    {
        if ($this->hasRole('colaborador')) {
            return $this->collaboratorInstallationIds();
        }

        if (! $this->hasRole('client-installation-admin')) {
            return null;
        }

        return $this->assignedInstallations()
            ->pluck('installations.id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function canAccessInstallation(int $installationId): bool
    {
        $ids = $this->assignedInstallationIds();

        return $ids === null || in_array($installationId, $ids, true);
    }

    public function installationPermissionOn(int $installationId): string
    {
        $row = $this->relationLoaded('installationAssignments')
            ? $this->installationAssignments->firstWhere('installation_id', $installationId)
            : $this->installationAssignments()->where('installation_id', $installationId)->first();

        return $row?->installation_permission ?? 'admin';
    }

    public function isSiteSupport(int $installationId): bool
    {
        return $this->hasRole('client-installation-admin')
            && $this->installationPermissionOn($installationId) === 'support';
    }

    /** @return list<int> */
    private function collaboratorClientIds(): array
    {
        $companyId = (int) ($this->security_company_id ?? 0);
        $grants = $this->relationLoaded('moduleGrants') ? $this->moduleGrants : $this->moduleGrants()->get();

        $wide = $grants->contains(function (UserModuleGrant $grant) use ($companyId): bool {
            return $grant->scope === AccessGrantScope::Company
                && (int) $grant->scope_id === $companyId
                && in_array($grant->module, ['clients', 'installations', 'observatory', 'supervision'], true);
        });

        if ($wide && $companyId > 0) {
            return Client::query()
                ->where('security_company_id', $companyId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $ids = $grants
            ->where('scope', AccessGrantScope::Client)
            ->pluck('scope_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $installationIds = $grants
            ->where('scope', AccessGrantScope::Installation)
            ->pluck('scope_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($installationIds !== []) {
            $fromSites = Installation::query()
                ->whereIn('id', $installationIds)
                ->pluck('client_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $ids = array_merge($ids, $fromSites);
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /** @return list<int> */
    private function collaboratorInstallationIds(): array
    {
        $companyId = (int) ($this->security_company_id ?? 0);
        $grants = $this->relationLoaded('moduleGrants') ? $this->moduleGrants : $this->moduleGrants()->get();

        $wide = $grants->contains(function (UserModuleGrant $grant) use ($companyId): bool {
            return $grant->scope === AccessGrantScope::Company
                && (int) $grant->scope_id === $companyId
                && in_array($grant->module, ['clients', 'installations', 'observatory', 'supervision'], true);
        });

        if ($wide) {
            return Installation::query()
                ->whereHas('client', fn ($q) => $q->where('security_company_id', $companyId))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $ids = $grants
            ->where('scope', AccessGrantScope::Installation)
            ->pluck('scope_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $clientIds = $grants
            ->where('scope', AccessGrantScope::Client)
            ->pluck('scope_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($clientIds !== []) {
            $fromClients = Installation::query()
                ->whereIn('client_id', $clientIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $ids = array_merge($ids, $fromClients);
        }

        return array_values(array_unique(array_filter($ids)));
    }

    public function isSupervisionManager(): bool
    {
        return $this->hasAnyRole(['super-admin', 'company-admin', 'client-admin', 'admin-accesos'])
            || $this->can('access.manage.supervision_codes');
    }
}
