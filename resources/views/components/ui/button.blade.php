@props([
    'variant' => 'primary',
    'size' => null,
    'href' => null,
    'icon' => null,
    'type' => 'submit',
])

@php
    $classes = trim('btn btn-'.$variant.($size ? ' btn-'.$size : ''));
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-ui.icon :name="$icon" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-ui.icon :name="$icon" />@endif
        {{ $slot }}
    </button>
@endif
