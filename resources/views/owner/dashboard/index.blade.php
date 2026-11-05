@extends('layouts.owner')

@section('title', 'Dashboard')

@use('App\Support\Format')

@section('content')
    <x-ui.page-header title="Dashboard" :subtitle="'Ringkasan kedai · '.now()->translatedFormat('l, d F Y')">
        <x-ui.button :href="route('owner.reports.index')" variant="secondary" icon="chart-column">Lihat laporan</x-ui.button>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card label="Omzet hari ini" icon="wallet" :value="Format::rupiah($todayRevenue)" :note="$todayTransactions.' transaksi'" />
        <x-ui.stat-card label="Omzet bulan ini" icon="chart-column" :value="Format::rupiah($monthRevenue)" :note="now()->translatedFormat('F Y')" />
        <x-ui.stat-card label="Laba kotor bulan ini" icon="coffee" :value="Format::rupiah($monthGrossProfit)" :note="'Margin '.number_format($monthMargin, 1, ',', '.').'% · estimasi dari HPP resep'" />
        <x-ui.stat-card label="Nilai persediaan" icon="boxes" :value="Format::rupiah($inventoryValue)" :note="$lowStockCount.' bahan menipis · pembelian bulan ini '.Format::rupiah($purchasesThisMonth)" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="card lg:col-span-2">
            <div class="card-header">
                <h2 class="card-title">Penjualan 14 hari terakhir</h2>
                <span class="text-xs text-muted">{{ Format::rupiah(array_sum($chart['revenue'])) }} · {{ array_sum($chart['transactions']) }} transaksi</span>
            </div>
            <div class="card-body">
                <div class="chart-box">
                    <canvas data-chart="{{ json_encode($chart) }}" aria-label="Grafik omzet harian" role="img"></canvas>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Menu terlaris bulan ini</h2></div>
            @if ($bestSellers->isEmpty())
                <x-ui.empty-state icon="coffee" title="Belum ada penjualan" description="Menu terlaris tampil setelah ada transaksi bulan ini." />
            @else
                <ol class="divide-y divide-line">
                    @foreach ($bestSellers as $i => $item)
                        <li class="flex items-center gap-3 px-5 py-3 text-sm">
                            <span class="grid size-7 shrink-0 place-items-center rounded-full bg-coffee-50 text-xs font-bold text-coffee-700">{{ $i + 1 }}</span>
                            <span class="flex-1 font-medium">{{ $item->name }}</span>
                            <span class="text-right tabular-nums">
                                <span class="block font-semibold">{{ (int) $item->qty }} terjual</span>
                                <span class="text-xs text-muted">{{ Format::rupiah($item->sales) }}</span>
                            </span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="card lg:col-span-2">
            <div class="card-header">
                <h2 class="card-title">Transaksi terbaru</h2>
                <a href="{{ route('orders.index') }}" class="text-xs font-semibold text-accent hover:underline">Lihat semua</a>
            </div>
            @if ($recentOrders->isEmpty())
                <x-ui.empty-state icon="receipt-text" title="Belum ada transaksi" />
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>No. transaksi</th><th>Waktu</th><th>Kasir</th><th>Metode</th><th class="num">Total</th></tr></thead>
                        <tbody>
                            @foreach ($recentOrders as $order)
                                <tr>
                                    <td class="font-medium"><a href="{{ route('orders.show', $order) }}" class="hover:underline">{{ $order->code }}</a></td>
                                    <td class="whitespace-nowrap">{{ $order->paid_at->translatedFormat('d M H:i') }}</td>
                                    <td>{{ $order->user->name }}</td>
                                    <td>{{ $order->payment?->method->label() ?? '–' }}</td>
                                    <td class="num font-semibold">{{ Format::rupiah($order->grand_total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card">
            <div class="card-header">
                <h2 class="card-title">Bahan perlu dibeli</h2>
                <a href="{{ route('owner.purchases.create') }}" class="text-xs font-semibold text-accent hover:underline">Catat pembelian</a>
            </div>
            @if ($lowStockIngredients->isEmpty())
                <x-ui.empty-state icon="boxes" title="Semua stok aman" />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($lowStockIngredients as $ingredient)
                        <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                            <span>
                                <span class="block font-medium">{{ $ingredient->name }}</span>
                                <span class="text-xs text-muted">{{ Format::qty($ingredient->stock) }} / min. {{ Format::qty($ingredient->min_stock) }} {{ $ingredient->unit->label() }}</span>
                            </span>
                            <x-ui.badge :variant="$ingredient->stockStatus()->badge()">{{ $ingredient->stockStatus()->label() }}</x-ui.badge>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($productsWithoutRecipe->isNotEmpty())
                <div class="border-t border-line px-5 py-3">
                    <p class="mb-1 text-xs font-semibold text-warning">Produk tanpa resep (HPP belum terhitung)</p>
                    <p class="text-sm">
                        @foreach ($productsWithoutRecipe as $product)
                            <a href="{{ route('owner.recipes.edit', $product) }}" class="hover:underline">{{ $product->name }}</a>@if (! $loop->last), @endif
                        @endforeach
                    </p>
                </div>
            @endif
        </section>
    </div>
@endsection
