<x-observatory-layout title="Reporte recibido">
    <p class="text-xs uppercase tracking-wider text-slate-500">Observatorio</p>
    <h1 class="text-xl font-semibold text-white mt-1">Recibido</h1>
    <p class="text-sm text-slate-400 mt-2">Gracias. El reporte quedó ligado a {{ $report->event?->installation?->name ?? 'la instalación' }}.</p>
    <p class="mt-4 font-mono text-lg text-teal-300">{{ $report->event?->folio() }}</p>
    <a href="{{ route('observatory.public.show', $client->slug) }}" class="mt-6 inline-flex h-11 items-center rounded-lg bg-teal-600 px-4 text-sm font-semibold text-white">
        Enviar otro
    </a>
</x-observatory-layout>
