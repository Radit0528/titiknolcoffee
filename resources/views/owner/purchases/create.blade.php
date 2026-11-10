@extends('layouts.owner')

@section('title', 'Catat Pembelian')

@section('content')
    <x-ui.page-header title="Catat Pembelian" subtitle="Isi jumlah dalam satuan bahan (gram, ml, pcs) dan total harga yang dibayar per bahan."
                      :back="route('owner.purchases.index')" back-label="Pembelian" />

    @if ($ingredientOptions->isEmpty())
        <div class="alert alert-warning mb-6">
            <x-ui.icon name="alert-triangle" />
            <p>Belum ada bahan baku. <a href="{{ route('owner.ingredients.create') }}" class="font-semibold underline">Tambah bahan baku</a> terlebih dahulu.</p>
        </div>
    @endif

    <form method="POST" action="{{ route('owner.purchases.store') }}" class="flex flex-col gap-6"
          x-data="purchaseForm(@js($ingredientOptions), @js($initialItems))" x-on:submit="submit">
        @csrf

        <div class="card">
            <div class="card-body grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <x-form.field label="Tanggal pembelian" for="purchased_at" required>
                    <input id="purchased_at" name="purchased_at" type="date" required max="{{ now()->toDateString() }}"
                           value="{{ old('purchased_at', now()->toDateString()) }}" @class(['input', 'input-invalid' => $errors->has('purchased_at')])>
                </x-form.field>
                <x-form.field label="Nomor nota" for="invoice_number" hint="Opsional, dari nota pembelian.">
                    <input id="invoice_number" name="invoice_number" type="text" maxlength="50" value="{{ old('invoice_number') }}" class="input">
                </x-form.field>
                <x-form.field label="Catatan" for="notes">
                    <input id="notes" name="notes" type="text" maxlength="1000" value="{{ old('notes') }}" class="input">
                </x-form.field>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Bahan yang dibeli</h2>
                <x-ui.button type="button" variant="secondary" size="sm" icon="plus" x-on:click="add">Tambah baris</x-ui.button>
            </div>

            <div class="purchase-row-head">
                <span>Bahan baku</span><span>Jumlah</span><span>Harga beli (total)</span><span class="text-right">Per satuan</span><span></span>
            </div>

            <template x-for="(row, index) in rows" x-bind:key="row.key">
                <div class="purchase-row">
                    <select class="select" x-bind:name="`items[${index}][ingredient_id]`" x-model="row.ingredient_id" required aria-label="Bahan baku">
                        <option value="">Pilih bahan</option>
                        <template x-for="ingredient in ingredients" x-bind:key="ingredient.id">
                            <option x-bind:value="String(ingredient.id)" x-text="ingredient.name"
                                    x-bind:disabled="isTaken(ingredient.id, row)"
                                    x-bind:selected="String(ingredient.id) === row.ingredient_id"></option>
                        </template>
                    </select>
                    <div class="input-group">
                        <input type="number" class="input" min="0.001" step="0.001" required placeholder="0"
                               x-bind:name="`items[${index}][quantity]`" x-model="row.quantity" aria-label="Jumlah">
                        <span class="input-addon w-14 justify-center" x-text="unit(row.ingredient_id) || '–'"></span>
                    </div>
                    <div class="input-group">
                        <span class="input-addon">Rp</span>
                        <input type="number" class="input" min="0" step="1" required placeholder="0" inputmode="numeric"
                               x-bind:name="`items[${index}][subtotal]`" x-model="row.subtotal" aria-label="Harga beli">
                    </div>
                    <span class="self-center text-sm text-muted tabular-nums sm:text-right" x-text="unitCost(row)"></span>
                    <button type="button" class="btn btn-ghost btn-sm btn-icon justify-self-end hover:text-danger" x-on:click="removeRow(row)" x-bind:disabled="rows.length === 1" aria-label="Hapus baris">
                        <x-ui.icon name="trash-2" />
                    </button>
                </div>
            </template>

            <div class="purchase-total">
                <span class="text-sm font-semibold">Total pembelian</span>
                <span class="text-xl font-bold tabular-nums" x-text="money(total)"></span>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-warning">
                <x-ui.icon name="alert-triangle" />
                <ul class="list-inside list-disc">
                    @foreach (collect($errors->all())->unique() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="hint">Pembelian tidak dapat diubah setelah disimpan. Koreksi dilakukan lewat penyesuaian stok.</p>
            <div class="flex gap-2">
                <x-ui.button :href="route('owner.purchases.index')" variant="secondary">Batal</x-ui.button>
                <x-ui.button x-bind:disabled="submitting">Simpan pembelian</x-ui.button>
            </div>
        </div>
    </form>
@endsection
