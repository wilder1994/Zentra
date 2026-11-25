<?php

declare(strict_types=1);

namespace App\Services\Ops;

use App\Enums\ObservatoryReporterRole;
use App\Enums\SupervisorFieldModule;
use App\Models\ObservatoryReport;
use App\Models\SupervisorFieldLog;
use App\Models\SupervisorShiftReview;
use Carbon\CarbonImmutable;

final class BuildActivityBubblesService
{
    /**
     * Últimos reportes de revista, alarma, apoyo y observatorio.
     * Cada burbuja lleva solo los datos que ese tipo tiene.
     *
     * @return list<array{id: string, kind: string, kind_label: string, tone: string, at: string, lines: list<array{label: string, value: string}>, sort: string}>
     */
    public function forCompany(int $companyId, ?int $clientId = null): array
    {
        if ($companyId < 1) {
            return [];
        }

        $rows = array_merge(
            $this->reviews($companyId, $clientId),
            $this->fieldLogs($companyId, $clientId),
            $this->reports($companyId, $clientId),
        );
        usort($rows, static fn (array $a, array $b): int => strcmp($b['sort'], $a['sort']));

        return array_map(static function (array $row): array {
            unset($row['sort']);

            return $row;
        }, array_slice($rows, 0, 40));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reviews(int $companyId, ?int $clientId): array
    {
        return SupervisorShiftReview::query()
            ->whereHas('shift', fn ($query) => $query->where('security_company_id', $companyId))
            ->when($clientId !== null, fn ($query) => $query->where('client_id', $clientId))
            ->with(['shift.user', 'client:id,name', 'supervisorPost.installation:id,name', 'employee'])
            ->latest('recorded_at')
            ->limit(40)
            ->get()
            ->map(function (SupervisorShiftReview $review): array {
                $at = $review->recorded_at;

                return $this->bubble(
                    'revista-'.$review->id,
                    'revista',
                    'Revista',
                    'indigo',
                    $at,
                    $this->lines([
                        'Hora' => $this->clock($at),
                        'Fecha' => $this->day($at),
                        'Supervisor' => $review->shift?->user?->name,
                        'Cliente' => $review->client?->name,
                        'Instalación' => $review->supervisorPost?->installation?->name,
                        'Puesto' => $review->supervisorPost?->name,
                        'Guarda' => $review->employee?->fullName(),
                        'Cédula' => $review->employee?->document_number,
                    ]),
                );
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fieldLogs(int $companyId, ?int $clientId): array
    {
        return SupervisorFieldLog::query()
            ->where('security_company_id', $companyId)
            ->whereIn('module', [SupervisorFieldModule::Alarms->value, SupervisorFieldModule::Supports->value])
            ->when($clientId !== null, fn ($query) => $query->where('client_id', $clientId))
            ->with(['shift.user', 'client:id,name', 'review.supervisorPost.installation:id,name', 'review.employee'])
            ->latest('recorded_at')
            ->limit(40)
            ->get()
            ->map(function (SupervisorFieldLog $log): array {
                $alarm = $log->module === SupervisorFieldModule::Alarms;
                $at = $log->recorded_at;
                $post = $log->review?->supervisorPost;
                $guard = $log->review?->employee;

                return $this->bubble(
                    ($alarm ? 'alarma-' : 'apoyo-').$log->id,
                    $alarm ? 'alarma' : 'apoyo',
                    $alarm ? 'Alarma' : 'Apoyo',
                    $alarm ? 'red' : 'sky',
                    $at,
                    $this->lines([
                        'Hora' => $this->clock($at),
                        'Fecha' => $this->day($at),
                        'Supervisor' => $log->shift?->user?->name,
                        'Cliente' => $log->client?->name,
                        'Instalación' => $post?->installation?->name,
                        'Puesto' => $post?->name,
                        'Guarda' => $guard?->fullName(),
                        'Cédula' => $guard?->document_number,
                    ]),
                );
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reports(int $companyId, ?int $clientId): array
    {
        return ObservatoryReport::query()
            ->whereHas('client', fn ($query) => $query->where('security_company_id', $companyId))
            ->when($clientId !== null, fn ($query) => $query->where('client_id', $clientId))
            ->with(['client:id,name', 'installation:id,name', 'reportedBy:id,name'])
            ->latest('id')
            ->limit(40)
            ->get()
            ->map(function (ObservatoryReport $report): array {
                $at = $report->created_at;
                $supervisor = $report->reporter_role === ObservatoryReporterRole::Supervisor
                    ? ($report->reporter_name ?: $report->reportedBy?->name)
                    : null;

                return $this->bubble(
                    'observatorio-'.$report->id,
                    'observatorio',
                    $report->source?->label() ?? 'Observatorio',
                    'amber',
                    $at,
                    $this->lines([
                        'Hora' => $this->clock($at),
                        'Fecha' => $this->day($at),
                        'Canal' => $report->source?->label(),
                        'Origen' => $report->reporter_role?->label(),
                        'Supervisor' => $supervisor,
                        'Cliente' => $report->client?->name,
                        'Instalación' => $report->installation?->name,
                    ]),
                );
            })
            ->all();
    }

    /**
     * @param  list<array{label: string, value: string}>  $lines
     * @return array<string, mixed>
     */
    private function bubble(string $id, string $kind, string $label, string $tone, mixed $at, array $lines): array
    {
        $when = $at instanceof \DateTimeInterface ? CarbonImmutable::instance($at) : null;

        return [
            'id' => $id,
            'kind' => $kind,
            'kind_label' => $label,
            'tone' => $tone,
            'at' => $when?->timezone((string) config('app.timezone'))->format('d/m/Y H:i') ?? '',
            'lines' => $lines,
            'sort' => $when?->toIso8601String() ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $pairs
     * @return list<array{label: string, value: string}>
     */
    private function lines(array $pairs): array
    {
        $lines = [];
        foreach ($pairs as $label => $value) {
            $text = trim((string) ($value ?? ''));
            if ($text === '') {
                continue;
            }
            $lines[] = ['label' => $label, 'value' => $text];
        }

        return $lines;
    }

    private function clock(mixed $at): ?string
    {
        return $at instanceof \DateTimeInterface
            ? CarbonImmutable::instance($at)->timezone((string) config('app.timezone'))->format('H:i')
            : null;
    }

    private function day(mixed $at): ?string
    {
        return $at instanceof \DateTimeInterface
            ? CarbonImmutable::instance($at)->timezone((string) config('app.timezone'))->format('d/m/Y')
            : null;
    }
}
