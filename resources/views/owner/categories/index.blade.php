@extends('layouts.owner')

@section('title', 'Kategori')

@section('content')
    <x-ui.page-header title="Kategori Menu" subtitle="Urutan di sini menjadi urutan tab kategori di layar kasir.">
        <x-ui.button :href="route('owner.categories.create')" icon="plus">Tambah kategori</x-ui.button>
    </x-ui.page-header>

    <div class="card">
        @if ($categories->isEmpty())
            <x-ui.empty-state icon="tags" title="Belum ada kategori" description="Buat kategori seperti Kopi, Non-Kopi, atau Makanan sebelum menambahkan produk.">
                <x-ui.button :href="route('owner.categories.create')" icon="plus">Tambah kategori</x-ui.button>
            </x-ui.empty-state>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="w-24">Urutan</th>
                            <th>Nama</th>
                            <th class="num">Produk</th>
                            <th>Status</th>
                            <th class="w-28"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($categories as $category)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-1">
                                        <form method="POST" action="{{ route('owner.categories.move', [$category, 'up']) }}">
                                            @csrf
                                            <x-ui.button variant="ghost" size="sm" class="btn-icon" icon="chevron-up" aria-label="Naikkan" :disabled="$loop->first" />
                                        </form>
                                        <form method="POST" action="{{ route('owner.categories.move', [$category, 'down']) }}">
                                            @csrf
                                            <x-ui.button variant="ghost" size="sm" class="btn-icon" icon="chevron-down" aria-label="Turunkan" :disabled="$loop->last" />
                                        </form>
                                    </div>
                                </td>
                                <td class="font-medium">{{ $category->name }}</td>
                                <td class="num">{{ $category->products_count }}</td>
                                <td>
                                    <x-ui.badge :variant="$category->is_active ? 'success' : 'neutral'">{{ $category->is_active ? 'Aktif' : 'Nonaktif' }}</x-ui.badge>
                                </td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        <x-ui.button :href="route('owner.categories.edit', $category)" variant="ghost" size="sm" class="btn-icon" icon="pencil" aria-label="Ubah" />
                                        <x-ui.modal :action="route('owner.categories.destroy', $category)" title="Hapus kategori {{ $category->name }}?"
                                                    message="Kategori yang masih memiliki produk tidak dapat dihapus. Nonaktifkan saja jika tidak ingin ditampilkan." />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
