<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommercialMetal;
use App\Enums\CompanyPackageSku;
use App\Enums\LegalCorpusType;
use App\Enums\PackageModality;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class LegalCorpusVersion extends Model
{
    protected $fillable = [
        'type',
        'package_sku',
        'version',
        'title',
        'content',
        'effective_from',
        'superseded_at',
        'content_hash',
    ];

    protected function casts(): array
    {
        return [
            'type' => LegalCorpusType::class,
            'effective_from' => 'date',
            'superseded_at' => 'datetime',
        ];
    }

    public function isCurrent(): bool
    {
        return $this->superseded_at === null;
    }

    /** Etiqueta del plan del contrato (`bronce_manual` → «Plan Bronce · Sin hardware»). */
    public function planLabel(): ?string
    {
        if ($this->package_sku === null || $this->package_sku === '') {
            return null;
        }

        [$metal, $modality] = array_pad(explode('_', (string) $this->package_sku, 2), 2, null);
        $metalEnum = CommercialMetal::tryFrom((string) $metal);
        $modalityEnum = PackageModality::tryFrom((string) $modality);
        if ($metalEnum === null || $modalityEnum === null) {
            return CompanyPackageSku::tryFrom($this->package_sku)?->label() ?? $this->package_sku;
        }

        return 'Plan '.$metalEnum->label().' · '.$modalityEnum->label();
    }

    public function isGlobal(): bool
    {
        return $this->package_sku === null;
    }

    public static function currentGlobal(LegalCorpusType $type): ?self
    {
        return self::query()
            ->where('type', $type->value)
            ->whereNull('package_sku')
            ->whereNull('superseded_at')
            ->first();
    }

    /** Documentos globales vigentes (sin contrato por SKU). */
    /** @return Collection<int, LegalCorpusVersion> */
    public static function currentGlobals(): Collection
    {
        return self::query()
            ->whereNull('superseded_at')
            ->whereNull('package_sku')
            ->where('type', '!=', LegalCorpusType::Contract->value)
            ->orderBy('type')
            ->get();
    }

    /** Corpus vigente para contratación de un SKU: globales + contrato del plan. */
    /** @return Collection<int, LegalCorpusVersion> */
    public static function currentForPackage(?CompanyPackageSku $sku): Collection
    {
        $globals = self::currentGlobals();

        if ($sku === null) {
            return $globals;
        }

        $contract = self::query()
            ->whereNull('superseded_at')
            ->where('type', LegalCorpusType::Contract->value)
            ->where('package_sku', $sku->contractKey())
            ->first();

        if ($contract === null) {
            return $globals;
        }

        return $globals->prepend($contract)->values();
    }

    /**
     * @deprecated Prefer currentForPackage / currentGlobals
     *
     * @return Collection<int, LegalCorpusVersion>
     */
    public static function currentForAllTypes(): Collection
    {
        return self::query()
            ->whereNull('superseded_at')
            ->orderByRaw("CASE WHEN type = 'contract' THEN 0 ELSE 1 END")
            ->orderBy('package_sku')
            ->orderBy('type')
            ->get();
    }
}
