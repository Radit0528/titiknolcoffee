@extends('layouts.owner')

@section('title', 'Stok Keluar')

@section('content')
    <x-ui.page-header title="Stok Keluar" subtitle="Catat bahan yang rusak, kedaluwarsa, atau tumpah/terbuang."
                      :back="route('owner.stock.index')" back-label="Mutasi stok" />

    <form method="POST" action="{{ route('owner.stock.out.store') }}" class="card max-w-2xl"
          x-data="stockForm(@js($options), @js($selected), 'out', @js(old('quantity')))" x-on:submit="submit">
        @csrf

        <div class="card-body flex flex-col gap-5">
            <x-form.field label="Bahan baku" for="ingredient_id" required>
                <select id="ingredient_id" name="ingredient_id" required x-model="ingredientId" @class(['select', 'input-invalid' => $errors->has('ingredient_id')])>
                    <option value="">Pilih bahan</option>
                    @foreach ($ingredients as $ingredient)
                        <option value="{{ $ingredient->id }}" @selected((int) $selected === $ingredient->id)>{{ $ingredient->name }}</option>
                    @endforeach
                </select>
            </x-form.field>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.field label="Jenis" for="type" required>
                    <select id="type" name="type" required @class(['select', 'input-invalid' => $errors->has('type')])>
                        @foreach ($types as $case)
                            <option value="{{ $case->value }}" @selected(old('type') === $case->value)>{{ $case->label() }}</option>
                        @endforeach
                    </select>
                </x-form.field>

                <x-form.field label="Jumlah keluar" for="quantity" required>
                    <div class="input-group">
                        <input id="quantity" name="quantity" type="number" min="0.001" step="0.001" required x-model="amount"
                               @class(['input', 'input-invalid' => $errors->has('quantity')])>
                        <span class="input-addon w-14 justify-center" x-text="unit || '–'"></span>
                    </div>
                </x-form.field>
            </div>

            <dl class="stock-preview" x-show="current" x-cloak>
                <div><dt>Stok sekarang</dt><dd x-text="current ? format(current.stock) : '–'"></dd></div>
                <div><dt>Keluar</dt><dd class="text-danger" x-text="Number.isNaN(value) ? '–' : '-' + format(value)"></dd></div>
                <div><dt>Sisa</dt><dd x-bind:class="exceeds && 'text-danger'" x-text="preview === null ? '–' : format(preview)"></dd></div>
            </dl>
            <p class="field-error" x-show="exceeds" x-cloak>Jumlah keluar melebihi stok yang tersedia.</p>

            <x-form.field label="Alasan" for="reason" hint="Wajib diisi, misalnya: susu basi, kemasan bocor." required>
                <input id="reason" name="reason" type="text" maxlength="255" required value="{{ old('reason') }}" @class(['input', 'input-invalid' => $errors->has('reason')])>
            </x-form.field>
        </div>

        <div class="flex justify-end gap-2 border-t border-line px-5 py-4">
            <x-ui.button :href="route('owner.stock.index')" variant="secondary">Batal</x-ui.button>
            <x-ui.button variant="danger" x-bind:disabled="submitting || exceeds">Catat stok keluar</x-ui.button>
        </div>
    </form>
@endsection
