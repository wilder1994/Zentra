@php
    $present = fn ($row) => [
        'id' => $row->id,
        'client' => $row->client?->name ?? '—',
        'installation' => $row->installation?->name ?? '—',
        'supervisor' => $row->requester?->name ?? '—',
        'requested_at' => $row->created_at?->format('d/m/Y H:i'),
        'accuracy' => $row->accuracy_m,
        'distance' => $row->distance_m,
        'previous' => $row->hasPrevious() ? ['lat' => (float) $row->previous_latitude, 'lng' => (float) $row->previous_longitude] : null,
        'requested' => ['lat' => (float) $row->latitude, 'lng' => (float) $row->longitude],
        'pending' => $row->status === \App\Enums\InstallationGeoRequestStatus::Pending,
        'status' => $row->status->label(),
        'reviewer' => $row->reviewer?->name,
        'reviewed_at' => $row->reviewed_at?->format('d/m/Y H:i'),
        'note' => $row->review_note,
        'approve_url' => route('company.supervision.geo.approve', $row),
        'reject_url' => route('company.supervision.geo.reject', $row),
    ];
    $distanceLabel = fn (?int $m) => $m === null ? 'Sin punto previo' : ($m >= 1000 ? number_format($m / 1000, 1, ',', '.').' km' : $m.' m');
@endphp

<x-company-layout title="Supervisión">
    <x-slot:headerTabs>
        @can('company.supervision.view')
            <a href="{{ route('company.supervision.index', ['tab' => 'live']) }}" class="admin-header-tab">En vivo</a>
            <a href="{{ route('company.supervision.index', ['tab' => 'history']) }}" class="admin-header-tab">Historial</a>
            <a href="{{ route('company.supervision.index', ['tab' => 'summary']) }}" class="admin-header-tab">Resumen</a>
            <a href="{{ route('company.supervision.index', ['tab' => 'sheets']) }}" class="admin-header-tab">Fichas</a>
        @endcan
        <a href="{{ route('company.supervision.geo.index') }}" class="admin-header-tab is-active">
            Georreferenciaciones
            @if ($pendingCount > 0)
                <span class="ml-1 inline-flex min-w-[1.25rem] justify-center rounded-full bg-amber-500 px-1.5 text-[11px] font-bold text-slate-950">{{ $pendingCount }}</span>
            @endif
        </a>
    </x-slot:headerTabs>

    <div class="space-y-4" x-data="geoRequests(@js([
        'rows' => $rows->getCollection()->map($present)->values(),
        'openId' => $openId,
        'mapsKey' => $mapsKey,
        'csrf' => csrf_token(),
    ]))">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="inline-flex rounded-lg bg-slate-900 p-1 ring-1 ring-slate-800">
                <a href="{{ route('company.supervision.geo.index') }}" @class(['rounded-md px-3 py-1.5 text-sm font-semibold', 'bg-indigo-600 text-white' => $tab === 'pending', 'text-slate-400 hover:text-white' => $tab !== 'pending'])>
                    Pendientes ({{ $pendingCount }})
                </a>
                <a href="{{ route('company.supervision.geo.index', ['tab' => 'history']) }}" @class(['rounded-md px-3 py-1.5 text-sm font-semibold', 'bg-indigo-600 text-white' => $tab === 'history', 'text-slate-400 hover:text-white' => $tab !== 'history'])>
                    Historial
                </a>
            </div>
            <p class="text-xs text-slate-500">Los supervisores envían la ubicación desde el puesto. Al aprobar, la instalación usa ese punto para medir las revistas.</p>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
            @if ($rows->isEmpty())
                <div class="px-6 py-14 text-center">
                    <p class="text-base font-semibold text-white">{{ $tab === 'pending' ? 'No hay solicitudes pendientes' : 'Aún no hay solicitudes revisadas' }}</p>
                    <p class="mt-1 text-sm text-slate-500">Llegan desde la app de Supervisión, módulo «Georreferencia».</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-800 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Cliente · instalación</th>
                                <th class="px-4 py-3">Supervisor</th>
                                <th class="px-4 py-3">Enviada</th>
                                <th class="px-4 py-3 text-right">Distancia al actual</th>
                                <th class="px-4 py-3 text-right">Precisión</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach ($rows as $row)
                                <tr class="hover:bg-slate-800/40">
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-white">{{ $row->installation?->name }}</p>
                                        <p class="text-xs text-slate-500">{{ $row->client?->name }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-slate-300">{{ $row->requester?->name }}</td>
                                    <td class="px-4 py-3 tabular-nums text-slate-400">{{ $row->created_at?->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums {{ ($row->distance_m ?? 0) > 300 ? 'font-semibold text-amber-300' : 'text-slate-300' }}">{{ $distanceLabel($row->distance_m) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-slate-400">±{{ $row->accuracy_m }} m</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ring-1 {{ $row->status->badge() }}">{{ $row->status->label() }}</span>
                                        @if ($row->reviewer)
                                            <p class="mt-1 text-xs text-slate-500">{{ $row->reviewer->name }} · {{ $row->reviewed_at?->format('d/m H:i') }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button type="button" @click="open({{ $row->id }})" class="inline-flex h-8 items-center rounded-lg bg-indigo-600 px-3 text-xs font-semibold text-white hover:bg-indigo-500">
                                            {{ $row->status === \App\Enums\InstallationGeoRequestStatus::Pending ? 'Revisar' : 'Ver' }}
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        <div>{{ $rows->links() }}</div>

        <template x-teleport="body">
            <div x-show="current" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-10" @keydown.escape.window="close()">
                <div class="absolute inset-0 bg-black/50" @click="close()"></div>
                <div class="relative w-full max-w-5xl space-y-4 rounded-2xl border border-slate-700 bg-slate-900 p-5 shadow-2xl" x-show="current">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-widest text-amber-400">Solicitud de georreferencia</p>
                            <h3 class="mt-1 text-lg font-semibold text-white" x-text="current?.installation"></h3>
                            <p class="text-sm text-slate-400"><span x-text="current?.client"></span> · enviada por <span class="text-slate-200" x-text="current?.supervisor"></span> el <span x-text="current?.requested_at"></span></p>
                        </div>
                        <button type="button" @click="close()" class="text-sm text-slate-400 hover:text-white">Cerrar</button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-lg bg-slate-950 px-3 py-2">
                            <p class="text-[11px] uppercase tracking-wider text-slate-500">Distancia entre puntos</p>
                            <p class="text-base font-semibold text-white" x-text="distanceText()"></p>
                        </div>
                        <div class="rounded-lg bg-slate-950 px-3 py-2">
                            <p class="text-[11px] uppercase tracking-wider text-slate-500">Precisión del GPS</p>
                            <p class="text-base font-semibold text-white" x-text="current ? '±' + current.accuracy + ' m' : ''"></p>
                        </div>
                        <div class="rounded-lg bg-slate-950 px-3 py-2">
                            <p class="text-[11px] uppercase tracking-wider text-slate-500">Estado</p>
                            <p class="text-base font-semibold text-white" x-text="current?.status"></p>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <p class="mb-1.5 flex items-center gap-2 text-sm font-semibold text-slate-200"><span class="h-3 w-3 rounded-full bg-[#64748b]"></span>Ubicación actual</p>
                            <div id="geo-map-current" class="h-72 rounded-xl border border-slate-700 bg-slate-950"></div>
                            <p class="mt-1 font-mono text-xs text-slate-500" x-text="current?.previous ? coords(current.previous) : 'La instalación no tenía ubicación.'"></p>
                        </div>
                        <div>
                            <p class="mb-1.5 flex items-center gap-2 text-sm font-semibold text-slate-200"><span class="h-3 w-3 rounded-full bg-[#0f766e]"></span>Ubicación solicitada</p>
                            <div id="geo-map-requested" class="h-72 rounded-xl border border-slate-700 bg-slate-950"></div>
                            <p class="mt-1 font-mono text-xs text-slate-500" x-text="current ? coords(current.requested) : ''"></p>
                        </div>
                    </div>
                    <p x-show="!mapsKey" class="text-xs text-amber-300">No hay clave de Google Maps configurada; se muestran solo las coordenadas.</p>

                    <template x-if="current && !current.pending">
                        <p class="rounded-lg bg-slate-950 px-3 py-2 text-sm text-slate-300">
                            <span x-text="current.status"></span><span x-show="current.reviewer"> por <span x-text="current.reviewer"></span> el <span x-text="current.reviewed_at"></span></span><span x-show="current.note">. Motivo: <span x-text="current.note"></span></span>
                        </p>
                    </template>

                    <template x-if="current && current.pending">
                        <div class="space-y-3 border-t border-slate-800 pt-4">
                            <div x-show="rejecting">
                                <label for="geo-reject-note" class="mb-1 block text-xs text-slate-400">Motivo del rechazo (opcional)</label>
                                <input id="geo-reject-note" x-model="note" maxlength="500" class="h-10 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 text-sm text-white" placeholder="Ej.: el punto quedó en la vía, repetir dentro de la portería">
                            </div>
                            <p x-show="error" class="text-sm text-red-400" x-text="error"></p>
                            <div class="flex flex-wrap justify-end gap-2">
                                <button type="button" x-show="!rejecting" @click="rejecting = true" :disabled="busy" class="h-10 rounded-lg border border-slate-700 px-4 text-sm font-semibold text-slate-200 hover:bg-slate-800">Rechazar</button>
                                <button type="button" x-show="rejecting" @click="rejecting = false" :disabled="busy" class="h-10 rounded-lg px-4 text-sm font-semibold text-slate-400 hover:text-white">Volver</button>
                                <button type="button" x-show="rejecting" @click="resolve(false)" :disabled="busy" class="h-10 rounded-lg bg-red-600 px-4 text-sm font-semibold text-white hover:bg-red-500">Confirmar rechazo</button>
                                <button type="button" x-show="!rejecting" @click="resolve(true)" :disabled="busy" class="h-10 rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-500">Aprobar nueva ubicación</button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    @push('scripts')
        <script>
            function geoRequests(cfg) {
                return {
                    rows: cfg.rows || [],
                    mapsKey: cfg.mapsKey || '',
                    csrf: cfg.csrf,
                    current: null,
                    rejecting: false,
                    note: '',
                    busy: false,
                    error: '',
                    init() {
                        if (cfg.openId && this.rows.some((r) => r.id === Number(cfg.openId))) {
                            this.open(Number(cfg.openId));
                        }
                    },
                    open(id) {
                        this.current = this.rows.find((r) => r.id === id) || null;
                        this.rejecting = false;
                        this.note = '';
                        this.error = '';
                        if (this.current) this.$nextTick(() => this.drawMaps());
                    },
                    close() {
                        this.current = null;
                    },
                    coords(p) {
                        return p.lat.toFixed(6) + ', ' + p.lng.toFixed(6);
                    },
                    distanceText() {
                        const d = this.current?.distance;
                        if (d === null || d === undefined) return 'Sin punto previo';
                        return d >= 1000 ? (d / 1000).toFixed(1).replace('.', ',') + ' km' : d + ' m';
                    },
                    loadMaps() {
                        if (window.google?.maps) return Promise.resolve(true);
                        if (!this.mapsKey) return Promise.resolve(false);
                        if (!window.__geoMapsPromise) {
                            window.__geoMapsPromise = new Promise((resolve) => {
                                window.__geoMapsReady = () => resolve(true);
                                const s = document.createElement('script');
                                s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(this.mapsKey) + '&callback=__geoMapsReady';
                                s.async = true;
                                s.onerror = () => resolve(false);
                                document.head.appendChild(s);
                            });
                        }
                        return window.__geoMapsPromise;
                    },
                    async drawMaps() {
                        const row = this.current;
                        if (!(await this.loadMaps()) || !row) return;
                        const pin = (color, opacity) => ({
                            path: google.maps.SymbolPath.CIRCLE,
                            scale: 9,
                            fillColor: color,
                            fillOpacity: opacity,
                            strokeColor: '#ffffff',
                            strokeWeight: 2,
                            strokeOpacity: opacity,
                        });
                        const draw = (elId, main, mainColor, other, otherColor) => {
                            const el = document.getElementById(elId);
                            if (!el) return;
                            const center = main || other;
                            const map = new google.maps.Map(el, {
                                center,
                                zoom: 18,
                                mapTypeId: google.maps.MapTypeId.HYBRID,
                                streetViewControl: false,
                                mapTypeControl: false,
                            });
                            if (other) new google.maps.Marker({ position: other, map, icon: pin(otherColor, 0.45) });
                            if (main) new google.maps.Marker({ position: main, map, icon: pin(mainColor, 1) });
                            if (main && other) {
                                const bounds = new google.maps.LatLngBounds();
                                bounds.extend(main);
                                bounds.extend(other);
                                map.fitBounds(bounds, 60);
                                google.maps.event.addListenerOnce(map, 'idle', () => {
                                    if (map.getZoom() > 19) map.setZoom(19);
                                });
                            }
                        };
                        draw('geo-map-current', row.previous, '#64748b', row.requested, '#0f766e');
                        draw('geo-map-requested', row.requested, '#0f766e', row.previous, '#64748b');
                    },
                    async resolve(approve) {
                        const row = this.current;
                        if (!row || this.busy) return;
                        this.busy = true;
                        this.error = '';
                        const body = new FormData();
                        body.append('_token', this.csrf);
                        if (!approve && this.note.trim() !== '') body.append('note', this.note.trim());
                        try {
                            const res = await fetch(approve ? row.approve_url : row.reject_url, {
                                method: 'POST',
                                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                body,
                                credentials: 'same-origin',
                            });
                            const data = await res.json().catch(() => ({}));
                            if (!res.ok && res.status !== 409) {
                                this.error = data.message || 'No se pudo completar.';
                                this.busy = false;
                                return;
                            }
                            window.location.href = @js(route('company.supervision.geo.index'));
                        } catch (_) {
                            this.error = 'Sin conexión. Intenta de nuevo.';
                            this.busy = false;
                        }
                    },
                };
            }
        </script>
    @endpush
</x-company-layout>
