<div x-show="help" x-cloak x-transition.opacity.duration.200ms
     @click.outside="if (! $refs.helpBtn.contains($event.target)) help = false"
     class="absolute inset-x-3 top-3 z-20 rounded-lg border border-slate-700/70 bg-slate-950/85 p-3 text-xs text-slate-200 shadow-xl backdrop-blur-sm">
    <div class="flex items-start justify-between gap-2">
        <p class="font-semibold text-white">Ayuda</p>
        <button type="button" class="leading-none text-slate-400 hover:text-white" @click="help = false" aria-label="Cerrar ayuda">×</button>
    </div>
    <ul class="mt-1.5 list-disc space-y-1 pl-4 text-slate-300">
        @foreach ($tips as $tip)
            <li>{{ $tip }}</li>
        @endforeach
    </ul>
</div>
