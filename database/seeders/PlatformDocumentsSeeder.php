<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CommercialMetal;
use App\Enums\LegalCorpusType;
use App\Enums\PackageModality;
use App\Models\DocumentRetentionSeries;
use App\Models\LegalCorpusVersion;
use Database\Seeders\Support\LegalCorpusDraftContent;
use Illuminate\Database\Seeder;

final class PlatformDocumentsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedLegalCorpus();
        $this->seedTrd();
    }

    private function seedLegalCorpus(): void
    {
        // Contratos legacy globales (pre package_sku) → se reemplazan por contrato por SKU.
        LegalCorpusVersion::query()
            ->where('type', LegalCorpusType::Contract->value)
            ->whereNull('package_sku')
            ->delete();

        // Contratos por cupo fijo (pack_N_*) → reemplazados por contrato por plan.
        LegalCorpusVersion::query()
            ->where('type', LegalCorpusType::Contract->value)
            ->where('package_sku', 'like', 'pack\_%')
            ->whereNull('superseded_at')
            ->update(['superseded_at' => now()]);

        $globals = [
            [LegalCorpusType::Terms, LegalCorpusDraftContent::terms()],
            [LegalCorpusType::PrivacyPolicy, LegalCorpusDraftContent::privacy()],
            [LegalCorpusType::ProcedureLifecycle, LegalCorpusDraftContent::procedureLifecycle()],
            [LegalCorpusType::MinorsDataPolicy, LegalCorpusDraftContent::minorsDataPolicy()],
        ];

        foreach ($globals as [$type, $content]) {
            $this->upsertVersion($type, null, $type->label(), $content);
        }

        foreach (CommercialMetal::cases() as $metal) {
            foreach ([PackageModality::Manual, PackageModality::Hardware] as $modality) {
                $this->upsertVersion(
                    LegalCorpusType::Contract,
                    $metal->value.'_'.$modality->value,
                    LegalCorpusType::Contract->label().' · Plan '.$metal->label().' · '.$modality->label(),
                    LegalCorpusDraftContent::contractForPlan($metal, $modality),
                );
            }
        }
    }

    private function upsertVersion(
        LegalCorpusType $type,
        ?string $packageSku,
        string $title,
        string $content,
    ): void {
        LegalCorpusVersion::query()->updateOrCreate(
            [
                'type' => $type->value,
                'package_sku' => $packageSku,
                'version' => '1.0',
            ],
            [
                'title' => $title,
                'content' => $content,
                'effective_from' => now()->toDateString(),
                'superseded_at' => null,
                'content_hash' => hash('sha256', $content),
            ],
        );
    }

    private function seedTrd(): void
    {
        $rows = [
            ['Comercial', 'Contrato + aceptación', '10 años', null, 'Conservar', 'CCom art. 60 / Ley 962'],
            ['Comercial', 'Factura electrónica', '10 años', 3650, 'Conservar', 'Sistema FE DIAN'],
            ['Comercial', 'Actas de ciclo', '10 años', 3650, 'Conservar', 'TRD interna'],
            ['Operativo tenant', 'Censo / visitantes / logs', '365 días post baja', 365, 'Purga certificada', 'Ley 1581'],
            ['Normativa interna', 'Políticas publicadas', 'Histórico vigente', null, 'Conservar', 'Ley 594 metodología'],
        ];

        foreach ($rows as $i => [$series, $subseries, $label, $days, $disposition, $basis]) {
            DocumentRetentionSeries::query()->updateOrCreate(
                ['series' => $series, 'subseries' => $subseries],
                [
                    'retention_label' => $label,
                    'retention_days' => $days,
                    'disposition' => $disposition,
                    'legal_basis' => $basis,
                    'sort_order' => $i + 1,
                ],
            );
        }
    }
}
