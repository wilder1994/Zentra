@props([
    'text',
    'align' => 'left',
])

@php
    $alignJs = match ($align) {
        'right' => 'right',
        'center' => 'center',
        default => 'left',
    };
@endphp

<span class="relative inline-flex shrink-0 align-middle"
      x-data="{
        open: false,
        panelStyle: {},
        align: @js($alignJs),
        place() {
            const btn = this.$refs.btn;
            if (! btn) return;
            const r = btn.getBoundingClientRect();
            const width = window.matchMedia('(min-width: 640px)').matches ? 256 : 224;
            let left = r.left;
            if (this.align === 'right') left = r.right - width;
            if (this.align === 'center') left = r.left + (r.width / 2) - (width / 2);
            left = Math.max(8, Math.min(left, window.innerWidth - width - 8));
            let top = r.bottom + 6;
            const approxH = 96;
            if (top + approxH > window.innerHeight - 8) {
                top = Math.max(8, r.top - approxH - 6);
            }
            this.panelStyle = {
                position: 'fixed',
                top: `${Math.round(top)}px`,
                left: `${Math.round(left)}px`,
                width: `${width}px`,
                zIndex: 300,
            };
        },
        toggle() {
            this.open = ! this.open;
            if (this.open) {
                this.$nextTick(() => this.place());
            }
        },
        close() { this.open = false; },
      }"
      @keydown.escape.window="close()"
      @scroll.window="if (open) close()"
      @resize.window="if (open) place()">
    <button type="button"
        x-ref="btn"
        class="inline-flex h-3.5 w-3.5 items-center justify-center rounded-full border border-slate-600 text-[9px] font-semibold leading-none text-slate-400 hover:border-slate-400 hover:text-white"
        @click.stop.prevent="toggle()"
        :aria-expanded="open.toString()"
        aria-label="Ayuda">
        ?
    </button>
    <template x-teleport="body">
        <span x-show="open" x-cloak x-transition.opacity
            @click.outside="close()"
            @click.stop
            :style="panelStyle"
            class="rounded-lg border border-slate-600 bg-slate-950 p-2.5 text-[11px] font-normal normal-case tracking-normal leading-relaxed text-slate-200 shadow-2xl ring-1 ring-black/40">
            {{ $text }}
        </span>
    </template>
</span>
