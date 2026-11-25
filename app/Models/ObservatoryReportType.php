<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Observatory\RiskMath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ObservatoryReportType extends Model
{
    protected $fillable = [
        'security_company_id',
        'name',
        'slug',
        'impacto',
        'urgencia',
        'color',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'impacto' => 'integer',
            'urgencia' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(SecurityCompany::class, 'security_company_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ObservatoryReport::class, 'observatory_report_type_id');
    }

    /** @return array<string, string> */
    public static function optionsFor(int $clientId, bool $activeOnly = true): array
    {
        $companyId = (int) Client::query()->whereKey($clientId)->value('security_company_id');

        return static::query()
            ->where('security_company_id', $companyId)
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (self $type): array => [$type->slug => $type->name])
            ->all();
    }

    /**
     * @return list<array{id: int, ids: list<int>, slug: string, name: string, impacto: int, urgencia: int, score: float, band: string, color: string}>
     */
    public static function catalogForScope(?int $companyId, ?int $clientId, bool $activeOnly = true): array
    {
        if ($companyId === null && $clientId !== null) {
            $companyId = (int) Client::query()->whereKey($clientId)->value('security_company_id');
        }

        $parameters = $companyId > 0 ? RiskModelParameter::forCompany($companyId) : null;

        return static::query()
            ->when($companyId !== null, fn ($q) => $q->where('security_company_id', $companyId))
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (self $type) use ($parameters): array {
                $row = self::catalogRow($type, $parameters);
                $row['ids'] = [(int) $type->id];

                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, slug: string, name: string, impacto: int, urgencia: int, score: float, band: string, color: string}>
     */
    public static function catalogFor(int $clientId, bool $activeOnly = true): array
    {
        $companyId = (int) Client::query()->whereKey($clientId)->value('security_company_id');
        $parameters = $companyId > 0 ? RiskModelParameter::forCompany($companyId) : null;

        return static::query()
            ->where('security_company_id', $companyId)
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (self $type): array => self::catalogRow($type, $parameters))
            ->all();
    }

    /** @return array{id: int, slug: string, name: string, impacto: int, urgencia: int, score: float, band: string, color: string} */
    private static function catalogRow(self $type, ?RiskModelParameter $parameters): array
    {
        $score = RiskMath::score((int) $type->impacto, (int) $type->urgencia);

        return [
            'id' => (int) $type->id,
            'slug' => $type->slug,
            'name' => $type->name,
            'impacto' => (int) $type->impacto,
            'urgencia' => (int) $type->urgencia,
            'score' => $score,
            'band' => $parameters?->bandOf($score) ?? 'BAJO',
            'color' => $type->color,
        ];
    }
}
