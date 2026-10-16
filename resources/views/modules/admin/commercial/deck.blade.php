@php
    $d = $defaults;
    $ta = 'w-full px-3 py-2 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white placeholder:text-slate-600 focus:border-violet-500 focus:ring-1 focus:ring-violet-500/30';
@endphp

<x-admin-layout title="Presentación comercial">
    <div class="max-w-3xl space-y-4">
        <x-ui.button variant="secondary" :href="route('admin.commercial.index')" size="sm">← Comercial</x-ui.button>

        <p class="text-sm text-slate-400">
            Genera un PowerPoint (.pptx) de 11 diapositivas (paleta clara, capturas del panel empresa y cierre «Gracias»). Marcas: Zentra · W Codex.
        </p>

        <form
            method="POST"
            action="{{ route('admin.commercial.deck.pptx') }}"
            class="space-y-4 rounded-lg border border-slate-800 bg-slate-900/80 p-4"
        >
            @csrf

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
                <x-ui.label for="subject">Asunto / título</x-ui.label>
                <x-ui.input id="subject" name="subject" :value="old('subject', $d['subject'])" accent="platform" />
                <x-ui.field-error :messages="$errors->get('subject')" />
            </div>

            <div>
                <x-ui.label for="context">El reto (contexto)</x-ui.label>
                <textarea id="context" name="context" rows="4" class="{{ $ta }}">{{ old('context', $d['context']) }}</textarea>
                <x-ui.field-error :messages="$errors->get('context')" />
            </div>

            <div>
                <x-ui.label for="solution">La solución</x-ui.label>
                <textarea id="solution" name="solution" rows="4" class="{{ $ta }}">{{ old('solution', $d['solution']) }}</textarea>
                <x-ui.field-error :messages="$errors->get('solution')" />
            </div>

            <div>
                <x-ui.label for="why">Por qué Zentra</x-ui.label>
                <textarea id="why" name="why" rows="3" class="{{ $ta }}">{{ old('why', $d['why']) }}</textarea>
                <x-ui.field-error :messages="$errors->get('why')" />
            </div>

            <div>
                <x-ui.label for="next_steps">Próximos pasos</x-ui.label>
                <textarea id="next_steps" name="next_steps" rows="3" class="{{ $ta }}">{{ old('next_steps', $d['next_steps']) }}</textarea>
                <x-ui.field-error :messages="$errors->get('next_steps')" />
            </div>

            <div>
                <x-ui.label for="closing">Cierre</x-ui.label>
                <textarea id="closing" name="closing" rows="2" class="{{ $ta }}">{{ old('closing', $d['closing']) }}</textarea>
                <x-ui.field-error :messages="$errors->get('closing')" />
            </div>

            <div class="border-t border-slate-800 pt-4 space-y-3">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Quien presenta</p>
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
                        <x-ui.label for="presenter_phone">Celular</x-ui.label>
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

            <div class="flex items-center gap-3 pt-2">
                <x-ui.button type="submit" variant="platform">Descargar PowerPoint</x-ui.button>
                <a href="{{ route('admin.commercial.index') }}" class="text-sm text-slate-400 hover:text-white">Cancelar</a>
            </div>
        </form>
    </div>
</x-admin-layout>
