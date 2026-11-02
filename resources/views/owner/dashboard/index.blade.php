@extends('layouts.owner')

@section('title', 'Dashboard')

@section('content')
    <x-ui.page-header title="Dashboard" :subtitle="'Ringkasan kedai · '.now()->translatedFormat('l, d F Y')" />

    <div class="alert alert-info mb-6">
        <x-ui.icon name="clipboard-list" />
        <p>Angka penjualan (omzet, transaksi, laba kotor, menu terlaris) akan tampil di sini setelah modul kasir dan laporan aktif.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card label="Nilai persediaan" icon="wallet" :value="\App\Support\Format::rupiah($inventoryValue)" note="Stok × harga modal per satuan" />
        <x-ui.stat-card label="Bahan menipis" icon="alert-triangle" :value="$lowStockCount" note="Di bawah atau sama dengan minimum stok" />
        <x-ui.stat-card label="Produk tersedia" icon="coffee" :value="$availableProductCount.' / '.$productCount" note="Produk yang dapat dijual" />
        <x-ui.stat-card label="Pembelian bulan ini" icon="shopping-basket" :value="\App\Support\Format::rupiah($purchasesThisMonth)" :note="$activeCategoryCount.' kategori menu aktif'" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-5">
        <section class="card lg:col-span-3">
            <div class="card-header">
                <h2 class="card-title">Bahan perlu dibeli</h2>
                <a href="{{ route('owner.purchases.create') }}" class="text-xs font-semibold text-accent hover:underline">Catat pembelian</a>
            </div>
            @if ($lowStockIngredients->isEmpty())
                <x-ui.empty-state icon="boxes" title="Semua stok aman" description="Tidak ada bahan di bawah minimum stok." />
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Bahan</th><th class="num">Stok</th><th class="num">Minimum</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($lowStockIngredients as $ingredient)
                                <tr>
                                    <td class="font-medium">{{ $ingredient->name }}</td>
                                    <td class="num">{{ \App\Support\Format::qty($ingredient->stock) }} {{ $ingredient->unit->label() }}</td>
                                    <td class="num text-muted">{{ \App\Support\Format::qty($ingredient->min_stock) }}</td>
                                    <td><x-ui.badge :variant="$ingredient->stockStatus()->badge()">{{ $ingredient->stockStatus()->label() }}</x-ui.badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card lg:col-span-2">
            <div class="card-header">
                <h2 class="card-title">Produk tanpa resep</h2>
                <a href="{{ route('owner.recipes.index') }}" class="text-xs font-semibold text-accent hover:underline">Kelola resep</a>
            </div>
            @if ($productsWithoutRecipe->isEmpty())
                <x-ui.empty-state icon="book-open-text" title="Semua produk punya resep" description="HPP setiap produk sudah dapat dihitung." />
            @else
                <p class="px-5 pt-4 text-xs text-muted">HPP produk ini belum bisa dihitung dan stoknya tidak akan terpotong saat terjual.</p>
                <ul class="divide-y divide-line">
                    @foreach ($productsWithoutRecipe as $product)
                        <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                            <span class="font-medium">{{ $product->name }}</span>
                            <x-ui.button :href="route('owner.recipes.edit', $product)" variant="secondary" size="sm">Atur resep</x-ui.button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
