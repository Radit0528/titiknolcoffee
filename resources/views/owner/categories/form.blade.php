@extends('layouts.owner')

@section('title', $category->exists ? 'Ubah Kategori' : 'Tambah Kategori')

@section('content')
    <x-ui.page-header :title="$category->exists ? 'Ubah Kategori' : 'Tambah Kategori'" :back="route('owner.categories.index')" back-label="Kategori" />

    <form method="POST" action="{{ $category->exists ? route('owner.categories.update', $category) : route('owner.categories.store') }}" class="card max-w-xl">
        @csrf
        @if ($category->exists) @method('PUT') @endif

        <div class="card-body flex flex-col gap-5">
            <x-form.field label="Nama kategori" for="name" required>
                <input id="name" name="name" type="text" maxlength="100" required value="{{ old('name', $category->name) }}"
                       @class(['input', 'input-invalid' => $errors->has('name')])>
            </x-form.field>

            <x-form.field label="Urutan" for="sort_order" hint="Angka kecil tampil lebih dulu." required>
                <input id="sort_order" name="sort_order" type="number" min="0" max="9999" required value="{{ old('sort_order', $category->sort_order) }}"
                       @class(['input max-w-32', 'input-invalid' => $errors->has('sort_order')])>
            </x-form.field>

            <x-form.switch name="is_active" :checked="old('is_active', $category->is_active)" label="Aktif" description="Kategori nonaktif tidak muncul di layar kasir." />
        </div>

        <div class="flex justify-end gap-2 border-t border-line px-5 py-4">
            <x-ui.button :href="route('owner.categories.index')" variant="secondary">Batal</x-ui.button>
            <x-ui.button>Simpan</x-ui.button>
        </div>
    </form>
@endsection
