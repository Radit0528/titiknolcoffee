@extends('layouts.owner')

@section('title', $product->exists ? 'Ubah Produk' : 'Tambah Produk')

@use('App\Support\Format')

@section('content')
    <x-ui.page-header :title="$product->exists ? 'Ubah Produk' : 'Tambah Produk'" :back="route('owner.products.index')" back-label="Produk" />

    @if ($categories->isEmpty())
        <div class="alert alert-warning mb-6">
            <x-ui.icon name="alert-triangle" />
            <p>Belum ada kategori. <a href="{{ route('owner.categories.create') }}" class="font-semibold underline">Buat kategori</a> terlebih dahulu.</p>
        </div>
    @endif

    <form method="POST" enctype="multipart/form-data"
          action="{{ $product->exists ? route('owner.products.update', $product) : route('owner.products.store') }}"
          class="grid gap-6 lg:grid-cols-3" x-data="{ busy: false }" x-on:submit="busy = true">
        @csrf
        @if ($product->exists) @method('PUT') @endif

        <div class="card lg:col-span-2">
            <div class="card-body flex flex-col gap-5">
                <x-form.field label="Nama produk" for="name" required>
                    <input id="name" name="name" type="text" maxlength="150" required value="{{ old('name', $product->name) }}"
                           @class(['input', 'input-invalid' => $errors->has('name')])>
                </x-form.field>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.field label="Kategori" for="category_id" required>
                        <select id="category_id" name="category_id" required @class(['select', 'input-invalid' => $errors->has('category_id')])>
                            <option value="">Pilih kategori</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((int) old('category_id', $product->category_id) === $category->id)>
                                    {{ $category->name }}{{ $category->is_active ? '' : ' (nonaktif)' }}
                                </option>
                            @endforeach
                        </select>
                    </x-form.field>

                    <x-form.field label="Harga jual" for="price" required>
                        <div class="input-group">
                            <span class="input-addon">Rp</span>
                            <input id="price" name="price" type="number" min="0" step="500" required inputmode="numeric"
                                   value="{{ old('price', $product->price !== null ? (int) $product->price : '') }}"
                                   @class(['input', 'input-invalid' => $errors->has('price')])>
                        </div>
                    </x-form.field>
                </div>

                <x-form.field label="Deskripsi" for="description" hint="Opsional. Maksimal 1.000 karakter.">
                    <textarea id="description" name="description" maxlength="1000" rows="3" @class(['textarea', 'input-invalid' => $errors->has('description')])>{{ old('description', $product->description) }}</textarea>
                </x-form.field>

                <x-form.switch name="is_available" :checked="old('is_available', $product->is_available)" label="Tersedia untuk dijual"
                               description="Produk yang ditandai habis tidak bisa ditambahkan ke transaksi." />
            </div>
        </div>

        <div class="flex flex-col gap-6">
            <div class="card" x-data="imagePicker(@js($product->imageUrl()))">
                <div class="card-header"><h2 class="card-title">Gambar</h2></div>
                <div class="card-body flex flex-col gap-3">
                    <div class="grid aspect-[4/3] place-items-center overflow-hidden rounded-lg border border-dashed border-line bg-coffee-50 text-coffee-300">
                        <img x-show="preview" x-bind:src="preview" alt="" class="size-full object-cover" x-cloak>
                        <span x-show="!preview"><x-ui.icon name="image-plus" class="size-8" /></span>
                    </div>
                    <input type="hidden" name="remove_image" x-bind:value="remove ? 1 : 0">
                    <input x-ref="file" id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="hint file:mr-3 file:rounded-md file:border-0 file:bg-coffee-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-coffee-900" x-on:change="pick">
                    <p class="hint">JPG, PNG, atau WebP. Maks. 2 MB.</p>
                    <p class="field-error" x-show="error" x-text="error" x-cloak></p>
                    @error('image') <p class="field-error">{{ $message }}</p> @enderror
                    <button type="button" class="self-start text-xs font-semibold text-danger hover:underline" x-show="preview" x-cloak x-on:click="clear">Hapus gambar</button>
                </div>
            </div>

            @if ($costing)
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Estimasi biaya</h2>
                        <a href="{{ route('owner.recipes.edit', $product) }}" class="text-xs font-semibold text-accent hover:underline">Ubah resep</a>
                    </div>
                    <dl class="costing-list px-5 py-2">
                        <div><dt>HPP</dt><dd>{{ $costing['has_recipe'] ? Format::rupiah($costing['hpp']) : 'Belum ada resep' }}</dd></div>
                        <div><dt>Margin</dt><dd>{{ $costing['has_recipe'] ? Format::rupiah($costing['margin']) : '–' }}</dd></div>
                    </dl>
                </div>
            @endif

            <div class="flex gap-2">
                <x-ui.button :href="route('owner.products.index')" variant="secondary" class="flex-1">Batal</x-ui.button>
                <x-ui.button class="flex-1" x-bind:disabled="busy">{{ $product->exists ? 'Simpan perubahan' : 'Simpan & atur resep' }}</x-ui.button>
            </div>
        </div>
    </form>
@endsection
