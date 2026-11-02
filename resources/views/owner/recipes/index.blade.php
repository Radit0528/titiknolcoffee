@extends('layouts.owner')

@section('title', 'Resep & HPP')

@use('App\Support\Format')

@section('content')
    <x-ui.page-header title="Resep & HPP" subtitle="HPP dihitung dari takaran resep × harga modal bahan saat ini." />

    <form method="GET" class="mb-4 flex gap-2">
        <div class="search-field flex-1">
            <x-ui.icon name="search" />
            <input type="search" name="q" value="{{ $search }}" placeholder="Cari produk…" class="input">
        </div>
        <x-ui.button variant="secondary">Cari</x-ui.button>
    </form>

    <div class="card">
        @if ($products->isEmpty())
            <x-ui.empty-state icon="book-open-text" title="Belum ada produk" description="Tambahkan produk terlebih dahulu, lalu atur resepnya di sini.">
                <x-ui.button :href="route('owner.products.create')" icon="plus">Tambah produk</x-ui.button>
            </x-ui.empty-state>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Produk</th><th class="num">Bahan</th><th class="num">Harga jual</th><th class="num">HPP</th><th class="num">Margin</th><th class="w-32"></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $product)
                            @php($c = $costing[$product->id])
                            <tr>
                                <td>
                                    <p class="font-medium">{{ $product->name }}</p>
                                    <p class="text-xs text-muted">{{ $product->category->name }}</p>
                                </td>
                                <td class="num">{{ $product->recipe?->items->count() ?? 0 }}</td>
                                <td class="num">{{ Format::rupiah($product->price) }}</td>
                                <td class="num">{{ $c['has_recipe'] ? Format::rupiah($c['hpp']) : '–' }}</td>
                                <td class="num">
                                    @if ($c['has_recipe'])
                                        <span @class(['font-semibold', 'text-danger' => $c['margin'] < 0])>{{ number_format($c['margin_percent'] ?? 0, 1, ',', '.') }}%</span>
                                    @else
                                        <x-ui.badge variant="warning">Belum ada</x-ui.badge>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <x-ui.button :href="route('owner.recipes.edit', $product)" variant="secondary" size="sm">{{ $c['has_recipe'] ? 'Ubah resep' : 'Atur resep' }}</x-ui.button>
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
