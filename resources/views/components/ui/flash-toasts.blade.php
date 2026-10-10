@props([
    'rail' => null,
])

@php
    $firstError = $errors->first();
    $firstKey = $errors->keys()[0] ?? '';
    $kind = session('error') ? 'error' : (session('warning') ? 'warning' : (session('success') ? 'success' : ($firstError ? 'error' : '')));
    $text = session('error') ?? session('warning') ?? session('success') ?? $firstError;
@endphp

<div
    id="zentra-feedback"
    data-zentra-feedback
    data-initial-text="{{ $text ?? '' }}"
    data-initial-kind="{{ $kind }}"
    data-initial-field="{{ $firstKey }}"
    class="pointer-events-none fixed inset-0 z-[100] flex items-center justify-center px-5"
    aria-live="assertive"
    aria-atomic="true"
>
    <p
        data-zentra-feedback-msg
        hidden
        class="max-w-md rounded-xl border px-5 py-3 text-center text-sm font-semibold leading-snug shadow-2xl"
    ></p>
</div>
