@props(['icon' => 'package', 'title', 'description' => null])
<div class="empty-state">
    <span class="empty-state-icon"><x-ui.icon :name="$icon" /></span>
    <p class="text-sm font-semibold">{{ $title }}</p>
    @if ($description)
        <p class="max-w-sm text-sm text-muted">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-3">{{ $slot }}</div>
    @endif
</div>
