@props(['label', 'value', 'icon' => null, 'note' => null])
<div {{ $attributes->class('stat-card') }}>
    <span class="stat-label">
        @if ($icon)<x-ui.icon :name="$icon" />@endif
        {{ $label }}
    </span>
    <span class="stat-value">{{ $value }}</span>
    @if ($note)
        <span class="stat-note">{{ $note }}</span>
    @endif
</div>
