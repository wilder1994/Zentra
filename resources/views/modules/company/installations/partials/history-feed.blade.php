<div class="space-y-3">
    <p class="text-xs text-slate-500">Historial de «{{ $installation->name }}»</p>
    @forelse ($entries as $entry)
        <article class="rounded-lg border border-slate-800 bg-slate-950/50 px-3 py-2">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <p class="text-xs font-semibold text-slate-200">{{ $entry->title }}</p>
                <time class="text-[10px] text-slate-500 tabular-nums">{{ $entry->created_at?->format('d/m/Y H:i') }}</time>
            </div>
            <p class="mt-1 text-xs leading-relaxed text-slate-400">{{ $entry->body }}</p>
            @if ($entry->actor)
                <p class="mt-1 text-[10px] text-slate-600">{{ $entry->actor->name }}</p>
            @endif
        </article>
    @empty
        <p class="py-8 text-center text-sm text-slate-500">Sin eventos registrados aún.</p>
    @endforelse
</div>
