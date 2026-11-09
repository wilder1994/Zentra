<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

final class InstallationProfile extends Model
{
    public const CODE_COLEGIO = 'colegio';

    protected $fillable = [
        'security_company_id',
        'code',
        'name',
        'requires_dane',
        'observatory_community',
        'is_system',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'requires_dane' => 'boolean',
            'observatory_community' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function securityCompany(): BelongsTo
    {
        return $this->belongsTo(SecurityCompany::class);
    }

    public function installations(): HasMany
    {
        return $this->hasMany(Installation::class);
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    public function isColegio(): bool
    {
        return $this->code === self::CODE_COLEGIO;
    }

    public static function idByCode(int $companyId, string $code): int
    {
        $id = self::query()
            ->where('security_company_id', $companyId)
            ->where('code', $code)
            ->value('id');

        if ($id === null) {
            throw new InvalidArgumentException("Installation profile [{$code}] is not defined for company [{$companyId}].");
        }

        return (int) $id;
    }

    /** @return array<int, array{id: int, name: string, requires_dane: bool, observatory_community: bool}> */
    public static function optionsForForm(int $companyId, ?int $includeId = null): array
    {
        return self::query()
            ->where('security_company_id', $companyId)
            ->where(function (Builder $q) use ($includeId): void {
                $q->where('is_active', true);
                if ($includeId !== null) {
                    $q->orWhereKey($includeId);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'requires_dane', 'observatory_community'])
            ->map(static fn (self $profile): array => [
                'id' => (int) $profile->id,
                'name' => $profile->name,
                'requires_dane' => (bool) $profile->requires_dane,
                'observatory_community' => (bool) $profile->observatory_community,
            ])
            ->all();
    }
}
