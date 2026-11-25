<?php

declare(strict_types=1);

namespace App\Services\Ops;

use App\Enums\ObservatoryReporterRole;
use App\Enums\SupervisorAlarmKind;
use App\Enums\SupervisorAlarmResult;
use App\Enums\SupervisorFieldModule;
use App\Models\ObservatoryReport;
use App\Models\SupervisorAlarmType;
use App\Models\SupervisorFieldLog;
use App\Models\SupervisorShiftReview;
use App\Models\SupervisorSupportType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class BuildActivityBubblesService
{
    /**
     * Últimos reportes de revista, alarma, apoyo y observatorio.
     * Cada burbuja lleva solo los datos que ese tipo tiene.
     *
     * @return list<array{id: string, kind: string, kind_label: string, tone: string, at: string, text: string, lines: list<array{label: string, value: string}>, sort: string}>
     */
    public function forCompany(int $companyId, ?int $clientId = null, bool $onlyObservatoryClients = false): array
    {
        if ($companyId < 1) {
            return [];
        }

        $rows = array_merge(
            $this->reviews($companyId, $clientId, $onlyObservatoryClients),
            $this->fieldLogs($companyId, $clientId, $onlyObservatoryClients),
            $this->reports($companyId, $clientId, $onlyObservatoryClients),
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
    private function reviews(int $companyId, ?int $clientId, bool $onlyObservatoryClients): array
    {
        return SupervisorShiftReview::query()
            ->whereHas('shift', fn ($query) => $query->where('security_company_id', $companyId))
            ->when($clientId !== null, fn ($query) => $query->where('client_id', $clientId))
            ->when($onlyObservatoryClients, fn ($query) => $this->onlyObservatory($query))
            ->with(['shift.user', 'client:id,name', 'supervisorPost.installation:id,name', 'employee', 'fieldLogs:id,supervisor_shift_review_id,module'])
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
                    $this->reviewText($review),
                );
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fieldLogs(int $companyId, ?int $clientId, bool $onlyObservatoryClients): array
    {
        $logs = SupervisorFieldLog::query()
            ->where('security_company_id', $companyId)
            ->whereIn('module', [SupervisorFieldModule::Alarms->value, SupervisorFieldModule::Supports->value])
            ->when($clientId !== null, fn ($query) => $query->where('client_id', $clientId))
            ->when($onlyObservatoryClients, fn ($query) => $this->onlyObservatory($query))
            ->with(['shift.user', 'client:id,name', 'review.supervisorPost.installation:id,name', 'review.employee'])
            ->latest('recorded_at')
            ->limit(40)
            ->get();
        $alarmIds = $logs->map(fn (SupervisorFieldLog $log): int => (int) ($log->payload['alarm_type_id'] ?? 0))->filter()->unique()->values();
        $supportIds = $logs->map(fn (SupervisorFieldLog $log): int => (int) ($log->payload['support_type_id'] ?? 0))->filter()->unique()->values();
        $alarmNames = $alarmIds->isEmpty()
            ? collect()
            : SupervisorAlarmType::query()->whereIn('id', $alarmIds)->pluck('name', 'id');
        $supportNames = $supportIds->isEmpty()
            ? collect()
            : SupervisorSupportType::query()->whereIn('id', $supportIds)->pluck('name', 'id');

        return $logs
            ->map(function (SupervisorFieldLog $log) use ($alarmNames, $supportNames): array {
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
                    $alarm
                        ? $this->alarmText($log, $post?->installation?->name, (string) ($alarmNames[(int) ($log->payload['alarm_type_id'] ?? 0)] ?? ''))
                        : $this->supportText($log, $post?->installation?->name, (string) ($supportNames[(int) ($log->payload['support_type_id'] ?? 0)] ?? '')),
                );
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reports(int $companyId, ?int $clientId, bool $onlyObservatoryClients): array
    {
        return ObservatoryReport::query()
            ->whereHas('client', function ($query) use ($companyId, $onlyObservatoryClients): void {
                $query->where('security_company_id', $companyId)
                    ->when($onlyObservatoryClients, fn ($client) => $client->where('has_observatory', true));
            })
            ->when($clientId !== null, fn ($query) => $query->where('client_id', $clientId))
            ->with(['client:id,name', 'installation:id,name', 'reportedBy:id,name', 'reportType:id,name'])
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
                    $this->reportText($report),
                );
            })
            ->all();
    }

    /**
     * @param  list<array{label: string, value: string}>  $lines
     * @return array<string, mixed>
     */
    private function bubble(string $id, string $kind, string $label, string $tone, mixed $at, array $lines, string $text = ''): array
    {
        $when = $at instanceof \DateTimeInterface ? CarbonImmutable::instance($at) : null;

        return [
            'id' => $id,
            'kind' => $kind,
            'kind_label' => $label,
            'tone' => $tone,
            'at' => $when?->timezone((string) config('app.timezone'))->format('d/m/Y H:i') ?? '',
            'text' => $text,
            'lines' => $lines,
            'sort' => $when?->toIso8601String() ?? '',
        ];
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function onlyObservatory(Builder $query): void
    {
        $query->whereHas('client', fn ($client) => $client->where('has_observatory', true));
    }

    private function reviewText(SupervisorShiftReview $review): string
    {
        $who = trim((string) $review->shift?->user?->name);
        $place = $this->place(
            $review->supervisorPost?->name,
            $review->supervisorPost?->installation?->name,
            $review->client?->name,
            'el puesto',
        );
        $lead = $who !== '' ? $who.' revisó '.$place.'.' : 'Revista en '.$place.'.';
        $guard = trim((string) $review->employee?->fullName());
        $parts = [$lead];
        if ($guard !== '') {
            $parts[] = 'Guarda '.$guard.'.';
        }
        $notes = $this->clip((string) $review->notes, 120);
        if ($review->has_novelty) {
            $parts[] = $notes !== '' ? 'Dejó novedad: '.$notes.'.' : 'Con novedad.';
        } else {
            $parts[] = 'Sin novedad.';
        }
        $did = $review->fieldLogs
            ->filter(fn (SupervisorFieldLog $log): bool => $log->module instanceof SupervisorFieldModule && $log->module->hangsOffReview())
            ->map(fn (SupervisorFieldLog $log): string => mb_strtolower($log->module->label()))
            ->unique()
            ->values()
            ->all();
        if ($did !== []) {
            $parts[] = 'Hizo '.$this->joinList($did).'.';
        }

        return implode(' ', $parts);
    }

    private function alarmText(SupervisorFieldLog $log, ?string $site, string $type): string
    {
        $who = trim((string) $log->shift?->user?->name);
        $kind = SupervisorAlarmKind::tryFrom((string) ($log->payload['kind'] ?? ''));
        $result = SupervisorAlarmResult::tryFrom((string) ($log->payload['result'] ?? ''));
        $type = $this->clip($type, 40);
        $place = $this->place(null, $site, $log->client?->name, '');
        $what = $type !== '' ? ' de '.mb_strtolower($type) : '';
        $lead = $kind === SupervisorAlarmKind::Test
            ? ($who !== '' ? $who.' hizo una prueba de alarma'.$what : 'Prueba de alarma'.$what)
            : ($who !== '' ? $who.' atendió una alarma'.$what : 'Atención de alarma'.$what);
        $sentence = $lead.' en '.$place.'.';
        if ($result instanceof SupervisorAlarmResult) {
            $sentence .= ' Resultado: '.mb_strtolower($result->label()).'.';
        }

        return $sentence;
    }

    private function supportText(SupervisorFieldLog $log, ?string $site, string $type): string
    {
        $who = trim((string) $log->shift?->user?->name);
        $type = $this->clip($type, 40);
        $place = $this->place(null, $site, $log->client?->name, '');
        $lead = $who !== '' ? $who.' prestó apoyo' : 'Apoyo';
        if ($type !== '') {
            $lead .= ' de '.mb_strtolower($type);
        }
        $parts = [$lead.' en '.$place.'.'];
        $reason = $this->clip((string) ($log->payload['reason'] ?? ''), 120);
        if ($reason !== '') {
            $parts[] = $reason.(str_ends_with($reason, '.') ? '' : '.');
        }
        $novelty = (string) ($log->payload['novelty'] ?? '');
        if ($novelty === 'yes') {
            $parts[] = 'Con novedad.';
        } elseif ($novelty === 'no') {
            $parts[] = 'Sin novedad.';
        }

        return implode(' ', $parts);
    }

    private function reportText(ObservatoryReport $report): string
    {
        $who = $this->reporterLead($report);
        $kind = trim($report->kindLabel());
        $place = $this->place(null, $report->installation?->name, $report->client?->name, '');
        $sentence = $who.' reportó'.($kind !== '' && $kind !== '—' ? ' '.mb_strtolower($kind) : '').' en '.$place.'.';
        $body = $this->clip((string) $report->body, 140);
        if ($body !== '') {
            $sentence .= ' «'.$body.'»';
        }

        return $sentence;
    }

    private function reporterLead(ObservatoryReport $report): string
    {
        $role = $report->reporter_role?->label();
        if ($report->is_anonymous || $role === null) {
            return $role !== null ? 'Un '.mb_strtolower($role) : 'Alguien';
        }
        $name = trim((string) ($report->reporter_name ?: $report->reportedBy?->name));
        if ($name !== '') {
            return $name.', '.mb_strtolower($role).',';
        }

        return 'Un '.mb_strtolower($role);
    }

    private function place(?string $post, ?string $site, ?string $client, string $postPrefix): string
    {
        $post = trim((string) $post);
        $site = trim((string) $site);
        $client = trim((string) $client);
        $bits = [];
        if ($post !== '') {
            $bits[] = trim($postPrefix.' '.$post);
        } elseif ($site !== '') {
            $bits[] = $site;
        }
        if ($site !== '' && $post !== '') {
            $bits[] = 'en '.$site;
        }
        $place = $bits !== [] ? implode(' ', $bits) : 'el sitio';
        if ($client !== '') {
            $place .= ', de '.$client;
        }

        return $place;
    }

    /**
     * @param  list<string>  $items
     */
    private function joinList(array $items): string
    {
        if (count($items) < 2) {
            return $items[0] ?? '';
        }
        $last = array_pop($items);

        return implode(', ', $items).' y '.$last;
    }

    private function clip(string $text, int $max): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if ($text === '' || mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max - 1)).'…';
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
