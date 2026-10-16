@props(['defaults'])

@php
    $d = $defaults;
    $ta = 'w-full px-3 py-2 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white placeholder:text-slate-600 focus:border-violet-500 focus:ring-1 focus:ring-violet-500/30';
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <x-ui.label for="date">Fecha</x-ui.label>
        <x-ui.input type="date" id="date" name="date" :value="old('date', $d['date'])" accent="platform" />
        <x-ui.field-error :messages="$errors->get('date')" />
    </div>
    <div>
        <x-ui.label for="city">Ciudad</x-ui.label>
        <x-ui.input id="city" name="city" :value="old('city', $d['city'])" accent="platform" />
        <x-ui.field-error :messages="$errors->get('city')" />
    </div>
</div>

<div>
    <x-ui.label for="directed_to">Dirigido a</x-ui.label>
    <x-ui.input id="directed_to" name="directed_to" :value="old('directed_to', $d['directed_to'])" accent="platform" placeholder="Empresa o contacto" />
    <x-ui.field-error :messages="$errors->get('directed_to')" />
</div>

<div>
    <x-ui.label for="subject">Asunto</x-ui.label>
    <x-ui.input id="subject" name="subject" :value="old('subject', $d['subject'])" accent="platform" />
    <x-ui.field-error :messages="$errors->get('subject')" />
</div>

<div>
    <x-ui.label for="introduction">Introducción</x-ui.label>
    <textarea id="introduction" name="introduction" rows="5" class="{{ $ta }}">{{ old('introduction', $d['introduction']) }}</textarea>
    <x-ui.field-error :messages="$errors->get('introduction')" />
</div>

<div>
    <x-ui.label for="highlight">Lo que destaca</x-ui.label>
    <textarea id="highlight" name="highlight" rows="3" class="{{ $ta }}">{{ old('highlight', $d['highlight']) }}</textarea>
    <x-ui.field-error :messages="$errors->get('highlight')" />
</div>

<div>
    <x-ui.label for="conclusions">Conclusiones</x-ui.label>
    <textarea id="conclusions" name="conclusions" rows="4" class="{{ $ta }}">{{ old('conclusions', $d['conclusions']) }}</textarea>
    <x-ui.field-error :messages="$errors->get('conclusions')" />
</div>

<div class="border-t border-slate-800 pt-4 space-y-3">
    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Quien presenta (texto, sin firma imagen)</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-ui.label for="presenter_name">Nombre</x-ui.label>
            <x-ui.input id="presenter_name" name="presenter_name" :value="old('presenter_name', $d['presenter_name'])" accent="platform" />
            <x-ui.field-error :messages="$errors->get('presenter_name')" />
        </div>
        <div>
            <x-ui.label for="presenter_role">Cargo</x-ui.label>
            <x-ui.input id="presenter_role" name="presenter_role" :value="old('presenter_role', $d['presenter_role'])" accent="platform" />
            <x-ui.field-error :messages="$errors->get('presenter_role')" />
        </div>
        <div>
            <x-ui.label for="presenter_phone">Teléfono</x-ui.label>
            <x-ui.input id="presenter_phone" name="presenter_phone" :value="old('presenter_phone', $d['presenter_phone'])" accent="platform" />
            <x-ui.field-error :messages="$errors->get('presenter_phone')" />
        </div>
        <div>
            <x-ui.label for="presenter_email">Correo</x-ui.label>
            <x-ui.input type="email" id="presenter_email" name="presenter_email" :value="old('presenter_email', $d['presenter_email'])" accent="platform" />
            <x-ui.field-error :messages="$errors->get('presenter_email')" />
        </div>
    </div>
</div>
