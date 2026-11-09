<x-client-layout :title="$member->full_name">
    <div class="space-y-6 max-w-3xl">
        <div>
            <a href="{{ route('client.members.index') }}" class="text-sm text-teal-400 hover:text-teal-300">← Directorio</a>
            <h2 class="text-2xl font-bold text-white mt-2">{{ $member->full_name }}</h2>
            <p class="text-sm text-slate-400">
                {{ $member->structure?->name ?? '—' }}
                @if ($member->structure?->installation)
                    · {{ $member->structure->installation->name }}
                @endif
                · {{ $member->memberType?->name }}
                @if ($member->isMinor())
                    · Menor de edad
                @endif
            </p>
        </div>

        @if ($member->isMinor())
            <p class="rounded-lg border border-amber-800/70 bg-amber-950/30 px-3 py-2 text-xs text-amber-200 leading-relaxed whitespace-pre-line">{{ \App\Support\Privacy\MinorPersonalData::notice() }}</p>
        @endif

        <div class="flex gap-2 mb-2">
            <a href="{{ route('client.members.edit', $member) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-600 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Editar
            </a>
            @can('client.app_users.manage')
                @if ($member->appUser)
                    <a href="{{ route('client.app-users.show', $member->appUser) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-600 transition-colors">
                        Ver usuario app
                    </a>
                @elseif (! $member->isMinor())
                    <a href="{{ route('client.app-users.create', ['member_id' => $member->id]) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-600 transition-colors">
                        Crear en Usuarios app
                    </a>
                @endif
            @endcan
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-4 space-y-3">
                <h3 class="text-sm font-semibold text-white">Datos de contacto</h3>
                <p class="text-sm text-slate-300">Documento: <span class="font-mono">{{ $member->displayedDocument() }}</span></p>
                @if ($member->revealsPii())
                    <p class="text-sm text-slate-300">Nacimiento: {{ $member->birth_date?->format('d/m/Y') ?? '—' }}{{ $member->birth_date ? ' · '.$member->birth_date->age.' años' : '' }}</p>
                @endif
                <p class="text-sm text-slate-300">Teléfono: {{ $member->displayedContact($member->phone_primary) }}</p>
                <p class="text-sm text-slate-300">Email: {{ $member->displayedContact($member->email) }}</p>
                @if($member->revealsPii() && $member->photo_path)
                <div class="pt-2">
                    <p class="text-xs text-slate-500 mb-1">Foto</p>
                    <img src="{{ Storage::url($member->photo_path) }}" alt="{{ $member->full_name }}" class="w-24 h-24 rounded-lg object-cover border border-slate-700">
                </div>
                @endif
            </div>

            @if ($member->revealsPii() && ! $member->isMinor())
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                <h3 class="text-sm font-semibold text-white mb-3">Código de acceso / QR</h3>
                <p class="font-mono text-lg text-indigo-300 mb-4">{{ $member->access_code }}</p>
                <div id="member-qr" class="bg-white p-3 inline-block rounded-lg" data-code="{{ $member->access_code }}"></div>
                <p class="text-xs text-slate-500 mt-3">Escaneable en portería para validar identidad.</p>
            </div>
            @endif
        </div>
    </div>

    @if ($member->revealsPii() && ! $member->isMinor())
    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script>
        const el = document.getElementById('member-qr');
        if (el && window.QRCode) {
            QRCode.toCanvas(document.createElement('canvas'), el.dataset.code, { width: 160 }, (err, canvas) => {
                if (!err) el.appendChild(canvas);
            });
        }
    </script>
    @endpush
    @endif
</x-client-layout>
