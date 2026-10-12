<x-client-layout title="Nuevo reporte">
    <a href="{{ route('client.observatory.events.index', ['vista' => 'eventos']) }}" class="text-sm text-slate-400 hover:text-white">← Observatorio</a>
    <h1 class="mt-3 text-xl font-semibold text-white">Nuevo reporte</h1>

    @if (! ($canReport ?? false))
        <div class="mt-6 max-w-xl rounded-xl border border-amber-800/70 bg-amber-950/40 p-5 space-y-2">
            <p class="text-sm text-amber-100">
                Con el usuario <span class="font-semibold text-white">{{ $reporterName ?? 'actual' }}</span> no puedes realizar el reporte.
            </p>
            <p class="text-sm text-amber-200/80">
                Solo el administrador de la instalación (rector) o el apoyo pueden reportar desde el panel.
            </p>
        </div>
    @else
        <p class="mt-1 text-sm text-slate-400">Se registra como {{ $roleLabel }} desde el panel. Puedes enviarlo anónimo.</p>

        @if ($errors->any())
            <div class="mt-4 rounded-lg border border-red-800 bg-red-950/40 px-3 py-2 text-sm text-red-200">
                <ul class="list-disc pl-4 space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('client.observatory.reports.store') }}" enctype="multipart/form-data" class="mt-6 max-w-xl space-y-4" x-data="observatoryPhotos({ anonymous: {{ old('is_anonymous') ? 'true' : 'false' }} })">
            @csrf
            <div>
                <label class="block text-xs text-slate-400 mb-1" for="installation_id">Instalación</label>
                <select id="installation_id" name="installation_id" required class="w-full h-11 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
                    <option value="">Seleccione…</option>
                    @foreach ($sites as $site)
                        <option value="{{ $site->id }}" @selected((string) old('installation_id') === (string) $site->id)>{{ $site->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1" for="kind">Tipo</label>
                <select id="kind" name="kind" required class="w-full h-11 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
                    <option value="">Seleccione…</option>
                    @foreach ($kinds as $value => $label)
                        <option value="{{ $value }}" @selected(old('kind') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1" for="body">Qué pasó</label>
                <textarea id="body" name="body" rows="5" required minlength="10" maxlength="2000" class="w-full px-3 py-2 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">{{ old('body') }}</textarea>
            </div>
            @include('modules.observatory.partials.photo-slots')
            <label class="flex items-start gap-2 text-sm text-slate-300">
                <input type="hidden" name="is_anonymous" value="0">
                <input type="checkbox" name="is_anonymous" value="1" x-model="anonymous" @checked(old('is_anonymous')) class="mt-0.5 rounded border-slate-600 bg-slate-950 text-teal-600">
                <span>Enviar en anónimo</span>
            </label>
            <div x-show="anonymous" x-cloak class="rounded-lg border border-amber-800 bg-amber-950/40 px-3 py-2 text-sm text-amber-100">
                En este reporte se oculta tu nombre y teléfono. Solo se muestra la denuncia.
            </div>
            <button type="submit" class="h-11 px-4 rounded-lg bg-teal-600 text-sm font-semibold text-white">Enviar reporte</button>
        </form>
    @endif
</x-client-layout>
