@extends('layouts.owner')

@section('title', 'Produk')

@use('App\Support\Format')

@section('content')
    <x-ui.page-header title="Produk" subtitle="Daftar menu, harga jual, dan estimasi margin dari resep.">
        <x-ui.button :href="route('owner.products.create')" icon="plus">Tambah produk</x-ui.button>
    </x-ui.page-header>

    <form method="GET" class="mb-4 flex flex-col gap-2 sm:flex-row">
        <div class="search-field flex-1">
            <x-ui.icon name="search" />
            <input type="search" name="q" value="{{ $search }}" placeholder="Cari nama produk…" class="input">
        </div>
        <select name="category" class="select sm:w-52" x-data x-on:change="$el.form.submit()">
            <option value="">Semua kategori</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <x-ui.button variant="secondary">Cari</x-ui.button>
    </form>

    <div class="card">
        @if ($products->isEmpty())
            @if ($search !== '' || $categoryId)
                <x-ui.empty-state icon="search" title="Produk tidak ditemukan" description="Coba kata kunci atau kategori lain." />
            @else
                <x-ui.empty-state icon="coffee" title="Belum ada produk" description="Tambahkan menu pertama kedai Anda.">
                    <x-ui.button :href="route('owner.products.create')" icon="plus">Tambah produk</x-ui.button>
                </x-ui.empty-state>
            @endif
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th class="num">Harga jual</th>
                            <th class="num">HPP</th>
                            <th class="num">Margin</th>
                            <th>Status</th>
                            <th class="w-24"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $product)
                            @php($c = $costing[$product->id])
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="thumb">
                                            @if ($product->imageUrl())
                                                <img src="{{ $product->imageUrl() }}" alt="" loading="lazy">
                                            @else
                                                <x-ui.icon name="coffee" />
                                            @endif
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate font-medium">{{ $product->name }}</p>
                                            <p class="text-xs text-muted">{{ $product->category->name }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="num font-medium">{{ Format::rupiah($product->price) }}</td>
                                <td class="num">
                                    @if ($c['has_recipe'])
                                        {{ Format::rupiah($c['hpp']) }}
                                    @else
                                        <a href="{{ route('owner.recipes.edit', $product) }}" class="text-xs font-semibold text-warning hover:underline">Belum ada resep</a>
                                    @endif
                                </td>
                                <td class="num">
                                    @if ($c['has_recipe'])
                                        <span @class(['font-semibold', 'text-danger' => $c['margin'] < 0])>{{ Format::rupiah($c['margin']) }}</span>
                                        <span class="block text-xs text-muted">{{ $c['margin_percent'] !== null ? number_format($c['margin_percent'], 1, ',', '.').'%' : '–' }}</span>
                                    @else
                                        <span class="text-muted">–</span>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('owner.products.toggle', $product) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" title="Klik untuk mengubah status">
                                            <x-ui.badge :variant="$product->is_available ? 'success' : 'danger'">{{ $product->is_available ? 'Tersedia' : 'Habis' }}</x-ui.badge>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        <x-ui.button :href="route('owner.products.edit', $product)" variant="ghost" size="sm" class="btn-icon" icon="pencil" aria-label="Ubah" />
                                        <x-ui.modal :action="route('owner.products.destroy', $product)" title="Hapus {{ $product->name }}?"
                                                    message="Produk tidak akan tampil lagi di kasir. Riwayat transaksinya tetap tersimpan." />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($products->hasPages())
                <div class="pagination-wrap">{{ $products->links() }}</div>
            @endif
        @endif
    </div>
@endsection
