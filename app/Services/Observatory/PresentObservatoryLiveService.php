<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use App\Models\ObservatoryEvent;
use App\Models\RiskModelParameter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PresentObservatoryLiveService
{
    /**
     * @param  array<string, mixed>  $board
     * @param  array<string, mixed>  $map
     * @param  array<string, mixed>  $risk
     * @return array<string, mixed>
     */
    public function execute(array $board, array $map, LengthAwarePaginator $events, string $showRoute, bool $withClient, array $risk = []): array
    {
        return [
            'board' => [
                'total' => (int) ($board['total'] ?? 0),
                'nuevo' => (int) ($board['nuevo'] ?? 0),
                'en_atencion' => (int) ($board['en_atencion'] ?? 0),
                'cerrado' => (int) ($board['cerrado'] ?? 0),
                'closed_rate' => (int) ($board['closed_rate'] ?? 0),
                'load_rate' => (int) ($board['load_rate'] ?? 0),
                'trend' => $board['trend'] ?? ['labels' => ['—'], 'series' => []],
                'peaks' => $board['peaks'] ?? ['labels' => ['—'], 'values' => [0]],
                'crimes' => $board['crimes'] ?? ['labels' => [], 'values' => [], 'colors' => []],
                'sites' => $board['sites'] ?? ['rows' => []],
                'sources' => $board['sources'] ?? ['labels' => [], 'values' => []],
                'origins' => $board['origins'] ?? ['labels' => [], 'values' => []],
                'risk' => $this->riskBoard($risk),
            ],
            'map' => [
                'sites' => $map['sites'] ?? [],
                'points' => $map['points'] ?? [],
                'shifts' => $map['shifts'] ?? [],
            ],
            'bubbles' => $map['bubbles'] ?? [],
            'shifts' => $map['shifts'] ?? [],
            'events' => collect($events->items())->map(function (ObservatoryEvent $event) use ($showRoute, $withClient): array {
                return [
                    'id' => $event->id,
                    'folio' => $event->folio(),
                    'site' => $event->installation?->name,
                    'client' => $withClient ? $event->client?->name : null,
                    'type' => $event->kindLabel(),
                    'status' => $event->statusLabel(),
                    'opened' => $event->opened_at?->format('d/m/Y H:i'),
                    'url' => route($showRoute, $event),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $risk
     * @return array{year: int, rows: list<array<string, mixed>>}
     */
    private function riskBoard(array $risk): array
    {
        $parameters = $risk['parameters'] ?? null;
        $ink = function (string $band) use ($parameters): string {
            if ($parameters instanceof RiskModelParameter) {
                return $parameters->colorFor($band);
            }

            return match ($band) {
                'MEDIO' => '#854d0e',
                'ALTO' => '#9a3412',
                'CRITICO' => '#9f1239',
                default => '#14532d',
            };
        };
        $cap = $parameters instanceof RiskModelParameter ? $parameters->colorFor('CRITICO') : '#9f1239';
        $rows = [];
        foreach ($risk['rows'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $final = (float) ($row['r_final'] ?? 0);
            $band = (string) ($row['band'] ?? 'BAJO');
            $rows[] = [
                'name' => (string) ($row['name'] ?? ''),
                'client' => (string) ($row['client'] ?? ''),
                'comuna' => (string) ($row['comuna'] ?? ''),
                'reports' => (int) ($row['reports'] ?? 0),
                'max_severity' => (float) ($row['max_severity'] ?? 0),
                'weighted_sum' => (float) ($row['weighted_sum'] ?? 0),
                'r_commune' => (float) ($row['r_commune'] ?? 0),
                'r_installation' => (float) ($row['r_installation'] ?? 0),
                'r_final' => $final,
                'band' => $band,
                'fill' => max(0, min(100, $final)),
                'ink' => $ink($band),
                'cap' => $cap,
            ];
        }

        return [
            'year' => (int) ($risk['year'] ?? now()->year),
            'rows' => $rows,
        ];
    }
}
