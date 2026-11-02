@props(['name'])
<i data-lucide="{{ $name }}" {{ $attributes->merge(['aria-hidden' => 'true']) }}></i>
