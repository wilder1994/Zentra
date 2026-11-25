<x-observatory-layout :title="'Reportar · '.$client->name">
    <style>
        .intake-kicker { font-size: 0.75rem; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; color: #94a3b8; }
        .intake { margin-top: 0.75rem; padding: 1rem; display: flex; flex-direction: column; gap: 0.75rem; border: 1px solid #334155; border-radius: 0.75rem; background: #0f172a; }
        .intake-label { display: block; margin-bottom: 0.25rem; font-size: 0.75rem; font-weight: 500; color: #94a3b8; }
        .intake-control { width: 100%; height: 2.5rem; padding: 0 0.75rem; font-size: 0.875rem; color: #f8fafc; background: #020617; border: 1px solid #475569; border-radius: 0.5rem; }
        textarea.intake-control { height: auto; padding: 0.5rem 0.75rem; }
        .intake-control::placeholder { color: #64748b; }
        .intake-school { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding-bottom: 0.75rem; border-bottom: 1px solid #334155; }
        .intake-school-name { min-width: 0; font-size: 0.95rem; font-weight: 600; color: #f8fafc; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .intake-change { flex: none; height: 2.25rem; padding: 0 0.75rem; font-size: 0.8125rem; font-weight: 600; color: #f8fafc; background: #1e293b; border: 1px solid #64748b; border-radius: 0.5rem; }
        .intake-list { border: 1px solid #334155; border-radius: 0.5rem; overflow: hidden; }
        .intake-list button { display: block; width: 100%; padding: 0.5rem 0.75rem; text-align: left; font-size: 0.875rem; color: #e2e8f0; background: transparent; border: 0; border-top: 1px solid #1e293b; }
        .intake-list button:first-child { border-top: 0; }
        .intake-hint { font-size: 0.6875rem; color: #94a3b8; }
        .intake-policy { display: flex; flex-direction: column; gap: 0.5rem; }
        .intake-policy p { margin: 0; font-size: 0.75rem; line-height: 1.45; color: #cbd5e1; text-align: justify; }
        .intake-age { display: flex; align-items: center; gap: 0.85rem; }
        .intake-age .intake-label { margin: 0; }
        .intake-radio { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.875rem; color: #e2e8f0; }
        .intake-check input,
        .intake-radio input { appearance: none; width: 1rem; height: 1rem; margin: 0.1rem 0 0; flex: none; border: 1px solid #64748b; border-radius: 50%; background: #020617; }
        .intake-check input:checked,
        .intake-radio input:checked { border-color: #14b8a6; background: radial-gradient(circle, #14b8a6 0 42%, transparent 46%); }
        .intake-alert { font-size: 0.75rem; color: #fca5a5; }
        .intake-tiles { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.5rem; }
        .intake-tile { position: relative; display: flex; aspect-ratio: 1; align-items: center; justify-content: center; overflow: hidden; border: 1px solid #475569; border-radius: 0.5rem; background: #020617; color: #94a3b8; font-size: 0.6875rem; font-weight: 600; cursor: pointer; }
        .intake-map { overflow: hidden; border: 1px solid #334155; border-radius: 0.5rem; }
        .intake-check { display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.875rem; color: #e2e8f0; }
        .intake-submit { width: 100%; height: 2.5rem; border: 0; border-radius: 0.5rem; background: #0d9488; color: #fff; font-size: 0.875rem; font-weight: 600; }
        .intake-submit:disabled { opacity: 0.4; }
        .intake-error { padding: 0.5rem 0.75rem; border: 1px solid #991b1b; border-radius: 0.5rem; background: #450a0a; color: #fecaca; font-size: 0.875rem; }
        html[data-theme="light"] body { background: #f1f5f9; color: #0f172a; }
        html[data-theme="light"] .intake-kicker { color: #475569; }
        html[data-theme="light"] .intake { border-color: #cbd5e1; background: #fff; }
        html[data-theme="light"] .intake-label,
        html[data-theme="light"] .intake-hint { color: #64748b; }
        html[data-theme="light"] .intake-policy p { color: #334155; }
        html[data-theme="light"] .intake-radio { color: #0f172a; }
        html[data-theme="light"] .intake-check input,
        html[data-theme="light"] .intake-radio input { background: #fff; border-color: #94a3b8; }
        html[data-theme="light"] .intake-alert { color: #b91c1c; }
        html[data-theme="light"] .intake-control { color: #0f172a; background: #fff; border-color: #cbd5e1; }
        html[data-theme="light"] .intake-school { border-color: #e2e8f0; }
        html[data-theme="light"] .intake-school-name { color: #0f172a; }
        html[data-theme="light"] .intake-change { color: #0f172a; background: #f8fafc; border-color: #94a3b8; }
        html[data-theme="light"] .intake-list { border-color: #cbd5e1; }
        html[data-theme="light"] .intake-list button { color: #0f172a; border-color: #e2e8f0; }
        html[data-theme="light"] .intake-tile { border-color: #cbd5e1; background: #f8fafc; color: #475569; }
        html[data-theme="light"] .intake-map { border-color: #cbd5e1; }
        html[data-theme="light"] .intake-check { color: #0f172a; }
        html[data-theme="light"] .intake-error { border-color: #fecaca; background: #fef2f2; color: #991b1b; }
    </style>

    <p class="intake-kicker">Observatorio</p>

    @if ($errors->any())
        <div class="intake-error mt-3">
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('observatory.public.store', $client->slug) }}"
        enctype="multipart/form-data"
        class="intake"
        @input="saveDraft()"
        @change="saveDraft()"
        @submit="guardSubmit($event)"
        x-data="observatoryIntake(@js([
            'installationsUrl' => route('observatory.public.installations', $client->slug),
            'storageKey' => 'observatory.identity.'.$client->slug,
            'installationId' => (string) old('installation_id', ''),
            'installationName' => old('installation_label', ''),
            'kind' => old('kind', ''),
            'anonymous' => (bool) old('is_anonymous', false),
            'role' => old('reporter_role', ''),
            'isAdult' => old('is_adult'),
            'consent' => (bool) old('minor_treatment_accepted', false),
            'reporterName' => old('reporter_name', ''),
            'reporterPhone' => old('reporter_phone', ''),
            'latitude' => old('latitude', ''),
            'longitude' => old('longitude', ''),
            'body' => old('body', ''),
            'draftKey' => 'observatory.draft.'.$client->slug,
            'consentKey' => 'observatory.consent.'.$client->slug,
            'consentMonths' => $consentMonths,
            'mapsKey' => $maps['api_key'] ?? '',
            'center' => $maps['center'] ?? ['lat' => 4.5709, 'lng' => -74.2973],
        ]))"
    >
        @csrf
        <input type="hidden" name="installation_id" :value="installationId">
        <input type="hidden" name="installation_label" :value="installationName">
        <input type="hidden" name="latitude" :value="latitude">
        <input type="hidden" name="longitude" :value="longitude">

        <div x-show="step === 1" class="space-y-2">
            <label class="intake-label" for="site-q">Colegio</label>
            <input
                id="site-q"
                type="search"
                x-model="query"
                @input.debounce.250ms="search()"
                placeholder="Nombre o código DANE"
                autocomplete="off"
                class="intake-control"
            >
            <div x-show="installations.length || (searched && !installationId)" class="intake-list">
                <template x-for="row in installations" :key="row.id">
                    <button type="button" @click="pick(row)">
                        <span x-text="row.name"></span>
                        <span class="intake-hint block" x-text="(row.dane_code ? 'DANE ' + row.dane_code : '') + (row.city ? ' · ' + row.city : '')"></span>
                    </button>
                </template>
                <p x-show="searched && !installationId && installations.length === 0" class="intake-hint px-3 py-2">No hay colegios con ese nombre o DANE.</p>
            </div>
        </div>

        <div x-show="step === 2" x-cloak class="space-y-3">
            <div class="intake-school">
                <p class="intake-school-name" x-text="installationName"></p>
                <button type="button" class="intake-change" @click="goStep(1)">Cambiar colegio</button>
            </div>
            <div>
                <label class="intake-label" for="kind">Riesgo endógeno</label>
                <select id="kind" name="kind" x-model="kind" required @change="focusBody()" class="intake-control">
                    <option value="">Seleccione…</option>
                    @foreach ($kinds as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="intake-label" for="body">Qué pasó</label>
                <textarea id="body" name="body" rows="3" required minlength="1" maxlength="2000" x-model="body" placeholder="Puede ser una palabra, por ejemplo hurto" class="intake-control"></textarea>
            </div>
            <div>
                <label class="intake-label">Dónde pasó</label>
                <div x-show="hasPin && mapsKey" class="intake-map">
                    <div x-ref="pinMap" class="h-36 w-full"></div>
                </div>
                <p x-show="!mapsKey || !hasPin" class="intake-hint">Sin mapa: se usa el pin del colegio si existe.</p>
            </div>
            <div>
                <p class="intake-label">Evidencia</p>
                <div class="intake-tiles">
                    <template x-for="index in [0, 1]" :key="index">
                        <label class="intake-tile">
                            <input type="file" accept="image/*" capture="environment" class="sr-only" :name="previews[index] ? 'photos[]' : null" @change="setPhoto($event, index)">
                            <img x-show="previews[index]" x-cloak :src="previews[index]" alt="" class="pointer-events-none absolute inset-0 h-full w-full object-cover">
                            <span x-show="!previews[index]" x-text="'Foto ' + (index + 1)"></span>
                        </label>
                    </template>
                    <label class="intake-tile">
                        <input id="video" type="file" accept="video/*" capture="environment" class="sr-only" :name="videoPreview ? 'video' : null" @change="setVideo($event)">
                        <video x-show="videoPreview" x-cloak :src="videoPreview" muted playsinline preload="metadata" class="pointer-events-none absolute inset-0 h-full w-full object-cover"></video>
                        <span x-show="!videoPreview">Video</span>
                    </label>
                </div>
                <p class="intake-hint mt-1" :class="videoError ? 'text-red-300' : ''" x-text="videoError || '2 fotos de 10 MB y 1 video de 30 s / 20 MB.'"></p>
            </div>

            <div x-show="readyForIdentity()" x-cloak class="space-y-3">
                <p x-show="remembered" class="intake-hint">
                    Datos guardados en este teléfono.
                    <button type="button" class="underline" @click="forget()">Borrar</button>
                </p>
                <div>
                    <label class="intake-label" for="reporter_role">Quién eres</label>
                    <select id="reporter_role" name="reporter_role" x-model="role" @change="onRole()" :required="readyForIdentity()" class="intake-control">
                        <option value="">Seleccione…</option>
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-show="asksAge()" x-cloak class="space-y-2">
                    <p x-show="consentSaved" class="intake-hint">
                        Edad y autorización guardadas en este dispositivo.
                        <button type="button" class="underline" @click="forgetConsent()">Volver a preguntar</button>
                    </p>
                    <div x-show="!consentSaved">
                        <p class="intake-label">¿Recordamos tu edad y autorización en este dispositivo?</p>
                        <div class="intake-age">
                            <label class="intake-radio">
                                <input type="radio" :checked="consentMemory === 'yes'" @change="setConsentMemory(true)">
                                <span>Sí</span>
                            </label>
                            <label class="intake-radio">
                                <input type="radio" :checked="consentMemory === 'no'" @change="setConsentMemory(false)">
                                <span>No</span>
                            </label>
                        </div>
                    </div>
                    <p x-show="consentMemory === 'no' && !consentSaved" x-cloak class="intake-hint">En los próximos reportes se seguirá preguntando la misma información.</p>
                    <div x-show="consentMemory !== null && !consentSaved" class="intake-age">
                        <p class="intake-label">¿Eres mayor de edad?</p>
                        <label class="intake-radio">
                            <input type="radio" :checked="minor === false" @change="setMinor(false)">
                            <span>Sí</span>
                        </label>
                        <label class="intake-radio">
                            <input type="radio" :checked="minor === true" @change="setMinor(true)">
                            <span>No</span>
                        </label>
                    </div>
                    <input type="hidden" name="is_adult" :value="minor ? '0' : '1'" :disabled="!asksAge() || minor === null">
                    <input type="hidden" name="remember_reporter" :value="consentMemory === 'yes' && consent ? '1' : '0'">
                </div>
                <div x-show="consentMemory !== null && isAdult() && !consentSaved" x-cloak class="intake-policy">
                    <p>Autorizo el tratamiento de mis datos personales para registrar este reporte de forma previa, expresa e informada conforme a la Ley 1581 de 2012.</p>
                    <p>Política de tratamiento de datos. El responsable recibe el reporte a través de Zentra. Trata el nombre, el teléfono si lo informas, el texto y la fecha y hora de envío, solo para registrar la alerta. Esos datos no se venden ni se usan para publicidad. Se conservan el tiempo necesario para atender la alerta y las obligaciones legales. Puedes pedir conocer, actualizar, rectificar o suprimir tus datos.</p>
                </div>
                <div x-show="consentMemory !== null && isMinor() && !consentSaved" x-cloak class="intake-policy">
                    <p>Los datos personales de niños, niñas y adolescentes tienen protección especial. La Ley 1581 de 2012 (artículo 7) prohíbe su tratamiento, salvo los datos de naturaleza pública, y exige respetar sus derechos prevalentes. Solo pueden tratarse cuando ello responde a su interés superior y existe autorización previa, expresa e informada del representante legal (Decreto 1377 de 2013).</p>
                </div>
                <div x-show="consentMemory !== null && needsConsent() && !consentSaved" x-cloak class="space-y-2">
                    <label class="intake-check">
                        <input type="checkbox" name="minor_treatment_accepted" value="1" x-model="consent" @change="onConsent()">
                        <span>Autorizo el tratamiento de mis datos personales</span>
                    </label>
                    <p x-show="consentError" x-cloak class="intake-alert" x-text="consentError"></p>
                </div>
                <label class="intake-check" x-show="showAnonymous()" x-cloak>
                    <input type="hidden" name="is_anonymous" value="0">
                    <input type="checkbox" name="is_anonymous" value="1" x-model="anonymous" class="mt-0.5">
                    <span>Quiero reportar en anónimo</span>
                </label>
                <p x-show="showAnonymous() && anonymous" class="intake-hint">En este reporte se oculta tu nombre y teléfono.</p>
                <div x-show="readyToSend() && !anonymous" class="space-y-3">
                    <div>
                        <label class="intake-label" for="reporter_name">Nombre</label>
                        <input id="reporter_name" type="text" name="reporter_name" x-model="reporterName" class="intake-control">
                        <p x-show="role === 'alumno'" class="intake-hint mt-1">Si eres alumno, el nombre de este reporte no se guarda en el teléfono.</p>
                    </div>
                    <div x-show="role !== 'alumno'">
                        <label class="intake-label" for="reporter_phone">Teléfono (opcional)</label>
                        <input id="reporter_phone" type="text" name="reporter_phone" x-model="reporterPhone" class="intake-control">
                    </div>
                </div>
                <label class="intake-check" x-show="readyToSend()" x-cloak>
                    <input type="checkbox" x-model="remember" class="mt-0.5">
                    Recordarme en este teléfono
                </label>
                <button type="submit" class="intake-submit" x-show="readyToSend()" x-cloak :disabled="sending || videoError !== ''" x-text="sending ? 'Guardando…' : 'Enviar reporte'"></button>
            </div>
        </div>
    </form>
</x-observatory-layout>
