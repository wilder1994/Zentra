@php
    $post = ($post ?? null) instanceof \App\Models\SupervisorPost ? $post : null;
    $vista = $vista ?? 'sitio';
    $accent = $accent ?? 'indigo';
    $postModalities = $postModalities ?? [];
    $selectedHours = (int) old('modality', $post?->modality ?? 12);
    if ($selectedHours > 0 && ! isset($postModalities[$selectedHours])) {
        $postModalities[$selectedHours] = $selectedHours.' h';
    }
    $selectedDays = array_map('intval', (array) old('service_days', $post?->serviceDayList() ?? [1, 2, 3, 4, 5, 6, 7]));
    $servesHolidays = (bool) old('serves_holidays', $post?->serves_holidays ?? true);
    $installations = $installations ?? collect();
    $installation = $installation ?? null;
    $returnTo = $returnTo ?? null;
    $btn = $accent === 'indigo' ? 'bg-indigo-600' : 'bg-amber-600';
    $check = $accent === 'indigo' ? 'text-indigo-600' : 'text-amber-500';
    $isEdit = $post !== null;
    $oldIds = array_map('intval', (array) old('employee_ids', []));
    $selectedEmployees = $oldIds !== []
        ? \App\Models\Employee::query()->whereIn('id', $oldIds)->get()
        : ($post?->employees ?? collect());
    $pickerConfig = [
        'searchUrl' => route('company.employees.lookup'),
        'exceptPostId' => $post?->id,
        'selected' => $selectedEmployees->map(fn ($employee) => [
            'id' => $employee->id,
            'name' => $employee->fullName(),
            'document' => trim($employee->document_type.' '.$employee->document_number),
            'job' => $employee->jobTitle?->name ?: 'empleado',
        ])->values()->all(),
    ];
@endphp

<form
    method="POST"
    action="{{ $isEdit ? route('company.clients.posts.update', [$client, $post]) : route('company.clients.posts.store', $client) }}"
    class="{{ $isEdit ? 'mt-2' : '' }} grid sm:grid-cols-2 gap-2 items-end"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif
    <input type="hidden" name="vista" value="{{ $vista }}">
    @if ($returnTo)
        <input type="hidden" name="return_to" value="{{ $returnTo }}">
    @endif

    @if ($isEdit)
        <div class="sm:col-span-2">
            <label class="block text-[11px] text-slate-500 mb-1">Instalación</label>
            <select name="installation_id" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-2 py-1.5 text-xs text-white">
                @foreach ($installations as $option)
                    <option value="{{ $option->id }}" @selected($option->id === $post->installation_id)>{{ $option->name }}</option>
                @endforeach
            </select>
        </div>
    @else
        <input type="hidden" name="installation_id" value="{{ $installation->id }}">
    @endif

    <div>
        <label class="block text-[11px] text-slate-500 mb-1">{{ $isEdit ? 'Nombre' : 'Nuevo puesto' }}</label>
        <input type="text" name="name" value="{{ old('name', $post?->name) }}" required placeholder="{{ $namePlaceholder ?? 'Portería principal' }}" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-2 py-1.5 text-xs text-white">
    </div>

    <div>
        <label class="block text-[11px] text-slate-500 mb-1">Modalidad</label>
        <select name="modality" required class="w-full rounded-lg bg-slate-950 border border-slate-700 px-2 py-1.5 text-xs text-white">
            @foreach ($postModalities as $value => $label)
                <option value="{{ $value }}" @selected($selectedHours === (int) $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="sm:col-span-2" x-data="{ days: @js($selectedDays), set(list) { this.days = list } }">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
            <label class="block text-[11px] text-slate-500">Días de servicio</label>
            <div class="flex gap-1">
                <button type="button" @click="set([1,2,3,4,5,6,7])" class="rounded-md border border-slate-700 px-2 py-0.5 text-[10px] text-slate-300 hover:bg-slate-800">Lun a Dom</button>
                <button type="button" @click="set([1,2,3,4,5])" class="rounded-md border border-slate-700 px-2 py-0.5 text-[10px] text-slate-300 hover:bg-slate-800">Lun a Vie</button>
                <button type="button" @click="set([1,2,3,4,5,6])" class="rounded-md border border-slate-700 px-2 py-0.5 text-[10px] text-slate-300 hover:bg-slate-800">Lun a Sáb</button>
            </div>
        </div>
        <div class="flex flex-wrap gap-1">
            @foreach (\App\Models\SupervisorPost::DAY_LABELS as $iso => $dayLabel)
                <label class="cursor-pointer">
                    <input type="checkbox" name="service_days[]" value="{{ $iso }}" x-model.number="days" class="peer sr-only">
                    <span class="inline-flex min-w-[2.6rem] justify-center rounded-md border border-slate-700 px-2 py-1 text-[11px] text-slate-400 peer-checked:border-indigo-500 peer-checked:bg-indigo-600/30 peer-checked:text-white">{{ $dayLabel }}</span>
                </label>
            @endforeach
        </div>
        <div class="mt-2 flex flex-wrap items-center gap-3 text-[11px] text-slate-300">
            <span class="text-slate-500">¿Presta servicio en festivos?</span>
            <label class="inline-flex items-center gap-1">
                <input type="radio" name="serves_holidays" value="1" @checked($servesHolidays) class="border-slate-700 {{ $check }}"> Sí
            </label>
            <label class="inline-flex items-center gap-1">
                <input type="radio" name="serves_holidays" value="0" @checked(! $servesHolidays) class="border-slate-700 {{ $check }}"> No
            </label>
        </div>
        <p class="mt-1 text-[11px] text-slate-500">Cada día de servicio espera al menos una revista (de 06:00 a 06:00). Los días sin servicio no cuentan para el cumplimiento.</p>
    </div>

    <div class="sm:col-span-2 space-y-2" x-data="postEmployeePicker(@js($pickerConfig))" @click.outside="open = false">
        <label class="block text-[11px] text-slate-500">Vigilantes</label>
        <div class="flex flex-wrap gap-1">
            <template x-for="person in selected" :key="person.id">
                <span class="inline-flex items-center gap-1 rounded-full bg-slate-800 px-2 py-0.5 text-[11px] text-slate-200">
                    <span x-text="person.name"></span>
                    <button type="button" class="text-slate-400 hover:text-white" @click="remove(person.id)">×</button>
                    <input type="hidden" name="employee_ids[]" :value="person.id">
                </span>
            </template>
        </div>
        <div class="relative">
            <input
                type="search"
                x-model="query"
                @input="search()"
                @focus="if (results.length) open = true"
                placeholder="Buscar por cédula o nombre"
                autocomplete="off"
                class="w-full rounded-lg bg-slate-950 border border-slate-700 px-2 py-1.5 text-xs text-white"
            >
            <ul
                x-show="open"
                x-cloak
                class="absolute z-20 mt-1 max-h-48 w-full overflow-auto rounded-lg border border-slate-700 bg-slate-950 py-1 shadow-xl"
            >
                <template x-for="item in results" :key="item.id">
                    <li>
                        <button
                            type="button"
                            class="flex w-full flex-col px-3 py-1.5 text-left hover:bg-slate-800"
                            @click="pick(item)"
                        >
                            <span class="text-xs text-white" x-text="item.name"></span>
                            <span class="text-[10px] text-slate-500" x-text="item.document + (item.job ? ' · ' + item.job : '') + (item.assigned ? ' · ya asignado' : '')"></span>
                        </button>
                    </li>
                </template>
            </ul>
        </div>
        <p x-show="warning" x-cloak class="text-[11px] text-amber-300">
            <span x-text="warning"></span>
            <a x-show="warningUrl" :href="warningUrl" class="underline hover:text-amber-200">Abrir ficha</a>
        </p>
    </div>

    <div>
        <label class="block text-[11px] text-slate-500 mb-1">Lat. puesto (opcional)</label>
        <input type="text" name="latitude" value="{{ old('latitude', $post?->latitude) }}" placeholder="La de la instalación" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-2 py-1.5 text-xs text-white">
    </div>
    <div>
        <label class="block text-[11px] text-slate-500 mb-1">Lng. puesto (opcional)</label>
        <input type="text" name="longitude" value="{{ old('longitude', $post?->longitude) }}" placeholder="Sin pin propio" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-2 py-1.5 text-xs text-white">
    </div>
    <p class="sm:col-span-2 text-[11px] text-slate-500">Crear o editar el puesto no pide GPS. La revista sí: {{ $post ? app(\App\Support\Supervision\SupervisorReviewGeofence::class)->radiusFor($post) : \App\Models\SupervisionReviewSettings::current()->default_radius_m }} m del pin del puesto o, si no hay, de la instalación. Un pin lejos de la instalación se anota; no se bloquea el alta.</p>

    <div class="sm:col-span-2">
        <label class="block text-[11px] text-slate-500 mb-1">Observaciones @if ($isEdit)<span class="text-slate-600">(obligatorio si el servicio ya está armado)</span>@else<span class="text-slate-600">(opcional en el alta)</span>@endif</label>
        <textarea name="observations" rows="2" maxlength="2000" placeholder="{{ $isEdit ? 'Por qué cambia modalidad, vigilantes o estado…' : 'Opcional' }}" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-2 py-1.5 text-xs text-white">{{ old('observations') }}</textarea>
    </div>

    <div class="flex items-center gap-2 sm:col-span-2">
        @if ($isEdit)
            <label class="inline-flex items-center gap-1 text-[11px] text-slate-300">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $post->is_active)) class="rounded border-slate-700 {{ $check }}">
                Activo · desmarcar lo archiva y libera cupo de la instalación
            </label>
        @endif
        <button type="submit" class="rounded-lg {{ $isEdit ? $btn.' px-2 py-1 text-[11px] font-semibold text-white' : 'bg-slate-800 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-slate-700' }}">
            {{ $isEdit ? 'OK' : 'Agregar puesto' }}
        </button>
    </div>
</form>
