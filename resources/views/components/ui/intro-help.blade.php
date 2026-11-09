@props([
    'storageKey',
    'text',
    'align' => 'left',
    'seconds' => 5,
])

@php
    $ms = max(1, (int) $seconds) * 1000;
@endphp

<span
    class="relative inline-flex items-center gap-2"
    x-data="{
        key: @js('zentra.intro.'.$storageKey),
        banner: false,
        init() {
            try {
                if (localStorage.getItem(this.key)) return;
                localStorage.setItem(this.key, '1');
                this.banner = true;
                setTimeout(() => { this.banner = false; }, {{ $ms }});
            } catch (e) {}
        },
    }"
>
    <span
        x-show="banner"
        x-cloak
        x-transition.opacity.duration.300ms
        class="max-w-xs sm:max-w-sm rounded-lg border border-teal-700/50 bg-teal-950/80 px-3 py-2 text-[11px] leading-relaxed text-teal-100 shadow-lg"
    >{{ $text }}</span>
    <x-ui.help-tip :text="$text" :align="$align" />
</span>
