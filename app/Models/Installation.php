<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ColombianAreaKind;
use App\Enums\InstallationKind;
use App\Models\Concerns\BelongsToClient;
use App\Support\Company\InstallationSiteAdmins;
use App\Support\Geo\ColombianArea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class Installation extends Model
{
    use BelongsToClient, SoftDeletes;

    protected $fillable = [
        'client_id',
        'name',
        'code',
        'kind',
        'dane_code',
        'commune',
        'area_kind',
        'rector_user_id',
        'is_client_site',
        'is_active',
        'max_posts',
        'max_posts_note',
        'archived_at',
        'archived_with_parent',
        'address',
        'city',
        'department',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'is_client_site' => 'boolean',
            'is_active' => 'boolean',
            'max_posts' => 'integer',
            'archived_at' => 'datetime',
            'archived_with_parent' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (Installation $installation): void {
            if (filled($installation->code) || ! $installation->client_id) {
                return;
            }

            $client = $installation->relationLoaded('client')
                ? $installation->client
                : Client::query()->find($installation->client_id);

            if ($client instanceof Client) {
                $installation->code = self::nextAvailableCode($client);
            }
        });
    }

    public static function nextAvailableCode(Client $client, ?int $ignoreId = null): string
    {
        $raw = $client->slug ?: 'sed';
        $prefix = Str::upper(Str::substr((string) preg_replace('/[^A-Za-z0-9]+/', '', $raw), 0, 8)) ?: 'SED';
        $n = 1;

        do {
            $candidate = $prefix.'-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            $taken = self::query()
                ->withoutGlobalScopes()
                ->where('client_id', $client->id)
                ->where('code', $candidate)
                ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
                ->exists();
            $n++;
        } while ($taken);

        return $candidate;
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function rector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rector_user_id');
    }

    public function kindLabel(): string
    {
        return InstallationKind::tryFrom((string) $this->kind)?->label() ?? '—';
    }

    public function requiresOfficialCode(): bool
    {
        return InstallationKind::tryFrom((string) $this->kind)?->requiresOfficialCode() ?? false;
    }

    public function addressLine(): string
    {
        $city = trim(implode(', ', array_filter([
            trim((string) $this->city),
            trim((string) $this->department),
        ])));
        $parts = array_filter([
            trim((string) $this->address),
            $city,
        ]);

        return implode(' · ', $parts);
    }

    /** @return Collection<int, string> */
    public function staffRoleLines(string $permission): Collection
    {
        $people = $this->relationLoaded('assignedAdmins')
            ? $this->assignedAdmins
            : $this->assignedAdmins()->get();

        return $people
            ->filter(static function (User $user) use ($permission): bool {
                $value = $user->pivot->installation_permission ?? 'admin';

                return $permission === 'support' ? $value === 'support' : $value !== 'support';
            })
            ->map(static fn (User $user): string => InstallationSiteAdmins::roleName($user))
            ->filter()
            ->values();
    }

    /** @return Collection<int, string> */
    public function staffLines(): Collection
    {
        return $this->staffRoleLines('admin')
            ->merge($this->staffRoleLines('support'))
            ->values();
    }

    public function siteAdminLabel(): string
    {
        $lines = $this->staffLines();

        if ($lines->isEmpty()) {
            return '—';
        }

        if ($lines->count() <= 2) {
            return $lines->implode(', ');
        }

        return $lines->first().' +'.($lines->count() - 1);
    }

    public function areaKind(): ColombianAreaKind
    {
        return ColombianAreaKind::tryFrom((string) $this->area_kind)
            ?? ColombianArea::classify($this->commune, $this->city);
    }

    public function areaKindLabel(): string
    {
        return $this->areaKind()->label();
    }

    public function assignedAdmins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'client_user_installation_assignments')
            ->withPivot('installation_permission')
            ->withTimestamps()
            ->orderBy('users.name');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function supervisorPosts(): HasMany
    {
        return $this->hasMany(SupervisorPost::class);
    }

    public function structures(): HasMany
    {
        return $this->hasMany(Structure::class);
    }

    public function observatoryEvents(): HasMany
    {
        return $this->hasMany(ObservatoryEvent::class);
    }

    public function hasDoors(): bool
    {
        return Location::query()
            ->withoutGlobalScopes()
            ->where('installation_id', $this->id)
            ->where('is_active', true)
            ->exists();
    }
}
