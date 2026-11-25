@php
    $cards = [
        ['seccion' => 'motor', 'kicker' => '01', 'title' => 'Panel de configuración del motor de riesgos', 'text' => 'Pesos, vida media y semáforo. Escala 0–100.', 'icon' => 'sliders', 'tint' => 'bg-indigo-500 text-white shadow-indigo-500/40'],
        ['seccion' => 'catalogo', 'kicker' => '02', 'title' => 'Catálogo y matriz de delitos y reportes', 'text' => 'Riesgo de la comuna y riesgo de la sede, en matriz 10×10.', 'icon' => 'grid', 'tint' => 'bg-amber-500 text-white shadow-amber-500/40'],
        ['seccion' => 'comunas', 'kicker' => '03', 'title' => 'Delitos por comuna', 'text' => 'Casos del Observatorio de Seguridad de Cali, por año.', 'icon' => 'map', 'tint' => 'bg-teal-500 text-white shadow-teal-500/40'],
        ['seccion' => 'log', 'kicker' => '04', 'title' => 'Log de reportes por instalación', 'text' => 'Cada reporte, con su cruce, decaimiento y aporte.', 'icon' => 'list', 'tint' => 'bg-sky-500 text-white shadow-sky-500/40'],
    ];
@endphp
<div class="mx-auto max-w-5xl">
    <h2 class="text-center text-sm font-semibold uppercase tracking-[0.18em] text-slate-300">Parametrización de riesgos</h2>
    <div class="mt-8 grid gap-4 md:grid-cols-2">
        @foreach ($cards as $card)
            <a href="{{ route('company.observatory.types.index', ['seccion' => $card['seccion']]) }}"
               class="group flex items-start gap-4 rounded-2xl border border-slate-800 bg-gradient-to-br from-slate-900 to-slate-950 p-5 shadow-lg shadow-black/20 transition duration-300 hover:-translate-y-0.5 hover:border-indigo-500/40">
                <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl shadow-lg {{ $card['tint'] }}">
                    @if ($card['icon'] === 'sliders')
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M4 7a2 2 0 1 1 4 0 2 2 0 0 1-4 0Zm6-1.25h10v2.5H10V5.75ZM14 12a2 2 0 1 1 4 0 2 2 0 0 1-4 0ZM4 10.75h8v2.5H4v-2.5ZM8 17a2 2 0 1 1 4 0 2 2 0 0 1-4 0Zm6-1.25h6v2.5h-6v-2.5Z"/></svg>
                    @elseif ($card['icon'] === 'grid')
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm9 0h7v7h-7v-7Z"/></svg>
                    @elseif ($card['icon'] === 'map')
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M9.2 3.4 4 5.2v15.2l5.2-1.8 5.6 1.8L20 18.6V3.4l-5.2 1.8-5.6-1.8Zm.6 2.1v12.2l-4-1.4V4.1l4 1.4Zm1.6.6 4 1.3v12.2l-4-1.3V6.1Z"/></svg>
                    @else
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.25h12v2.5H8v-2.5Zm0 5.5h12v2.5H8v-2.5Zm0 5.5h12v2.5H8v-2.5ZM4 6.5a1.25 1.25 0 1 0 0-2.5 1.25 1.25 0 0 0 0 2.5Zm0 5.5a1.25 1.25 0 1 0 0-2.5 1.25 1.25 0 0 0 0 2.5Zm0 5.5a1.25 1.25 0 1 0 0-2.5 1.25 1.25 0 0 0 0 2.5Z"/></svg>
                    @endif
                </span>
                <span class="min-w-0">
                    <span class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400">{{ $card['kicker'] }}</span>
                    <span class="mt-1 block text-base font-semibold text-white">{{ $card['title'] }}</span>
                    <span class="mt-1 block text-sm leading-relaxed text-slate-400">{{ $card['text'] }}</span>
                </span>
            </a>
        @endforeach
    </div>
</div>
