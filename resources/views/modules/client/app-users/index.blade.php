<x-client-layout title="Usuarios app">
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-lg font-semibold text-white">Usuarios app</h2>
                <p class="text-xs text-slate-400 mt-1">
                    Login: <span class="font-mono text-teal-300">{{ 'usuario@'.($client?->login_suffix ?? '') }}</span>
                    · Máx. {{ $maxPerStructure }} por unidad
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($includeArchived)
                    <a href="{{ route('client.app-users.index') }}" class="text-xs text-slate-400 hover:text-white">Ver activos</a>
                @else
                    <a href="{{ route('client.app-users.index', ['archived' => 1]) }}" class="text-xs text-slate-400 hover:text-white">Ver archivados</a>
                @endif
                <a href="{{ route('client.app-users.create') }}" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-500">Crear</a>
            </div>
        </div>

        <div class="rounded-xl border border-slate-800 overflow-hidden bg-slate-900">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-950/60">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs uppercase text-slate-500">Persona</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-slate-500">Login</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-slate-500 hidden md:table-cell">Unidad</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-slate-500">Canal</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-slate-500">Estado</th>
                        <th class="px-4 py-3 text-right text-xs uppercase text-slate-500">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($appUsers as $appUser)
                        <tr class="hover:bg-slate-800/30">
                            <td class="px-4 py-3 text-white">{{ $appUser->member?->full_name ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-teal-300">{{ $appUser->login_email }}</td>
                            <td class="px-4 py-3 text-slate-500 text-xs hidden md:table-cell">{{ $appUser->member?->structure?->full_path ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-slate-400">{{ $appUser->channelLabel() }}</td>
                            <td class="px-4 py-3">
                                @if ($appUser->trashed())
                                    <span class="text-xs text-amber-300">Archivado</span>
                                @elseif ($appUser->is_active)
                                    <span class="text-xs text-emerald-300">Activo</span>
                                @else
                                    <span class="text-xs text-slate-500">Inactivo</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <a href="{{ route('client.app-users.show', $appUser) }}" class="text-xs text-teal-400 hover:text-teal-300">Ver</a>
                                @unless ($appUser->trashed())
                                    <a href="{{ route('client.app-users.edit', $appUser) }}" class="text-xs text-slate-400 hover:text-white">Editar</a>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500">
                                {{ $includeArchived ? 'No hay usuarios archivados.' : 'Aún no hay usuarios app. Crea el primero.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $appUsers->links() }}
    </div>
</x-client-layout>
