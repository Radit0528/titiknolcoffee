@extends('layouts.owner')

@section('title', 'Penyesuaian Stok')

@section('content')
    <x-ui.page-header title="Penyesuaian Stok" subtitle="Masukkan jumlah hasil hitung fisik. Selisihnya dicatat sebagai mutasi penyesuaian."
                      :back="route('owner.stock.index')" back-label="Mutasi stok" />

    <form method="POST" action="{{ route('owner.stock.adjustment.store') }}" class="card max-w-2xl"
          x-data="stockForm(@js($options), @js($selected), 'adjust', @js(old('counted_stock')))" x-on:submit="submit">
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

            <x-form.field label="Stok fisik (hasil hitung)" for="counted_stock" required>
                <div class="input-group sm:max-w-72">
                    <input id="counted_stock" name="counted_stock" type="number" min="0" step="0.001" required x-model="amount"
                           @class(['input', 'input-invalid' => $errors->has('counted_stock')])>
                    <span class="input-addon w-14 justify-center" x-text="unit || '–'"></span>
                </div>
            </x-form.field>

            <dl class="stock-preview" x-show="current" x-cloak>
                <div><dt>Di sistem</dt><dd x-text="current ? format(current.stock) : '–'"></dd></div>
                <div><dt>Hitung fisik</dt><dd x-text="Number.isNaN(value) ? '–' : format(value)"></dd></div>
                <div>
                    <dt>Selisih</dt>
                    <dd x-bind:class="preview > 0 ? 'text-success' : (preview < 0 ? 'text-danger' : '')" x-text="preview === null ? '–' : signed(preview)"></dd>
                </div>
            </dl>

            <x-form.field label="Alasan" for="reason" hint="Contoh: stock opname akhir bulan, salah input pembelian." required>
                <input id="reason" name="reason" type="text" maxlength="255" required value="{{ old('reason') }}" @class(['input', 'input-invalid' => $errors->has('reason')])>
            </x-form.field>
        </div>

        <div class="flex justify-end gap-2 border-t border-line px-5 py-4">
            <x-ui.button :href="route('owner.stock.index')" variant="secondary">Batal</x-ui.button>
            <x-ui.button x-bind:disabled="submitting">Simpan penyesuaian</x-ui.button>
        </div>
    </form>
@endsection
