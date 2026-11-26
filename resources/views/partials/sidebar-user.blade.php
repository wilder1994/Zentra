@php
    $userName = Auth::user()?->name ?? '';
@endphp
<div class="px-4 py-3 border-t border-slate-800 shrink-0 min-w-0">
    <p class="text-xs text-slate-400 truncate" title="{{ $userName }}">{{ $userName }}</p>
    <div class="relative mt-1" x-data>
        <button type="button" class="flex w-full items-center justify-between text-xs text-slate-300 hover:text-white"
                @click="$store.opsNotices.open = !$store.opsNotices.open">
            <span>Notificaciones</span>
            <span class="tabular-nums" x-text="$store.opsNotices.items.length"></span>
        </button>
        <div x-show="$store.opsNotices.open" x-cloak class="absolute bottom-full left-0 right-0 z-30 mb-1 max-h-60 overflow-auto rounded-lg border border-slate-700 bg-slate-950 p-2 text-xs text-slate-200 shadow-lg">
            <template x-if="!$store.opsNotices.items.length">
                <p class="px-1 py-2 text-slate-500">No tienes notificaciones.</p>
            </template>
            <template x-for="row in $store.opsNotices.items" :key="row.id">
                <a class="block rounded-md px-2 py-1.5 hover:bg-slate-800" :href="row.show_url || row.map_url || '#'">
                    <p class="font-semibold text-white" x-text="row.title"></p>
                    <p class="text-slate-400" x-text="row.body"></p>
                </a>
            </template>
        </div>
    </div>
    <form method="POST" action="{{ route('logout') }}" class="mt-1">
        @csrf
        <button type="submit" class="text-xs text-slate-500 hover:text-white transition">
            Cerrar sesión
        </button>
    </form>
</div>
