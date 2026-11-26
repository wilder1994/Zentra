<?php

declare(strict_types=1);

namespace App\Services\Ops;

use App\Enums\OperationalAlertType;
use App\Events\OpsSurfaceChanged;
use App\Models\Client;
use App\Models\ObservatoryEvent;
use App\Models\OperationalAlert;
use App\Models\User;
use Throwable;

final class NotifyOpsSurface
{
    public function fromAlert(OperationalAlert $alert): void
    {
        $actor = $alert->actor_user_id
            ? User::query()->whereKey($alert->actor_user_id)->value('name')
            : null;

        $kind = $alert->type instanceof OperationalAlertType
            ? $alert->type->value
            : (string) $alert->type;

        $this->send(
            (int) $alert->security_company_id,
            $alert->client_id !== null ? (int) $alert->client_id : null,
            $kind,
            (string) $alert->body,
            is_string($actor) ? $actor : null,
        );
    }

    public function observatory(ObservatoryEvent $event, ?User $actor, string $summary): void
    {
        $event->loadMissing('client');
        $client = $event->client;
        $companyId = (int) ($client?->security_company_id ?? 0);

        $this->send(
            $companyId,
            (int) $event->client_id,
            'observatory',
            $summary,
            $actor?->name,
        );
    }

    public function supervisionMap(int $companyId, ?string $actor): void
    {
        $this->send($companyId, null, 'supervision-ping', 'Mapa de supervisión', $actor);
    }

    public function send(int $companyId, ?int $clientId, string $kind, string $summary, ?string $actor): void
    {
        if ($companyId < 1 && ($clientId === null || $clientId < 1)) {
            return;
        }

        if ($companyId < 1 && $clientId !== null) {
            $companyId = (int) Client::query()->whereKey($clientId)->value('security_company_id');
        }

        try {
            broadcast(new OpsSurfaceChanged(
                $companyId,
                $clientId,
                $kind,
                mb_substr(trim($summary), 0, 180),
                $actor,
            ));
        } catch (Throwable) {
            // Reverb o el bus pueden no estar arriba; el sondeo JSON cubre el tablero.
        }
    }
}
