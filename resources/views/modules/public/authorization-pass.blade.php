<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Autorización de ingreso · {{ $authorization->client?->name }}</title>
    @include('partials.favicon')
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    <main class="mx-auto flex min-h-screen max-w-md flex-col items-center px-5 py-8">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Autorización de ingreso</p>
        <h1 class="mt-1 text-center text-xl font-bold text-white">{{ $authorization->client?->name }}</h1>

        @php
            $badge = match ($state) {
                'active' => 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/40',
                'upcoming' => 'bg-sky-500/15 text-sky-300 ring-sky-500/40',
                default => 'bg-slate-700/40 text-slate-300 ring-slate-600',
            };
        @endphp
        <span class="mt-3 inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $badge }}">{{ $authorization->stateLabel() }}</span>

        @if ($valid)
            <x-authorization-qr :value="route('authorizations.pass', $authorization->qr_auth_token)" size="w-64" class="mt-6" />
            <p class="mt-3 text-center text-sm text-slate-400">Muestra este código en portería junto con tu documento.</p>
        @else
            <div class="mt-6 w-full rounded-2xl border border-slate-800 bg-slate-900 p-5 text-center text-sm text-slate-400">
                Esta autorización ya no está vigente. En portería confirmarán tu ingreso con la unidad.
            </div>
        @endif

        <dl class="mt-6 w-full divide-y divide-slate-800 rounded-2xl border border-slate-800 bg-slate-900 text-sm">
            <div class="flex justify-between gap-4 px-4 py-3"><dt class="text-slate-500">Tipo</dt><dd class="font-medium text-white">{{ $authorization->typeLabel() }}</dd></div>
            <div class="flex justify-between gap-4 px-4 py-3"><dt class="text-slate-500">Destino</dt><dd class="font-medium text-white text-right">{{ $authorization->destinationLabel() }}</dd></div>
            <div class="flex justify-between gap-4 px-4 py-3"><dt class="text-slate-500">Vigencia</dt><dd class="font-medium text-white">{{ $authorization->validityLabel() }}</dd></div>
            <div class="px-4 py-3">
                <dt class="text-slate-500">{{ count($guests) === 1 ? 'Persona' : 'Personas ('.count($guests).')' }}</dt>
                <dd class="mt-1 flex flex-wrap gap-1.5">
                    @foreach (array_slice($guests, 0, 12) as $name)
                        <span class="rounded-full bg-slate-800 px-2.5 py-0.5 text-xs text-slate-200">{{ $name }}</span>
                    @endforeach
                    @if (count($guests) > 12)
                        <span class="rounded-full bg-slate-800 px-2.5 py-0.5 text-xs text-slate-400">+{{ count($guests) - 12 }}</span>
                    @endif
                </dd>
            </div>
        </dl>

        <p class="mt-auto pt-8 text-xs text-slate-600">{{ config('app.name') }}</p>
    </main>
</body>
</html>
