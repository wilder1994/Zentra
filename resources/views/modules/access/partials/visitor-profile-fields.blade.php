{{-- Campos de ficha de visitante. Prefijo Alpine opcional vía $prefix (ej. reg). --}}
@php
    $p = $prefix ?? '';
    $docModel = $p !== '' ? "{$p}Doc" : 'documentNumber';
    $firstModel = $p !== '' ? "{$p}First" : 'firstName';
    $lastModel = $p !== '' ? "{$p}Last" : 'lastName';
    $phoneModel = $p !== '' ? "{$p}Phone" : 'phone';
    $emailModel = $p !== '' ? "{$p}Email" : 'email';
    $companyModel = $p !== '' ? "{$p}Company" : 'company';
    $typeModel = $p !== '' ? "{$p}Type" : 'visitorType';
    $showCompany = $showCompany ?? true;
@endphp
<div class="grid grid-cols-2 gap-2">
    <label class="text-xs text-slate-400">Tipo doc.
        <select name="document_type" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
            @foreach(['CC','NIT','CE','Pasaporte'] as $dt)
                <option value="{{ $dt }}" @selected(old('document_type', 'CC') === $dt)>{{ $dt }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-xs text-slate-400">Documento <span class="text-amber-400">*</span>
        <input name="document_number" x-model="{{ $docModel }}" required
               class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
    </label>
    <label class="text-xs text-slate-400">Nombres <span class="text-amber-400">*</span>
        <input name="first_name" x-model="{{ $firstModel }}" required
               class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
    </label>
    <label class="text-xs text-slate-400">Apellidos <span class="text-amber-400">*</span>
        <input name="last_name" x-model="{{ $lastModel }}" required
               class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
    </label>
    <label class="text-xs text-slate-400">Teléfono
        <input name="phone" x-model="{{ $phoneModel }}"
               class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
    </label>
    <label class="text-xs text-slate-400">Correo
        <input type="email" name="email" x-model="{{ $emailModel }}"
               class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
    </label>
    <label class="text-xs text-slate-400 col-span-2">Tipo de visitante
        <select name="visitor_type" x-model="{{ $typeModel }}"
                class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
            <option value="persona">Visitante (amigo / familiar)</option>
            <option value="contratista">Contratista</option>
            <option value="proveedor">Proveedor / empresa</option>
            <option value="domiciliario">Domiciliario</option>
            <option value="servicio">Servicio (contadores, recibos, etc.)</option>
        </select>
    </label>
    @if($showCompany)
    <label class="text-xs text-slate-400 col-span-2" x-show="{{ $typeModel }} === 'contratista' || {{ $typeModel }} === 'proveedor' || {{ $typeModel }} === 'servicio'">
        Empresa / razón social
        <input name="company" x-model="{{ $companyModel }}"
               class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm"
               placeholder="Si viene de una empresa">
    </label>
    @endif
</div>
