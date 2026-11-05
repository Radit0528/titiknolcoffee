@extends('layouts.owner')

@section('title', $banner->exists ? 'Ubah Banner' : 'Buat Banner')

@section('content')
    <x-ui.page-header :title="$banner->exists ? 'Ubah Banner' : 'Buat Banner'" :back="route('owner.banners.index')" back-label="Banner promo" />

    <form method="POST" action="{{ $banner->exists ? route('owner.banners.update', $banner) : route('owner.banners.store') }}" enctype="multipart/form-data"
          class="grid max-w-5xl gap-6 lg:grid-cols-[1fr_22rem]">
        @csrf
        @if ($banner->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-body flex flex-col gap-5">
                <x-form.field label="Judul" for="title" required>
                    <input id="title" name="title" type="text" maxlength="100" required value="{{ old('title', $banner->title) }}" @class(['input', 'input-invalid' => $errors->has('title')])>
                </x-form.field>
                <x-form.field label="Keterangan" for="description" hint="Opsional, maks. 255 karakter. Contoh: Diskon 10% pakai kode NGOPIPAGI.">
                    <textarea id="description" name="description" rows="2" maxlength="255" @class(['input h-auto py-2', 'input-invalid' => $errors->has('description')])>{{ old('description', $banner->description) }}</textarea>
                </x-form.field>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.field label="Mulai tayang" for="starts_at" hint="Kosongkan untuk tayang sekarang.">
                        <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $banner->starts_at?->format('Y-m-d\TH:i')) }}" @class(['input', 'input-invalid' => $errors->has('starts_at')])>
                    </x-form.field>
                    <x-form.field label="Selesai tayang" for="ends_at" hint="Kosongkan jika tanpa batas.">
                        <input id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', $banner->ends_at?->format('Y-m-d\TH:i')) }}" @class(['input', 'input-invalid' => $errors->has('ends_at')])>
                    </x-form.field>
                </div>
                <x-form.field label="Urutan" for="sort_order" hint="Angka kecil tampil lebih dulu.">
                    <input id="sort_order" name="sort_order" type="number" min="0" max="999" step="1" value="{{ old('sort_order', $banner->sort_order) }}" @class(['input sm:max-w-32', 'input-invalid' => $errors->has('sort_order')])>
                </x-form.field>
                <x-form.switch name="is_active" :checked="old('is_active', $banner->is_active)" label="Aktif" description="Banner nonaktif tidak tampil di kasir walaupun masih dalam periode." />
            </div>
            <div class="flex justify-end gap-2 border-t border-line px-5 py-4">
                <x-ui.button :href="route('owner.banners.index')" variant="secondary">Batal</x-ui.button>
                <x-ui.button>Simpan</x-ui.button>
            </div>
        </div>

        <div class="card self-start" x-data="imagePicker(@js($banner->exists ? $banner->imageUrl() : null))">
            <div class="card-header"><h2 class="card-title">Gambar{{ $banner->exists ? '' : ' *' }}</h2></div>
            <div class="card-body flex flex-col gap-3">
                <div class="grid aspect-[16/7] place-items-center overflow-hidden rounded-lg border border-dashed border-line bg-coffee-50 text-coffee-300">
                    <img x-show="preview" x-bind:src="preview" alt="" class="size-full object-cover" x-cloak>
                    <span x-show="!preview"><x-ui.icon name="image-plus" class="size-8" /></span>
                </div>
                <input x-ref="file" id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" @required(! $banner->exists)
                       class="hint file:mr-3 file:rounded-md file:border-0 file:bg-coffee-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-coffee-900" x-on:change="pick">
                <p class="hint">JPG, PNG, atau WebP. Maks. 2 MB. Rasio lebar ±16:7 tampil paling pas.</p>
                <p class="field-error" x-show="error" x-text="error" x-cloak></p>
                @error('image') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </form>
@endsection
