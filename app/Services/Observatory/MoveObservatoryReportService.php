<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use App\Enums\ObservatoryEventStatus;
use App\Models\Installation;
use App\Models\ObservatoryEvent;
use App\Models\ObservatoryEventStatusLog;
use App\Models\ObservatoryReport;
use App\Models\User;
use App\Services\Ops\NotifyOpsSurface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MoveObservatoryReportService
{
    /**
     * Instalaciones de la empresa (o de un cliente) y sus folios, para elegir destino.
     *
     * @return list<array{id: int, name: string, folios: list<array{id: int, label: string}>}>
     */
    public function destinations(ObservatoryEvent $event, int $companyId, ?int $onlyClientId = null): array
    {
        $installations = Installation::query()
            ->whereHas('client', function ($query) use ($companyId, $onlyClientId): void {
                $query->where('security_company_id', $companyId);
                if ($onlyClientId !== null) {
                    $query->whereKey($onlyClientId);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $folios = ObservatoryEvent::query()
            ->whereIn('installation_id', $installations->pluck('id'))
            ->whereNull('archived_at')
            ->whereKeyNot($event->id)
            ->withCount('reports')
            ->orderByDesc('id')
            ->limit(400)
            ->get();

        $bySite = $folios->groupBy('installation_id');

        return $installations->map(function (Installation $site) use ($bySite): array {
            $rows = $bySite->get($site->id, collect());

            return [
                'id' => (int) $site->id,
                'name' => $site->name,
                'folios' => $rows->map(function (ObservatoryEvent $row): array {
                    $count = (int) $row->reports_count;

                    return [
                        'id' => (int) $row->id,
                        'label' => $row->folio().' · '.$row->statusLabel().' · '.$count.' reporte'.($count === 1 ? '' : 's'),
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }

    public function execute(
        ObservatoryEvent $source,
        ObservatoryReport $report,
        User $actor,
        string $note,
        int $installationId,
        string $destination,
    ): ObservatoryEvent {
        $note = trim($note);
        if (mb_strlen($note) < 10) {
            throw ValidationException::withMessages([
                'note' => 'La justificación es obligatoria (mínimo 10 caracteres).',
            ]);
        }

        if ((int) $report->event_id !== (int) $source->id) {
            throw ValidationException::withMessages([
                'report' => 'Ese reporte no pertenece a este folio.',
            ]);
        }

        if ($source->status === ObservatoryEventStatus::Cerrado) {
            throw ValidationException::withMessages([
                'report' => 'El folio está cerrado. No se pueden sacar reportes.',
            ]);
        }

        $installation = $this->installation($source, $installationId);

        return DB::transaction(function () use ($source, $report, $actor, $note, $installation, $destination): ObservatoryEvent {
            $locked = ObservatoryEvent::query()->whereKey($source->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === ObservatoryEventStatus::Cerrado) {
                throw ValidationException::withMessages([
                    'report' => 'El folio está cerrado. No se pueden sacar reportes.',
                ]);
            }

            $fresh = ObservatoryReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();
            if ((int) $fresh->event_id !== (int) $locked->id) {
                throw ValidationException::withMessages([
                    'report' => 'Ese reporte ya no está en este folio.',
                ]);
            }

            $target = $this->target($locked, $installation, $destination, $fresh);
            $fromFolio = $locked->folio();
            $code = $fresh->code();
            $was = $locked->status instanceof ObservatoryEventStatus
                ? $locked->status
                : ObservatoryEventStatus::Nuevo;

            $fresh->event_id = $target->id;
            $fresh->installation_id = $installation->id;
            $fresh->client_id = $installation->client_id;
            if ($installation->latitude !== null && $installation->longitude !== null) {
                $fresh->latitude = $installation->latitude;
                $fresh->longitude = $installation->longitude;
            }
            $fresh->save();

            $toFolio = $target->folio();
            $this->log(
                $locked,
                $was,
                $was,
                $actor,
                'Reporte '.$code.' pasó al folio '.$toFolio.'. '.$note,
            );

            $left = ObservatoryReport::query()->where('event_id', $locked->id)->count();
            if ($left < 1) {
                $locked->status = ObservatoryEventStatus::Cerrado;
                $locked->closed_at = now();
                $locked->closed_by_user_id = $actor->id;
                $locked->save();
                $this->log(
                    $locked,
                    $was,
                    ObservatoryEventStatus::Cerrado,
                    $actor,
                    'El folio se cerró porque quedó sin reportes. El reporte '.$code.' quedó en '.$toFolio.'.',
                );
            }

            $targetStatus = $target->status instanceof ObservatoryEventStatus
                ? $target->status
                : ObservatoryEventStatus::Nuevo;
            $this->log(
                $target,
                $targetStatus,
                $targetStatus,
                $actor,
                'Llegó el reporte '.$code.' desde el folio '.$fromFolio.'. '.$note,
            );

            app(NotifyOpsSurface::class)->observatory(
                $locked->refresh(),
                $actor,
                'Pasó el reporte '.$code.' al folio '.$toFolio,
            );

            return $target->refresh();
        });
    }

    private function installation(ObservatoryEvent $source, int $installationId): Installation
    {
        $source->loadMissing('client');
        $companyId = (int) ($source->client?->security_company_id ?? 0);
        $installation = Installation::query()
            ->whereKey($installationId)
            ->whereHas('client', fn ($query) => $query->where('security_company_id', $companyId))
            ->first();

        if (! $installation instanceof Installation) {
            throw ValidationException::withMessages([
                'installation_id' => 'Esa instalación no está en esta empresa.',
            ]);
        }

        return $installation;
    }

    private function target(
        ObservatoryEvent $source,
        Installation $installation,
        string $destination,
        ObservatoryReport $report,
    ): ObservatoryEvent {
        if ($destination === 'new') {
            return ObservatoryEvent::query()->create([
                'client_id' => $installation->client_id,
                'installation_id' => $installation->id,
                'status' => ObservatoryEventStatus::Nuevo,
                'title' => $report->kindLabel(),
                'opened_at' => now(),
            ]);
        }

        if (! ctype_digit($destination)) {
            throw ValidationException::withMessages([
                'destination' => 'Elige un folio o crea uno nuevo.',
            ]);
        }

        $target = ObservatoryEvent::query()
            ->whereKey((int) $destination)
            ->where('installation_id', $installation->id)
            ->whereNull('archived_at')
            ->lockForUpdate()
            ->first();

        if (! $target instanceof ObservatoryEvent || (int) $target->id === (int) $source->id) {
            throw ValidationException::withMessages([
                'destination' => 'Ese folio no está en la instalación elegida.',
            ]);
        }

        return $target;
    }

    private function log(
        ObservatoryEvent $event,
        ObservatoryEventStatus $from,
        ObservatoryEventStatus $to,
        User $actor,
        string $note,
    ): void {
        ObservatoryEventStatusLog::query()->create([
            'event_id' => $event->id,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $actor->id,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
