@props(['route', 'icon', 'active' => null, 'badge' => null])
@php
    $isActive = request()->routeIs($active ?? $route);
@endphp
<a href="{{ route($route) }}" @class(['sidebar-link', 'sidebar-link-active' => $isActive]) @if ($isActive) aria-current="page" @endif>
    <x-ui.icon :name="$icon" />
    <span>{{ $slot }}</span>
    @if ($badge)
        <span class="sidebar-badge" title="Bahan menipis atau habis">{{ $badge }}</span>
    @endif
</a>
