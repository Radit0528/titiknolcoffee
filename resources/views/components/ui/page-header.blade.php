@props(['title', 'subtitle' => null, 'back' => null, 'backLabel' => 'Kembali'])
<div class="page-header">
    <div>
        @if ($back)
            <a href="{{ $back }}" class="back-link"><x-ui.icon name="arrow-left" /> {{ $backLabel }}</a>
        @endif
        <h1 class="page-title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="page-subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
