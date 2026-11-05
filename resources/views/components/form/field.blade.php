{{-- Label + control + hint + validation error. Put the control in the slot. --}}
@props(['label', 'for', 'error' => null, 'hint' => null, 'required' => false, 'bag' => 'default'])
<div {{ $attributes->class('field') }}>
    <label for="{{ $for }}" class="label">
        {{ $label }}@if ($required)<span class="text-danger"> *</span>@endif
    </label>
    {{ $slot }}
    @if ($hint)
        <p class="hint">{{ $hint }}</p>
    @endif
    @error($error ?? $for, $bag)
        <p class="field-error">{{ $message }}</p>
    @enderror
</div>
