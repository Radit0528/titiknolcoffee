{{--
    Delete button + confirmation dialog.
    <x-ui.modal :action="route('...destroy', $model)" title="Hapus produk?" message="..." />
--}}
@props([
    'action',
    'title' => 'Hapus data ini?',
    'message' => 'Tindakan ini tidak dapat dibatalkan.',
    'confirmLabel' => 'Ya, hapus',
    'method' => 'DELETE',
    'triggerLabel' => null,
])

<div x-data="confirmDialog" x-on:keydown.escape.window="hide" class="inline-flex">
    <form x-ref="form" method="POST" action="{{ $action }}" class="hidden">
        @csrf
        @method($method)
    </form>

    @if ($triggerLabel)
        <x-ui.button type="button" variant="secondary" size="sm" icon="trash-2" x-on:click="show">{{ $triggerLabel }}</x-ui.button>
    @else
        <x-ui.button type="button" variant="ghost" size="sm" class="btn-icon hover:text-danger" icon="trash-2" x-on:click="show" aria-label="Hapus" />
    @endif

    <template x-teleport="body">
        <div x-show="open" x-cloak>
            <div class="modal-backdrop" x-on:click="hide" x-transition.opacity></div>
            <div class="modal-panel" role="alertdialog" aria-modal="true" x-transition>
                <span class="modal-icon"><x-ui.icon name="alert-triangle" /></span>
                <h2 class="text-base font-semibold">{{ $title }}</h2>
                <p class="mt-1.5 text-sm text-muted">{{ $message }}</p>
                <div class="mt-6 flex justify-end gap-2">
                    <x-ui.button type="button" variant="secondary" x-ref="cancel" x-on:click="hide">Batal</x-ui.button>
                    <x-ui.button type="button" variant="danger" x-on:click="confirm" x-bind:disabled="submitting">{{ $confirmLabel }}</x-ui.button>
                </div>
            </div>
        </div>
    </template>
</div>
