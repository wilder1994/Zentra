<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use App\Models\ObservatoryEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PresentObservatoryLiveService
{
    /**
     * @param  array<string, mixed>  $board
     * @param  array<string, mixed>  $map
     * @return array<string, mixed>
     */
    public function execute(array $board, array $map, LengthAwarePaginator $events, string $showRoute, bool $withClient): array
    {
        return [
            'board' => [
                'total' => (int) ($board['total'] ?? 0),
                'nuevo' => (int) ($board['nuevo'] ?? 0),
                'en_atencion' => (int) ($board['en_atencion'] ?? 0),
                'cerrado' => (int) ($board['cerrado'] ?? 0),
                'closed_rate' => (int) ($board['closed_rate'] ?? 0),
                'load_rate' => (int) ($board['load_rate'] ?? 0),
                'top' => $board['top'] ?? [],
                'trend' => $board['trend'] ?? ['labels' => ['—'], 'series' => []],
                'peaks' => $board['peaks'] ?? ['labels' => ['—'], 'values' => [0]],
                'sources' => $board['sources'] ?? ['labels' => [], 'values' => []],
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
}
