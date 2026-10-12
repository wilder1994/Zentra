@props(['messages' => null, 'name' => null])

@php
    $messages ??= $name ? $errors->get($name) : null;
@endphp

@if ($messages)
    <p {{ $attributes->merge(['class' => 'mt-1 text-xs text-red-400']) }}>
        {{ is_array($messages) ? $messages[0] : $messages }}
    </p>
@endif
