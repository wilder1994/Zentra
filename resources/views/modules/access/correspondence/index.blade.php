<x-access-layout title="Correspondencia">
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-700 bg-red-900/40 px-4 py-3 text-sm text-red-200">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <div class="mb-4 flex flex-wrap items-center gap-2">
        @foreach(['pendientes' => 'Pendientes ('.$pendingTotal.')', 'entregados' => 'Entregados', 'anulados' => 'Anulados'] as $key => $label)
            <a href="{{ route('access.correspondence.index', ['tab' => $key]) }}"
               class="rounded-lg px-4 py-2 text-sm font-semibold {{ $tab === $key ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-300' }}">{{ $label }}</a>
        @endforeach
        <a href="{{ route('access.correspondence.create') }}" class="ml-auto rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">+ Registrar paquete</a>
    </div>

    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <label class="min-w-[14rem] flex-1">
            <span class="block text-xs text-slate-500">Buscar</span>
            <input type="search" name="q" value="{{ $q }}" placeholder="Unidad, persona, guía o transportadora"
                   class="mt-1 h-9 w-full rounded-lg border-slate-700 bg-slate-950 text-sm text-white">
        </label>
        @if($tab !== 'pendientes')
            @include('modules.company.supervision.partials.period-picker', ['from' => $from, 'to' => $to])
        @endif
        <label class="w-40">
            <span class="block text-xs text-slate-500">Tipo</span>
            <select name="type" class="mt-1 h-9 w-full rounded-lg border-slate-700 bg-slate-950 py-0 text-sm text-white">
                <option value="">Todos</option>
                @foreach(\App\Models\Correspondence::TYPES as $value => $label)
                    <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <button class="h-9 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white">Filtrar</button>
    </form>

    <div x-data="correspondenceDelivery(@js(route('access.correspondence.members')))">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @forelse($items as $item)
                @php
                    $photo = $item->photoUrl();
                    $unit = $item->unitLabel();
                    $unitPending = $item->structure_id ? ($pendingByUnit[$item->structure_id] ?? 0) : 0;
                    $title = $unit !== '—' ? $unit : $item->recipientLabel();
                @endphp
                <article class="flex overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
                    <div class="flex w-24 shrink-0 items-center justify-center bg-slate-950 sm:w-28">
                        @if($photo)
                            <img src="{{ $photo }}" alt="" class="h-full w-full object-cover">
                        @else
                            <svg class="h-10 w-10 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5v-9Z"/><path d="M3 7.5 12 12l9-4.5M12 12v9"/>
                            </svg>
                        @endif
                    </div>
                    <div class="w-px shrink-0 bg-gradient-to-b from-transparent via-indigo-500/70 to-transparent"></div>
                    <div class="flex min-w-0 flex-1 flex-col p-3">
                        <div class="flex items-start justify-between gap-1">
                            <p class="truncate text-sm font-semibold text-white" title="{{ $title }}">{{ $title }}</p>
                            <span class="shrink-0 rounded-full bg-slate-700/40 px-1.5 py-0.5 text-[10px] font-semibold text-slate-300">{{ $item->typeLabel() }}</span>
                        </div>
                        @if($unit !== '—')
                            <p class="truncate text-[11px] text-slate-400" title="{{ $item->recipientLabel() }}">Para: {{ $item->recipientLabel() }}</p>
                        @endif
                        <dl class="mt-2 space-y-0.5 text-[11px] leading-snug">
                            <div class="flex gap-1"><dt class="shrink-0 text-slate-500">Llegó</dt><dd class="truncate text-slate-200">{{ $item->received_at?->format('d/m H:i') }} · {{ $item->received_at?->diffForHumans() }}</dd></div>
                            <div class="flex gap-1"><dt class="shrink-0 text-slate-500">Envío</dt><dd class="truncate text-slate-200">{{ $item->carrier ?? '—' }}{{ $item->courier_guide ? ' · '.$item->courier_guide : '' }}</dd></div>
                            <div class="flex gap-1"><dt class="shrink-0 text-slate-500">Recibió</dt><dd class="truncate text-slate-200">{{ $item->receiver?->name ?? '—' }}</dd></div>
                            @if($item->status === 'delivered')
                                <div class="flex gap-1"><dt class="shrink-0 text-slate-500">Entregado</dt><dd class="truncate text-emerald-300">{{ $item->delivered_at?->format('d/m H:i') }} · {{ $item->deliveredToLabel() }}</dd></div>
                            @elseif($item->status === 'voided')
                                <div class="flex gap-1"><dt class="shrink-0 text-slate-500">Anulado</dt><dd class="truncate text-red-300" title="{{ $item->void_reason }}">{{ $item->void_reason }}</dd></div>
                            @endif
                        </dl>
                        <div class="mt-auto flex flex-wrap gap-1.5 pt-2">
                            <a href="{{ route('access.correspondence.show', $item) }}" class="rounded-md border border-slate-700 px-2 py-1 text-[11px] font-semibold text-slate-200 hover:bg-slate-800">Ver</a>
                            @if($item->status === 'pending')
                                <button type="button" class="rounded-md bg-emerald-600 px-2 py-1 text-[11px] font-semibold text-white"
                                        @click="openSingle(@js(route('access.correspondence.deliver', $item)), @js($item->structure_id), @js($title))">Entregar</button>
                                @if($unitPending > 1)
                                    <button type="button" class="rounded-md border border-emerald-600 px-2 py-1 text-[11px] font-semibold text-emerald-400"
                                            @click="openUnit(@js($item->structure_id), @js($unit), {{ $unitPending }})">Todo ({{ $unitPending }})</button>
                                @endif
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <p class="col-span-full rounded-xl border border-slate-800 bg-slate-900 px-4 py-10 text-center text-sm text-slate-500">
                    {{ $tab === 'pendientes' ? 'No hay paquetes pendientes por entregar.' : 'Sin registros en el periodo.' }}
                </p>
            @endforelse
        </div>

        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="open = false">
            <div class="absolute inset-0 bg-slate-950/70" @click="open = false"></div>
            <form method="POST" :action="mode === 'unit' ? @js(route('access.correspondence.deliver-unit')) : action"
                  class="porteria-form relative w-full max-w-md space-y-4 rounded-xl border border-slate-700 bg-slate-900 p-5 shadow-2xl">
                @csrf
                <template x-if="mode === 'single'"><input type="hidden" name="_method" value="PATCH"></template>
                <template x-if="mode === 'unit'"><input type="hidden" name="structure_id" :value="structureId"></template>
                <div>
                    <h3 class="text-base font-semibold text-white" x-text="mode === 'unit' ? 'Entregar ' + count + ' paquetes · ' + unit : 'Entregar paquete · ' + unit"></h3>
                    <p class="mt-1 text-xs text-slate-400">Registra quién lo recibe en portería.</p>
                </div>
                <template x-if="members.length">
                    <label class="block text-xs text-slate-400">Persona de la unidad
                        <select name="delivered_to_member_id" x-model="memberId" class="mt-1 w-full rounded-lg border-slate-700 bg-slate-950 text-sm text-white">
                            <option value="">— Otra persona (escribir abajo) —</option>
                            <template x-for="m in members" :key="m.id">
                                <option :value="String(m.id)" x-text="m.name"></option>
                            </template>
                        </select>
                    </label>
                </template>
                <template x-if="!memberId">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="block text-xs text-slate-400 sm:col-span-2">Nombre de quien recibe <span class="text-amber-400">*</span>
                            <input name="delivered_to_name" required maxlength="150" class="mt-1 w-full rounded-lg border-slate-700 bg-slate-950 text-sm text-white">
                        </label>
                        <label class="block text-xs text-slate-400 sm:col-span-2">Documento (opcional)
                            <input name="delivered_to_document" maxlength="50" class="mt-1 w-full rounded-lg border-slate-700 bg-slate-950 text-sm text-white">
                        </label>
                    </div>
                </template>
                @include('modules.access.partials.camera-capture', [
                    'name' => 'delivery_photo_data',
                    'label' => 'Foto de la entrega',
                    'required' => false,
                    'hint' => 'Opcional',
                    'facing' => 'environment',
                ])
                <div class="flex justify-end gap-2">
                    <button type="button" @click="open = false" class="rounded-lg bg-slate-800 px-4 py-2 text-sm text-slate-200">Cancelar</button>
                    <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Confirmar entrega</button>
                </div>
            </form>
        </div>
    </div>

    <div class="mt-4">{{ $items->links() }}</div>

    @push('scripts')
        <script>
            function correspondenceDelivery(membersUrl) {
                return {
                    open: false, mode: 'single', action: '', structureId: null, unit: '', count: 1, members: [], memberId: '',
                    openSingle(action, structureId, unit) {
                        this.mode = 'single'; this.action = action; this.unit = unit; this.count = 1;
                        this.load(structureId);
                    },
                    openUnit(structureId, unit, count) {
                        this.mode = 'unit'; this.unit = unit; this.count = count;
                        this.load(structureId);
                    },
                    load(structureId) {
                        this.structureId = structureId; this.members = []; this.memberId = ''; this.open = true;
                        if (!structureId) return;
                        fetch(membersUrl + '?structure_id=' + structureId, { headers: { Accept: 'application/json' } })
                            .then(r => r.json())
                            .then(d => { this.members = d.members || []; });
                    },
                };
            }
        </script>
    @endpush
</x-access-layout>
