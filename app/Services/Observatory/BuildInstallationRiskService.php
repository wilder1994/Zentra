<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use App\Models\Installation;
use App\Models\ObservatoryCrimeFigure;
use App\Models\ObservatoryCrimeType;
use App\Models\ObservatoryReport;
use App\Models\ObservatoryReportType;
use App\Models\RiskModelParameter;
use App\Support\Geo\CaliComunaLayer;
use App\Support\Geo\CaliVeredaCatalog;
use App\Support\Observatory\RiskMath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/** Riesgo actual por instalación: comuna de Cali + reportes con decaimiento. */
final class BuildInstallationRiskService
{
    public function __construct(private readonly CaliComunaLayer $comunas) {}

    /**
     * @param  list<int>|null  $siteIds
     * @return array{
     *     parameters: RiskModelParameter,
     *     year: int,
     *     rows: list<array<string, mixed>>,
     *     crime_types: \Illuminate\Support\Collection<int, ObservatoryCrimeType>,
     *     report_types: \Illuminate\Support\Collection<int, ObservatoryReportType>
     * }
     */
    public function execute(int $companyId, ?int $clientId, ?array $siteIds): array
    {
        $key = 'obs-risk:'.md5((string) json_encode([$companyId, $clientId, $siteIds]));

        return Cache::remember($key, 20, function () use ($companyId, $clientId, $siteIds): array {
        $parameters = RiskModelParameter::forCompany($companyId);
        $year = (int) now()->year;
        $commune = $this->communeScores($companyId, $year);

        $sites = Installation::query()
            ->withoutGlobalScopes()
            ->where('is_active', true)
            ->whereHas('client', function ($query) use ($companyId, $clientId): void {
                $query->where('security_company_id', $companyId)->where('has_observatory', true);
                if ($clientId !== null) {
                    $query->whereKey($clientId);
                }
            })
            ->when($siteIds !== null, fn ($query) => $query->whereIn('id', $siteIds === [] ? [0] : $siteIds))
            ->with('client:id,name')
            ->orderBy('name')
            ->get();

        $reports = ObservatoryReport::query()
            ->whereIn('installation_id', $sites->pluck('id'))
            ->whereNotNull('observatory_report_type_id')
            ->with('reportType:id,impacto,urgencia')
            ->get(['id', 'installation_id', 'observatory_report_type_id', 'created_at']);

        $bySite = $reports->groupBy(fn (ObservatoryReport $report): int => (int) $report->installation_id);
        $lambda = $parameters->lambda();
        $rows = [];

        foreach ($sites as $site) {
            $hit = $this->comunas->locate(
                $site->latitude !== null ? (float) $site->latitude : null,
                $site->longitude !== null ? (float) $site->longitude : null,
            );
            $code = $hit['code'] ?? '';
            $vereda = null;
            if ($code === '') {
                $vereda = CaliVeredaCatalog::match($site->commune);
                $code = $vereda['code'] ?? '';
            }
            $rCommune = $code !== '' ? (float) ($commune[$code] ?? 0) : 0.0;
            $bucket = $bySite->get((int) $site->id, collect());
            $sum = 0.0;
            $max = 0.0;
            foreach ($bucket as $report) {
                $type = $report->reportType;
                if (! $type instanceof ObservatoryReportType) {
                    continue;
                }
                $weight = RiskMath::weight((int) $type->impacto, (int) $type->urgencia);
                $days = $this->elapsedDays($report->created_at);
                $sum += round($weight * RiskMath::decay($lambda, $days), 2);
                $max = max($max, $weight);
            }
            $rSite = RiskMath::installationScore($sum, $parameters->report_points_for_full);
            $final = RiskMath::integrated($parameters, $rCommune, $rSite);
            $rows[] = [
                'id' => (int) $site->id,
                'name' => $site->name,
                'client' => $site->client?->name ?? '',
                'comuna' => $hit['name'] ?? ($vereda['label'] ?? 'Sin comuna Cali'),
                'reports' => $bucket->count(),
                'max_severity' => round($max, 1),
                'weighted_sum' => round($sum, 2),
                'r_commune' => $rCommune,
                'r_installation' => $rSite,
                'r_final' => $final,
                'band' => $parameters->bandOf($final),
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['r_final'] <=> $a['r_final'] ?: strcmp($a['name'], $b['name']));

        return [
            'parameters' => $parameters,
            'year' => $year,
            'rows' => $rows,
            'crime_types' => ObservatoryCrimeType::query()
                ->where('security_company_id', $companyId)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'report_types' => ObservatoryReportType::query()
                ->where('security_company_id', $companyId)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reportLog(int $companyId): array
    {
        $parameters = RiskModelParameter::forCompany($companyId);
        $lambda = $parameters->lambda();
        $reports = ObservatoryReport::query()
            ->whereHas('client', fn ($query) => $query->where('security_company_id', $companyId))
            ->with(['reportType:id,name,slug,impacto,urgencia', 'installation:id,name'])
            ->latest('id')
            ->limit(300)
            ->get();

        $rows = [];
        foreach ($reports as $report) {
            $type = $report->reportType;
            $impacto = $type instanceof ObservatoryReportType ? (int) $type->impacto : 0;
            $urgencia = $type instanceof ObservatoryReportType ? (int) $type->urgencia : 0;
            $score = $impacto > 0 && $urgencia > 0 ? RiskMath::score($impacto, $urgencia) : 0.0;
            $weight = $impacto > 0 && $urgencia > 0 ? RiskMath::weight($impacto, $urgencia) : 0.0;
            $days = $this->elapsedDays($report->created_at);
            $factor = round(RiskMath::decay($lambda, $days), 3);
            $rows[] = [
                'id' => (int) $report->id,
                'site' => $report->installation?->name ?? '—',
                'type' => $type instanceof ObservatoryReportType ? $type->name : $report->kindLabel(),
                'cruce' => $impacto > 0 ? $impacto.'×'.$urgencia : '—',
                'score' => $score,
                'band' => $parameters->bandOf($score),
                'days' => $days,
                'severity' => $weight,
                'decay' => $factor,
                'aporte' => round($weight * $factor, 2),
            ];
        }

        return $rows;
    }

    /**
     * Comunas de Cali en el orden del catálogo, con el índice exógeno del año en curso.
     *
     * @return list<array{name: string, score: float, band: string, color: string}>
     */
    public function comunaBoard(int $companyId, string $onlyCode = ''): array
    {
        $parameters = RiskModelParameter::forCompany($companyId);
        $scores = $this->communeScores($companyId, (int) now()->year);
        $rows = [];
        foreach ($this->comunas->catalog() as $comuna) {
            if ($onlyCode !== '' && $comuna['code'] !== $onlyCode) {
                continue;
            }
            $score = (float) ($scores[$comuna['code']] ?? 0);
            $band = RiskMath::band($score, $parameters);
            $rows[] = [
                'name' => $comuna['name'],
                'score' => $score,
                'band' => $band,
                'color' => $parameters->colorFor($band),
            ];
        }

        return $rows;
    }

    /** @return array<string, float> */
    private function communeScores(int $companyId, int $year): array
    {
        $types = ObservatoryCrimeType::query()
            ->where('security_company_id', $companyId)
            ->where('is_active', true)
            ->get();
        $weights = [];
        foreach ($types as $type) {
            $weights[(int) $type->id] = RiskMath::weight((int) $type->impacto, (int) $type->probabilidad);
        }

        $figures = ObservatoryCrimeFigure::query()
            ->where('security_company_id', $companyId)
            ->where('year', $year)
            ->get();

        $index = [];
        foreach ($this->comunas->catalog() as $comuna) {
            $code = (string) $comuna['code'];
            $index[$code] = $this->weighted($figures, $code, $weights);
        }
        foreach (CaliVeredaCatalog::all() as $vereda) {
            $index[$vereda['code']] = $this->weighted($figures, $vereda['code'], $weights);
        }

        $comunaMax = 0.0;
        foreach ($this->comunas->codes() as $code) {
            $comunaMax = max($comunaMax, $index[$code] ?? 0);
        }
        $veredaMax = 0.0;
        foreach (CaliVeredaCatalog::codes() as $code) {
            $veredaMax = max($veredaMax, $index[$code] ?? 0);
        }
        $scores = [];
        foreach ($index as $code => $value) {
            $max = str_starts_with((string) $code, 'v') ? $veredaMax : $comunaMax;
            $scores[$code] = $max > 0 ? round(($value / $max) * 100, 1) : 0.0;
        }

        return $scores;
    }

    /**
     * @param  Collection<int, ObservatoryCrimeFigure>  $figures
     * @param  array<int, float>  $weights
     */
    private function weighted(Collection $figures, string $code, array $weights): float
    {
        $total = 0.0;
        foreach ($figures->where('comuna_code', $code) as $figure) {
            $total += (int) $figure->total * ($weights[(int) $figure->observatory_crime_type_id] ?? 0);
        }

        return $total;
    }

    private function elapsedDays(?Carbon $at): int
    {
        if ($at === null || $at->greaterThan(now())) {
            return 0;
        }

        return max(0, (int) $at->copy()->startOfDay()->diffInDays(now()->startOfDay()));
    }
}
