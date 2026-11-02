{{-- Renders session flash messages (success / error). --}}
<div class="toast-stack" aria-live="polite">
    @foreach (['success' => 'check-circle-2', 'error' => 'x-circle'] as $type => $icon)
        @if (session($type))
            <div x-data="toast" x-show="visible" x-transition.opacity.duration.200ms class="toast toast-{{ $type }}" role="status">
                <x-ui.icon :name="$icon" />
                <p class="flex-1">{{ session($type) }}</p>
                <button type="button" class="text-muted hover:text-ink" x-on:click="close" aria-label="Tutup">
                    <x-ui.icon name="x" class="size-4" />
                </button>
            </div>
        @endif
    @endforeach
</div>
