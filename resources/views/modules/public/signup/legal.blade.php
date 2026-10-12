@extends('layouts.public')

@section('content')
    @include('modules.public.signup._steps', ['step' => 2])

    <div class="max-w-xl space-y-4">
        <p class="text-sm text-slate-400">
            Representante legal y aceptación del corpus vigente
            @if (! $intent->planSelection()->isEmpty())
                para el plan <span class="text-slate-200">{{ $intent->packageLabel() }}</span>.
            @endif
        </p>

        <form method="POST" action="{{ route('signup.legal.store', $intent) }}" class="space-y-4 rounded-xl border border-slate-800 bg-slate-900/80 p-4">
            @csrf

            @include('partials.corpus-accept-docs', ['corpus' => $corpus])

            <div>
                <x-ui.label for="representative_name">Nombre representante</x-ui.label>
                <x-ui.input id="representative_name" name="representative_name" value="{{ old('representative_name', $intent->representative_name) }}" required />
                <x-ui.field-error name="representative_name" />
            </div>
            <div>
                <x-ui.label for="representative_role">Cargo</x-ui.label>
                <x-ui.input id="representative_role" name="representative_role" value="{{ old('representative_role', $intent->representative_role) }}" required />
                <x-ui.field-error name="representative_role" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <x-ui.label for="representative_document_type">Tipo documento</x-ui.label>
                    <select id="representative_document_type" name="representative_document_type" required
                            class="mt-1 block w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Seleccione…</option>
                        @foreach ($documentTypes as $code => $label)
                            <option value="{{ $code }}" @selected(old('representative_document_type', $intent->representative_document_type) === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-ui.field-error name="representative_document_type" />
                </div>
                <div>
                    <x-ui.label for="representative_document_number">Número</x-ui.label>
                    <x-ui.input id="representative_document_number" name="representative_document_number" value="{{ old('representative_document_number', $intent->representative_document_number) }}" required />
                    <x-ui.field-error name="representative_document_number" />
                </div>
            </div>
            <x-ui.button type="submit" class="w-full">Continuar → Resumen</x-ui.button>
        </form>
    </div>
@endsection
