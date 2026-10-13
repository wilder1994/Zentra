<x-client-layout title="Autorización">
    @php
        $state = $authorization->state();
        $badge = match ($state) {
            'active' => 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/40',
            'upcoming' => 'bg-sky-500/15 text-sky-300 ring-sky-500/40',
            'used' => 'bg-indigo-500/15 text-indigo-300 ring-indigo-500/40',
            'cancelled' => 'bg-red-500/10 text-red-300 ring-red-500/30',
            default => 'bg-slate-700/40 text-slate-300 ring-slate-600',
        };
        $shareable = in_array($state, ['active', 'upcoming'], true);
        $message = "Autorización de ingreso · {$authorization->client?->name}\n"
            ."{$authorization->typeLabel()} a {$authorization->destinationLabel()} · {$authorization->validityLabel()}\n"
            ."Muestra este enlace en portería: {$passUrl}";
    @endphp

    <x-slot:actions>
        <a href="{{ route('client.authorizations.index') }}" class="rounded-lg bg-slate-800 px-3 py-2 text-sm text-white hover:bg-slate-700">← Volver</a>
    </x-slot:actions>

    <div class="mx-auto grid max-w-6xl gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-5">
            <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <span class="rounded-md bg-slate-800 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-300">{{ $authorization->typeLabel() }}</span>
                        <h1 class="mt-2 text-xl font-bold text-white">{{ $authorization->visitor_name }}</h1>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $badge }}">{{ $authorization->stateLabel() }}</span>
                </div>

                <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs uppercase tracking-wide text-slate-500">Destino</dt><dd class="mt-0.5 text-white">{{ $authorization->destinationLabel() }}@if ($authorization->installation) <span class="text-slate-500">· {{ $authorization->installation->name }}</span>@endif</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-slate-500">Autoriza</dt><dd class="mt-0.5 text-white">{{ $authorization->authorizerLabel() }}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-slate-500">Vigencia</dt><dd class="mt-0.5 text-white">{{ $authorization->valid_from?->format('d/m/Y') }} – {{ $authorization->valid_until?->format('d/m/Y') }}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-slate-500">Entradas</dt><dd class="mt-0.5 text-white">{{ $authorization->multi_entry ? 'Múltiples durante la vigencia' : 'Una por persona' }}</dd></div>
                    @if ($authorization->vehicle_plate)
                        <div><dt class="text-xs uppercase tracking-wide text-slate-500">Placa</dt><dd class="mt-0.5 font-mono text-white">{{ $authorization->vehicle_plate }}</dd></div>
                    @endif
                    <div><dt class="text-xs uppercase tracking-wide text-slate-500">Creada por</dt><dd class="mt-0.5 text-white">{{ $authorization->creator?->name ?? ($authorization->origin === 'import' ? 'Importación' : '—') }} <span class="text-slate-500">· {{ $authorization->created_at?->format('d/m/Y H:i') }}</span></dd></div>
                    @if ($authorization->notes)
                        <div class="sm:col-span-2"><dt class="text-xs uppercase tracking-wide text-slate-500">Notas para portería</dt><dd class="mt-0.5 whitespace-pre-line text-slate-200">{{ $authorization->notes }}</dd></div>
                    @endif
                    @if ($authorization->cancelled_at)
                        <div class="sm:col-span-2 text-red-300">Cancelada el {{ $authorization->cancelled_at->format('d/m/Y H:i') }}.</div>
                    @endif
                </dl>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-800 px-5 py-3">
                    <h3 class="text-sm font-semibold text-white">Personas ({{ $authorization->guests->count() }})</h3>
                    <span class="text-xs text-slate-400">Ingresaron {{ $authorization->guests->where('entries_count', '>', 0)->count() }}</span>
                </div>
                <div class="divide-y divide-slate-800">
                    @foreach ($authorization->guests as $guest)
                        <div class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-white">{{ $guest->fullName() }}</p>
                                <p class="text-xs text-slate-500">{{ $guest->document_type }} {{ $guest->document_number ?? '—' }}@if ($guest->phone) · {{ $guest->phone }}@endif</p>
                            </div>
                            @if ($guest->entries_count > 0)
                                <span class="shrink-0 rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-xs text-emerald-300 ring-1 ring-emerald-500/40">
                                    Ingresó {{ $guest->last_entry_at?->format('d/m H:i') }}@if ($guest->entries_count > 1) · {{ $guest->entries_count }} veces @endif
                                </span>
                            @else
                                <span class="shrink-0 rounded-full bg-slate-800 px-2.5 py-0.5 text-xs text-slate-400">Sin ingresar</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <aside class="space-y-4" x-data="{ copied: false }">
            <section class="flex flex-col items-center rounded-2xl border border-slate-800 bg-slate-900 p-5">
                @if ($shareable)
                    <x-authorization-qr :value="$passUrl" size="w-56" id="auth-qr" />
                    <p class="mt-3 text-center text-xs text-slate-400">El visitante muestra este QR en portería, o da su cédula.</p>
                    <div class="mt-4 grid w-full gap-2">
                        <a href="https://wa.me/?text={{ rawurlencode($message) }}" target="_blank" rel="noopener"
                           class="rounded-lg bg-emerald-600 px-3 py-2 text-center text-sm font-semibold text-white hover:bg-emerald-500">Compartir por WhatsApp</a>
                        <button type="button" @click="navigator.clipboard.writeText(@js($passUrl)); copied = true; setTimeout(() => copied = false, 1800)"
                                class="rounded-lg bg-slate-800 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-700"
                                x-text="copied ? '¡Enlace copiado!' : 'Copiar enlace'">Copiar enlace</button>
                        <button type="button" @click="downloadQr()" class="rounded-lg bg-slate-800 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-700">Descargar QR</button>
                        <a href="{{ $passUrl }}" target="_blank" rel="noopener" class="text-center text-xs text-teal-400 hover:text-teal-300">Ver pase público</a>
                    </div>
                @else
                    <p class="text-center text-sm text-slate-400">Esta autorización ya no está vigente; el QR no se comparte. Si el visitante llega, portería confirma con la unidad.</p>
                @endif
            </section>

            @if ($shareable)
                <form method="POST" action="{{ route('client.authorizations.cancel', $authorization) }}"
                      onsubmit="return confirm('¿Cancelar esta autorización? El QR dejará de ser válido.')">
                    @csrf
                    @method('PATCH')
                    <button class="w-full rounded-lg border border-red-500/40 px-3 py-2 text-sm font-semibold text-red-300 hover:bg-red-500/10">Cancelar autorización</button>
                </form>
            @endif
        </aside>
    </div>

    @push('scripts')
        <script>
            function downloadQr() {
                const source = document.querySelector('#auth-qr svg');
                if (!source) return;
                const svg = source.cloneNode(true);
                svg.setAttribute('width', '640');
                svg.setAttribute('height', '640');
                const img = new Image();
                const data = new XMLSerializer().serializeToString(svg);
                img.onload = () => {
                    const size = 720, pad = 40;
                    const canvas = Object.assign(document.createElement('canvas'), { width: size, height: size });
                    const ctx = canvas.getContext('2d');
                    ctx.fillStyle = '#fff';
                    ctx.fillRect(0, 0, size, size);
                    ctx.drawImage(img, pad, pad, size - pad * 2, size - pad * 2);
                    const a = Object.assign(document.createElement('a'), { href: canvas.toDataURL('image/png'), download: 'autorizacion-{{ $authorization->id }}.png' });
                    a.click();
                };
                img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(data);
            }
        </script>
    @endpush
</x-client-layout>
