@php
    $post = $post instanceof \App\Models\SupervisorPost ? $post : null;
    abort_unless($post !== null, 500);
    $vista = $vista ?? 'sitio';
    $oldIds = array_map('intval', (array) old('employee_ids', []));
    $selectedEmployees = $oldIds !== []
        ? \App\Models\Employee::query()->whereIn('id', $oldIds)->get()
        : ($post->employees ?? collect());
    $pickerConfig = [
        'searchUrl' => route('company.employees.lookup'),
        'exceptPostId' => $post->id,
        'selected' => $selectedEmployees->map(fn ($employee) => [
            'id' => $employee->id,
            'name' => $employee->fullName(),
            'document' => trim($employee->document_type.' '.$employee->document_number),
            'job' => $employee->jobTitle?->name ?: 'empleado',
        ])->values()->all(),
    ];
@endphp

<form method="POST" action="{{ route('company.clients.posts.update', [$client, $post]) }}" class="space-y-3">
    @csrf
    @method('PUT')
    <input type="hidden" name="vista" value="{{ $vista }}">
    <input type="hidden" name="installation_id" value="{{ $post->installation_id }}">
    <input type="hidden" name="name" value="{{ $post->name }}">
    <input type="hidden" name="modality" value="{{ $post->modality }}">
    <input type="hidden" name="serves_holidays" value="{{ $post->serves_holidays ? 1 : 0 }}">
    <input type="hidden" name="reviews_per_day" value="{{ $post->reviewsPerDay() }}">
    <input type="hidden" name="is_active" value="{{ $post->is_active ? 1 : 0 }}">
    @foreach ($post->serviceDayList() as $day)
        <input type="hidden" name="service_days[]" value="{{ $day }}">
    @endforeach

    <div class="space-y-2" x-data="postEmployeePicker(@js($pickerConfig))" @click.outside="open = false">
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

    <button type="submit" class="w-full rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500">
        Guardar vigilantes
    </button>
</form>
