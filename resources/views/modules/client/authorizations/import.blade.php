<x-client-layout title="Importar autorizaciones">
    <x-slot:actions>
        <a href="{{ route('client.authorizations.index') }}" class="rounded-lg bg-slate-800 px-3 py-2 text-sm text-white hover:bg-slate-700">← Volver</a>
    </x-slot:actions>
    <div class="max-w-xl space-y-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5 text-sm text-slate-400">
            <p class="font-semibold text-white">Una fila por persona autorizada</p>
            <p class="mt-2">Obligatorias: <span class="font-mono text-slate-300">visitante, unidad, fecha</span>.</p>
            <p class="mt-1">Opcionales: <span class="font-mono text-slate-300">documento, telefono, hasta, tipo, anfitrion_documento, notas</span>.</p>
            <p class="mt-2 text-xs">Tipo: visita, trasteo, evento, entrevista, obra u otro. Sin «hasta», vale solo el día indicado.</p>
        </div>
        <form action="{{ route('client.authorizations.import.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4 rounded-2xl border border-slate-800 bg-slate-900 p-5">
            @csrf
            <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="block w-full text-sm text-slate-300 file:mr-4 file:rounded-lg file:border-0 file:bg-teal-600 file:px-4 file:py-2 file:text-white">
            <button type="submit" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-500">Importar</button>
        </form>
    </div>
</x-client-layout>
