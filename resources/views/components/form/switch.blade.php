@props(['name', 'checked' => false, 'label', 'description' => null])
<label class="switch">
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" value="1" class="peer" @checked($checked) {{ $attributes }}>
    <span class="switch-track"></span>
    <span class="flex flex-col">
        <span class="text-sm font-medium">{{ $label }}</span>
        @if ($description)
            <span class="hint">{{ $description }}</span>
        @endif
    </span>
</label>
