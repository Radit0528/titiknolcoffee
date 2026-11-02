@extends('layouts.owner')

@section('title', 'Resep '.$product->name)

@use('App\Support\Format')

@section('content')
    <x-ui.page-header :title="'Resep '.$product->name" :subtitle="$product->category->name.' · Harga jual '.Format::rupiah($product->price)"
                      :back="route('owner.recipes.index')" back-label="Resep & HPP">
        <x-ui.button :href="route('owner.products.edit', $product)" variant="secondary" icon="pencil">Ubah produk</x-ui.button>
    </x-ui.page-header>

    @if ($ingredientOptions->isEmpty())
        <div class="alert alert-warning mb-6">
            <x-ui.icon name="alert-triangle" />
            <p>Belum ada bahan baku. <a href="{{ route('owner.ingredients.create') }}" class="font-semibold underline">Tambah bahan baku</a> terlebih dahulu.</p>
        </div>
    @endif

    <form method="POST" action="{{ route('owner.recipes.update', $product) }}"
          x-data="recipeEditor(@js($ingredientOptions), @js($initialItems), {{ (float) $product->price }})"
          class="grid gap-6 lg:grid-cols-3">
        @csrf
        @method('PUT')

        <div class="flex flex-col gap-6 lg:col-span-2">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Bahan per 1 porsi</h2>
                    <x-ui.button type="button" variant="secondary" size="sm" icon="plus" x-on:click="add">Tambah bahan</x-ui.button>
                </div>

                <div class="recipe-row-head">
                    <span>Bahan baku</span><span>Takaran</span><span class="text-right">Biaya</span><span class="w-8"></span>
                </div>

                <template x-for="(row, index) in rows" x-bind:key="row.key">
                    <div class="recipe-row">
                        <select class="select" x-bind:name="`items[${index}][ingredient_id]`" x-model="row.ingredient_id" required aria-label="Bahan baku">
                            <option value="">Pilih bahan</option>
                            <template x-for="ingredient in ingredients" x-bind:key="ingredient.id">
                                <option x-bind:value="String(ingredient.id)" x-text="ingredient.name"
                                        x-bind:disabled="isTaken(ingredient.id, row)"
                                        x-bind:selected="String(ingredient.id) === row.ingredient_id"></option>
                            </template>
                        </select>
                        <button type="button" class="btn btn-ghost btn-sm btn-icon self-center hover:text-danger sm:order-last" x-on:click="removeRow(row)" aria-label="Hapus baris">
                            <x-ui.icon name="trash-2" />
                        </button>
                        <div class="input-group">
                            <input type="number" class="input" min="0.001" step="0.001" required placeholder="0"
                                   x-bind:name="`items[${index}][quantity]`" x-model="row.quantity" aria-label="Takaran">
                            <span class="input-addon w-14 justify-center" x-text="unit(row.ingredient_id) || '–'"></span>
                        </div>
                        <span class="self-center text-right text-sm font-medium tabular-nums" x-text="money(lineCost(row))"></span>
                    </div>
                </template>

                <p class="px-5 py-8 text-center text-sm text-muted" x-show="rows.length === 0" x-cloak>
                    Resep kosong. Produk tanpa resep tidak memotong stok saat terjual.
                </p>
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

            <div class="card">
                <div class="card-body">
                    <x-form.field label="Catatan penyajian" for="notes" hint="Opsional, misalnya urutan penuangan.">
                        <textarea id="notes" name="notes" rows="3" maxlength="1000" class="textarea">{{ old('notes', $product->recipe?->notes) }}</textarea>
                    </x-form.field>
                </div>
            </div>
        </div>

        <aside class="flex flex-col gap-4 lg:sticky lg:top-24 lg:self-start">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Perhitungan HPP</h2></div>
                <dl class="costing-list px-5 py-2">
                    <div><dt>Harga jual</dt><dd x-text="money(price)"></dd></div>
                    <div><dt>HPP per porsi</dt><dd x-text="money(hpp)"></dd></div>
                    <div>
                        <dt>Estimasi keuntungan</dt>
                        <dd x-bind:class="margin < 0 ? 'text-danger' : 'text-success'" x-text="money(margin)"></dd>
                    </div>
                </dl>
                <div class="px-5 pb-5">
                    <div class="flex justify-between text-xs text-muted">
                        <span>Margin</span>
                        <span x-text="marginPercent.toFixed(1).replace('.', ',') + '%'"></span>
                    </div>
                    <div class="margin-bar"><span x-bind:style="{ width: marginWidth }"></span></div>
                </div>
            </div>
            <p class="hint px-1">Angka di atas pratinjau. HPP tersimpan dihitung ulang oleh server: {{ $costing['has_recipe'] ? Format::rupiah($costing['hpp']) : 'belum ada resep' }}.</p>
            <x-ui.button class="w-full">Simpan resep</x-ui.button>
        </aside>
    </form>
@endsection
