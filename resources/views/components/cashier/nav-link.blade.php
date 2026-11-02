@props(['route', 'icon', 'active' => null])
@php($isActive = request()->routeIs($active ?? $route))
<a href="{{ route($route) }}" @if ($isActive) aria-current="page" @endif>
    <x-ui.icon :name="$icon" />
    <span class="inline-flex items-center gap-1.5">{{ $slot }}</span>
</a>
