<?php

declare(strict_types=1);

namespace App\Services\Ops;

use App\Enums\OperationalAlertType;
use App\Models\OperationalAlert;
use App\Models\User;

final class ResolveLiveOperationalAlertsService
{
    /**
     * @return list<array{id: int, type: string, title: string, body: string, latitude: ?float, longitude: ?float, can_attend: bool}>
     */
    public function pending(User $user, int $afterId = 0): array
    {
        $query = OperationalAlert::query()
            ->where('id', '>', $afterId)
            ->whereIn('type', [
                OperationalAlertType::Panic->value,
                OperationalAlertType::Observatory->value,
            ])
            ->where('created_at', '>=', now()->subHours(12))
            ->orderBy('id')
            ->limit(30);

        if ($user->security_company_id) {
            $query->where('security_company_id', $user->security_company_id);
        } elseif ($user->primary_client_id) {
            $query->where('client_id', $user->primary_client_id);
        } else {
            return [];
        }

        $query->where(function ($scope) {
            $scope->where('type', OperationalAlertType::Observatory->value)
                ->orWhere(function ($panic) {
                    $panic->where('type', OperationalAlertType::Panic->value)
                        ->whereDoesntHave('attention');
                });
        });

        return $query->with('attention')
            ->get()
            ->filter(fn (OperationalAlert $alert) => $this->visibleTo($user, $alert))
            ->values()
            ->map(fn (OperationalAlert $alert) => [
                'id' => $alert->id,
                'type' => $alert->type->value,
                'title' => $alert->title,
                'body' => $alert->body,
                'latitude' => $alert->latitude,
                'longitude' => $alert->longitude,
                'installation_id' => $alert->installation_id ? (int) $alert->installation_id : null,
                'show_url' => $this->eventUrl($user, $alert),
                'map_url' => $this->mapUrl($user),
                'can_attend' => $this->canAttend($user, $alert),
            ])
            ->all();
    }

    public function visibleTo(User $user, OperationalAlert $alert): bool
    {
        if ((int) $alert->actor_user_id === (int) $user->id) {
            return false;
        }

        return match ($alert->type) {
            OperationalAlertType::Panic => $this->receivesPanic($user, $alert),
            OperationalAlertType::Observatory => $this->receivesObservatory($user, $alert),
            default => false,
        };
    }

    private function receivesPanic(User $user, OperationalAlert $alert): bool
    {
        if ((int) $user->security_company_id !== (int) $alert->security_company_id) {
            return false;
        }

        if ($user->hasAnyRole(['client-admin', 'client-installation-admin', 'supervisor', 'vigilante'])) {
            return false;
        }

        return $user->can('ops.panic.attend') || $user->can('company.supervision.view');
    }

    private function receivesObservatory(User $user, OperationalAlert $alert): bool
    {
        if ($user->can('observatory.view') && (int) $user->security_company_id === (int) $alert->security_company_id
            && ! $user->hasAnyRole(['client-admin', 'client-installation-admin'])) {
            return true;
        }

        if ($alert->client_id && $user->hasRole('client-admin') && $user->canAccessClient((int) $alert->client_id)) {
            return true;
        }

        if ($alert->installation_id && $user->hasRole('client-installation-admin')
            && $user->canAccessInstallation((int) $alert->installation_id)) {
            return true;
        }

        return false;
    }

    private function canAttend(User $user, OperationalAlert $alert): bool
    {
        if ($alert->type === OperationalAlertType::Observatory) {
            return $user->can('observatory.events.update');
        }

        if ($alert->type !== OperationalAlertType::Panic || ! $user->can('ops.panic.attend')) {
            return false;
        }

        $attention = $alert->attention;
        if ($attention === null) {
            return true;
        }

        return $attention->isAttendedBy($user);
    }

    private function eventUrl(User $user, OperationalAlert $alert): ?string
    {
        $eventId = (int) (($alert->payload ?? [])['event_id'] ?? 0);
        if ($alert->type !== OperationalAlertType::Observatory || $eventId < 1) {
            return null;
        }

        $name = $this->companyPanel($user)
            ? 'company.observatory.events.show'
            : 'client.observatory.events.show';

        return route($name, $eventId);
    }

    private function mapUrl(User $user): ?string
    {
        if (! $user->can('observatory.view')) {
            return null;
        }

        return $this->companyPanel($user)
            ? route('company.observatory.events.index')
            : route('client.observatory.events.index');
    }

    private function companyPanel(User $user): bool
    {
        return $user->hasAnyRole(['company-admin', 'colaborador'])
            || ($user->security_company_id && ! $user->hasAnyRole(['client-admin', 'client-installation-admin']));
    }
}
