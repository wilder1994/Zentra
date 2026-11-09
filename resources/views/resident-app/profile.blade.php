<x-layouts.resident-app title="Perfil">
    <div class="space-y-3 text-sm">
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4 space-y-2">
            <p class="text-xs text-slate-500">Login</p>
            <p class="font-mono text-teal-300 break-all">{{ $appUser?->login_email }}</p>
            <p class="text-xs text-slate-500 pt-2">Canal</p>
            <p class="text-slate-200">{{ $appUser?->channelLabel() }}</p>
            <p class="text-xs text-slate-500 pt-2">Unidad</p>
            <p class="text-slate-200">{{ $appUser?->member?->structure?->full_path ?? '—' }}</p>
            <p class="text-xs text-slate-500">{{ $appUser?->member?->structure?->installation?->name }}</p>
        </div>
    </div>
</x-layouts.resident-app>
