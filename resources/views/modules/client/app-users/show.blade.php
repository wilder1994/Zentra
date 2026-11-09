<x-client-layout title="Usuario app">
    <div class="space-y-4 max-w-3xl">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <a href="{{ route('client.app-users.index') }}" class="text-sm text-slate-400 hover:text-white">← Usuarios app</a>
                <h2 class="text-lg font-semibold text-white mt-2">{{ $appUser->member?->full_name ?? $appUser->username }}</h2>
                <p class="text-xs text-slate-400 font-mono mt-1">{{ $appUser->login_email }}</p>
            </div>
            @unless ($appUser->trashed())
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('client.app-users.edit', $appUser) }}" class="rounded-lg border border-slate-700 px-3 py-1.5 text-xs text-slate-200 hover:bg-slate-800">Editar</a>
                    <form method="POST" action="{{ route('client.app-users.resend', $appUser) }}" onsubmit="return confirm('¿Generar nueva contraseña y enviarla por correo?')">
                        @csrf
                        <button type="submit" class="rounded-lg border border-teal-700 px-3 py-1.5 text-xs text-teal-300 hover:bg-teal-950">Reenviar clave</button>
                    </form>
                    <form method="POST" action="{{ route('client.app-users.destroy', $appUser) }}" onsubmit="return confirm('¿Archivar este usuario app?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-lg border border-amber-800 px-3 py-1.5 text-xs text-amber-300 hover:bg-amber-950">Archivar</button>
                    </form>
                </div>
            @endunless
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-4 space-y-2 text-sm">
                <p class="text-xs uppercase text-slate-500">Unidad</p>
                <p class="text-slate-200">{{ $appUser->member?->structure?->full_path ?? '—' }}</p>
                <p class="text-xs text-slate-500">{{ $appUser->member?->structure?->installation?->name }}</p>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-4 space-y-2 text-sm">
                <p class="text-xs uppercase text-slate-500">Cuenta</p>
                <p class="text-slate-200">{{ $appUser->channelLabel() }}</p>
                <p class="text-xs text-slate-400">Correo: {{ $appUser->email ?: '—' }}</p>
                <p class="text-xs text-slate-400">
                    Estado:
                    @if ($appUser->trashed())
                        Archivado
                    @elseif ($appUser->is_active)
                        Activo
                    @else
                        Inactivo
                    @endif
                </p>
                @if ($appUser->credentials_sent_at)
                    <p class="text-xs text-slate-500">Credenciales: {{ $appUser->credentials_sent_at->format('d/m/Y H:i') }}</p>
                @endif
            </div>
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-900 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-800">
                <h3 class="text-sm font-semibold text-white">Historial</h3>
            </div>
            <ul class="divide-y divide-slate-800 text-sm">
                @forelse ($events as $event)
                    <li class="px-4 py-3 flex flex-wrap items-baseline justify-between gap-2">
                        <div>
                            <p class="text-slate-200">{{ $event->label() }}</p>
                            <p class="text-[11px] text-slate-500">
                                {{ $event->actor?->name ?? 'Sistema' }}
                                @if (! empty($event->meta['to']))
                                    · {{ $event->meta['to'] }}
                                @endif
                            </p>
                        </div>
                        <p class="text-[11px] text-slate-500 tabular-nums">{{ $event->created_at?->format('d/m/Y H:i') }}</p>
                    </li>
                @empty
                    <li class="px-4 py-8 text-center text-slate-500">Sin eventos aún.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-client-layout>
