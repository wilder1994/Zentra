<x-layouts.resident-app title="Autorizar">
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-2">
            <p class="text-xs text-slate-400 truncate">{{ $appUser->member?->structure?->full_path }}</p>
            <a href="{{ route('resident-app.authorizations.create') }}"
               class="shrink-0 rounded-lg bg-teal-600 px-3 py-2 text-xs font-semibold text-white">Nueva</a>
        </div>

        <ul class="space-y-2">
            @forelse ($authorizations as $auth)
                <li class="rounded-xl border border-slate-800 bg-slate-900/80 p-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-white truncate">{{ $auth->visitor_name }}</p>
                            <p class="text-[11px] text-slate-500">
                                {{ $auth->typeLabel() }}
                                · {{ $auth->validityLabel() }}
                                @if ($auth->vehicle_plate)
                                    · {{ $auth->vehicle_plate }}
                                @endif
                            </p>
                            <p class="text-[11px] mt-1 {{ $auth->state() === 'active' ? 'text-emerald-400' : 'text-slate-500' }}">
                                {{ $auth->stateLabel() }}
                            </p>
                        </div>
                        @if (! in_array($auth->state(), ['cancelled', 'expired', 'used'], true))
                            <form method="POST" action="{{ route('resident-app.authorizations.destroy', $auth) }}"
                                  onsubmit="return confirm('¿Cancelar esta autorización?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-[11px] text-amber-400">Cancelar</button>
                            </form>
                        @endif
                    </div>
                </li>
            @empty
                <li class="rounded-xl border border-dashed border-slate-700 px-4 py-8 text-center text-sm text-slate-500">
                    Aún no hay autorizaciones. Crea la primera.
                </li>
            @endforelse
        </ul>
    </div>
</x-layouts.resident-app>
