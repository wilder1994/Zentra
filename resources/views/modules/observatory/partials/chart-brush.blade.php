@if ($canPaintCharts ?? false)
    <button type="button" class="obs-brush"
            @click="openBrush('{{ $chart }}')" :aria-expanded="brush === '{{ $chart }}'"
            aria-label="Colores de {{ $title }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.5 4.5l5 5M4 20l4.2-.7c.4-.1.7-.3.9-.6L19.2 8.6a1.6 1.6 0 0 0 0-2.3l-1.5-1.5a1.6 1.6 0 0 0-2.3 0L5.3 14.9c-.3.3-.5.6-.6.9L4 20z"/>
        </svg>
    </button>
    <div class="obs-brush-pop" x-cloak x-show="brush === '{{ $chart }}'" @click.stop>
        <p>{{ $title }}</p>
        <template x-for="row in brushRows" :key="row.key">
            <label class="obs-brush-row" x-show="brush === '{{ $chart }}'">
                <span x-text="row.label"></span>
                <input type="color" :value="row.color" @input="previewBrush(row.key, $event.target.value)" @change="saveBrush()">
            </label>
        </template>
    </div>
@endif
