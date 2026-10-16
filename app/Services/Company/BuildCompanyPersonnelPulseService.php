<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Enums\DocumentFolder;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\SecurityCompany;
use Carbon\CarbonImmutable;

/**
 * Señales de personal para Mi empresa: empleados, parafiscales del mes y carpetas base.
 */
final class BuildCompanyPersonnelPulseService
{
    /** Carpetas mínimas para considerar expediente base (además del parafiscal del mes). */
    private const BASE_FOLDERS = [
        DocumentFolder::HojaVida,
        DocumentFolder::Contratacion,
        DocumentFolder::Afiliaciones,
    ];

    /** @return array<string, mixed> */
    public function for(SecurityCompany $company): array
    {
        $companyId = (int) $company->id;
        $now = CarbonImmutable::now();
        $monthStart = $now->startOfMonth();
        $monthEnd = $now->endOfMonth();
        $periodLabel = $now->locale('es')->translatedFormat('F Y');

        $activeQuery = Employee::query()
            ->where('security_company_id', $companyId)
            ->where('is_active', true);

        $active = (clone $activeQuery)->count();
        $inactive = Employee::query()
            ->where('security_company_id', $companyId)
            ->where('is_active', false)
            ->count();

        $withoutPost = (clone $activeQuery)
            ->whereDoesntHave('supervisorPosts', fn ($q) => $q->where('is_active', true))
            ->count();

        $activeIds = (clone $activeQuery)->pluck('id');

        $lastParafiscalAt = EmployeeDocument::query()
            ->where('security_company_id', $companyId)
            ->where('folder', DocumentFolder::Parafiscales)
            ->max('created_at');

        $withParafiscalMonth = 0;
        if ($activeIds->isNotEmpty()) {
            $withParafiscalMonth = (int) EmployeeDocument::query()
                ->where('security_company_id', $companyId)
                ->where('folder', DocumentFolder::Parafiscales)
                ->whereIn('employee_id', $activeIds)
                ->where(function ($q) use ($monthStart, $monthEnd): void {
                    $q->whereBetween('taken_on', [$monthStart->toDateString(), $monthEnd->toDateString()])
                        ->orWhere(function ($q2) use ($monthStart, $monthEnd): void {
                            $q2->whereNull('taken_on')
                                ->whereBetween('created_at', [$monthStart, $monthEnd]);
                        });
                })
                ->select('employee_id')
                ->distinct()
                ->count('employee_id');
        }

        $expiredDocs = 0;
        $baseComplete = 0;
        $folderBars = [];
        foreach (self::BASE_FOLDERS as $folder) {
            $folderBars[$folder->value] = [
                'label' => $folder->label(),
                'with' => 0,
                'pct' => 0.0,
            ];
        }

        if ($activeIds->isNotEmpty()) {
            $expiredDocs = (int) EmployeeDocument::query()
                ->where('security_company_id', $companyId)
                ->whereIn('employee_id', $activeIds)
                ->whereNotNull('expires_on')
                ->whereDate('expires_on', '<', $now->toDateString())
                ->where('not_applicable', false)
                ->count();

            $rows = EmployeeDocument::query()
                ->where('security_company_id', $companyId)
                ->whereIn('employee_id', $activeIds)
                ->whereIn('folder', array_map(fn (DocumentFolder $f) => $f->value, self::BASE_FOLDERS))
                ->where(function ($q): void {
                    $q->where('not_applicable', true)->orWhereNotNull('disk_path');
                })
                ->select(['employee_id', 'folder'])
                ->distinct()
                ->get();

            $byEmployee = [];
            foreach ($rows as $row) {
                $fid = $row->folder instanceof DocumentFolder
                    ? $row->folder->value
                    : (string) $row->folder;
                $byEmployee[(int) $row->employee_id][$fid] = true;
                if (isset($folderBars[$fid])) {
                    $folderBars[$fid]['with']++;
                }
            }

            $needed = array_map(fn (DocumentFolder $f) => $f->value, self::BASE_FOLDERS);
            foreach ($activeIds as $eid) {
                $have = $byEmployee[(int) $eid] ?? [];
                $ok = true;
                foreach ($needed as $folder) {
                    if (! isset($have[$folder])) {
                        $ok = false;
                        break;
                    }
                }
                if ($ok) {
                    $baseComplete++;
                }
            }

            foreach ($folderBars as $key => $bar) {
                $folderBars[$key]['pct'] = $active > 0
                    ? round(($bar['with'] / $active) * 100, 1)
                    : 0.0;
            }
        }

        $parafiscalPct = $active > 0 ? round(($withParafiscalMonth / $active) * 100, 1) : 0.0;
        $basePct = $active > 0 ? round(($baseComplete / $active) * 100, 1) : 0.0;
        $docsOk = min($baseComplete, $withParafiscalMonth);
        $docsOkPct = $active > 0 ? round(($docsOk / $active) * 100, 1) : 0.0;

        $lastAt = $lastParafiscalAt ? CarbonImmutable::parse($lastParafiscalAt) : null;
        $daysSince = $lastAt ? (int) $lastAt->diffInDays($now) : null;

        return [
            'employees_active' => $active,
            'employees_inactive' => $inactive,
            'without_post' => $withoutPost,
            'period_label' => $periodLabel,
            'period_key' => $now->format('Y-m'),
            'parafiscal' => [
                'with_month' => $withParafiscalMonth,
                'missing_month' => max(0, $active - $withParafiscalMonth),
                'pct' => $parafiscalPct,
                'last_at' => $lastAt?->toIso8601String(),
                'last_label' => $lastAt?->timezone(config('app.timezone'))->format('d M Y H:i'),
                'days_since' => $daysSince,
            ],
            'docs' => [
                'base_complete' => $baseComplete,
                'base_missing' => max(0, $active - $baseComplete),
                'base_pct' => $basePct,
                'expired' => $expiredDocs,
                'ok_with_parafiscal' => $docsOk,
                'ok_pct' => $docsOkPct,
                'folders' => array_values($folderBars),
            ],
            'charts' => [
                'parafiscal_donut' => [
                    'labels' => ['Con planilla', 'Sin planilla'],
                    'values' => [$withParafiscalMonth, max(0, $active - $withParafiscalMonth)],
                ],
                'docs_donut' => [
                    'labels' => ['Expediente base', 'Incompleto'],
                    'values' => [$baseComplete, max(0, $active - $baseComplete)],
                ],
                'folder_bars' => [
                    'labels' => array_column(array_values($folderBars), 'label'),
                    'values' => array_column(array_values($folderBars), 'with'),
                    'pcts' => array_column(array_values($folderBars), 'pct'),
                ],
                'parafiscal_trend' => $this->parafiscalTrend($companyId, $now),
            ],
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function parafiscalTrend(int $companyId, CarbonImmutable $now): array
    {
        $labels = [];
        $values = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = $now->startOfMonth()->subMonths($i);
            $start = $month->startOfMonth();
            $end = $month->endOfMonth();
            $labels[] = $month->locale('es')->translatedFormat('M');
            $values[] = (int) EmployeeDocument::query()
                ->where('security_company_id', $companyId)
                ->where('folder', DocumentFolder::Parafiscales)
                ->where(function ($q) use ($start, $end): void {
                    $q->whereBetween('taken_on', [$start->toDateString(), $end->toDateString()])
                        ->orWhere(function ($q2) use ($start, $end): void {
                            $q2->whereNull('taken_on')
                                ->whereBetween('created_at', [$start, $end]);
                        });
                })
                ->select('employee_id')
                ->distinct()
                ->count('employee_id');
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
