@php
    $fmt = fn (float $n) => '$'.number_format($n, 0, ',', '.');
@endphp

@extends('layouts.public')

@section('content')
    @include('modules.public.signup._steps', ['step' => 3])

    <div class="max-w-xl space-y-4">
        <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-4 space-y-2 text-sm">
            @if ((int) $intent->package_manual_seats + (int) $intent->package_hardware_seats > 0)
                <div class="flex justify-between gap-3"><span class="text-slate-500">Accesos</span><span class="text-white text-right">{{ (new \App\Domain\Pricing\Data\AccessSeatSplit((int) $intent->package_manual_seats, (int) $intent->package_hardware_seats))->label() }}</span></div>
            @endif
            @if ($intent->supervision_package_sku)
                <div class="flex justify-between gap-3"><span class="text-slate-500">Supervisión</span><span class="text-amber-200 text-right">{{ $intent->supervision_package_sku->label() }}</span></div>
            @endif
            <div class="flex justify-between"><span class="text-slate-500">Ciclo</span><span>{{ $intent->billing_cycle->label() }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Empresa</span><span>{{ $intent->legal_name }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Email</span><span>{{ $intent->email }}</span></div>
            <div class="flex justify-between border-t border-slate-800 pt-2 mt-2">
                <span class="text-slate-300 font-medium">Total a pagar</span>
                <span class="text-xl font-bold text-white tabular-nums">{{ $fmt((float) $intent->amount) }}</span>
            </div>
        </div>

        <p class="text-xs text-slate-500">
            Tu cuenta se creará solo si el pago se aprueba. Si rechazas o falla, no quedará registro en el sistema.
        </p>

        <form method="POST" action="{{ route('signup.pay', $intent) }}">
            @csrf
            <x-ui.button type="submit" class="w-full">Ir a pagar (checkout simulado)</x-ui.button>
        </form>
    </div>
@endsection
