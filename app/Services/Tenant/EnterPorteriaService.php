<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\ClientLifecycle;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class EnterPorteriaService
{
    /** @return Collection<int, Client> */
    public function operableClients(User $user): Collection
    {
        return Client::query()
            ->whereIn('id', $user->assignedClientIds())
            ->where('is_active', true)
            ->where('lifecycle', ClientLifecycle::Active->value)
            ->orderBy('name')
            ->get()
            ->filter(fn (Client $client) => $user->can('operate', $client));
    }

    public function resolve(Request $request): RedirectResponse
    {
        $user = $request->user();
        $clients = $this->operableClients($user);

        if ($clients->isEmpty()) {
            return $this->emptyClientsRedirect($user);
        }

        if ($clients->count() === 1) {
            return redirect()
                ->route('company.clients.show', $clients->first())
                ->with('success', 'Abre «Operar portería» desde el expediente del cliente.');
        }

        return redirect()
            ->route('company.clients.index')
            ->with('success', 'Elige un cliente y usa «Operar portería» en su expediente.');
    }

    private function emptyClientsRedirect(User $user): RedirectResponse
    {
        if ($user->hasRole('super-admin')) {
            return redirect()
                ->route('admin.dashboard')
                ->with('warning', 'No hay clientes activos para operar portería.');
        }

        return redirect()
            ->route('company.clients.index')
            ->with('warning', 'No hay clientes activos para operar. Crea o activa un cliente en cartera.');
    }
}
