<x-client-layout title="Nueva persona">
    <div class="max-w-2xl">
        <form action="{{ route('client.members.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4 rounded-xl border border-slate-800 bg-slate-900 p-6">
            @csrf
            @if ($errors->any())
                <div class="rounded-lg border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-200">
                    <p class="font-semibold">No se pudo guardar. Revisa:</p>
                    <ul class="mt-1 list-disc pl-5 space-y-0.5">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif
            <x-client.member-photo-picker />
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Nombres</label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" required class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Apellidos</label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" required class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
                </div>
            </div>
            @include('modules.client.members.partials.identity-fields', ['documentTypes' => $documentTypes])
            <x-client.census-node-picker
                :installations="$installations"
                :node-options="$nodeOptions"
                :installation-id="$installationId"
                :structure-id="$structureId"
            />
            @include('modules.client.members.partials.type-select', ['selected' => old('member_type_id')])
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Teléfono</label>
                    <input type="text" name="phone_primary" value="{{ old('phone_primary') }}" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
                </div>
            </div>
            <button type="submit" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-500 disabled:opacity-50">Guardar y generar código de acceso</button>
        </form>
    </div>
</x-client-layout>
