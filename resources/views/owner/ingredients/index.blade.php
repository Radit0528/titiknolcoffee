@extends('layouts.owner')

@section('title', 'Bahan Baku')

@use('App\Support\Format')
@use('App\Enums\StockStatus')

@section('content')
    <x-ui.page-header title="Bahan Baku" subtitle="Stok, minimum stok, dan harga modal setiap bahan.">
        <x-ui.button :href="route('owner.ingredients.create')" icon="plus">Tambah bahan</x-ui.button>
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <x-ui.stat-card label="Nilai persediaan" icon="wallet" :value="Format::rupiah($inventoryValue)" />
        <x-ui.stat-card label="Bahan menipis / habis" icon="alert-triangle" :value="$lowCount" />
    </div>

    <form method="GET" class="mb-4 flex flex-col gap-2 sm:flex-row">
        <div class="search-field flex-1">
            <x-ui.icon name="search" />
            <input type="search" name="q" value="{{ $search }}" placeholder="Cari bahan…" class="input">
        </div>
        <select name="status" class="select sm:w-44" x-data x-on:change="$el.form.submit()">
            <option value="">Semua status</option>
            @foreach (StockStatus::cases() as $case)
                <option value="{{ $case->value }}" @selected($status === $case)>{{ $case->label() }}</option>
            @endforeach
        </select>
        <x-ui.button variant="secondary">Cari</x-ui.button>
    </form>

    <div class="card">
        @if ($ingredients->isEmpty())
            @if ($search !== '' || $status)
                <x-ui.empty-state icon="search" title="Bahan tidak ditemukan" />
            @else
                <x-ui.empty-state icon="boxes" title="Belum ada bahan baku" description="Catat bahan seperti biji kopi, susu, dan gula aren beserta harga modalnya.">
                    <x-ui.button :href="route('owner.ingredients.create')" icon="plus">Tambah bahan</x-ui.button>
                </x-ui.empty-state>
            @endif
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Bahan</th>
                            <th class="num">Stok</th>
                            <th class="num">Minimum</th>
                            <th class="num">Harga modal</th>
                            <th class="num">Nilai stok</th>
                            <th>Status</th>
                            <th class="w-36"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ingredients as $ingredient)
                            @php($state = $ingredient->stockStatus())
                            <tr>
                                <td>
                                    <p class="font-medium">{{ $ingredient->name }}</p>
                                    <p class="text-xs text-muted">{{ $ingredient->supplier?->name ?? 'Tanpa supplier' }}</p>
                                </td>
                                <td class="num font-medium">{{ Format::qty($ingredient->stock) }} <span class="text-xs text-muted">{{ $ingredient->unit->label() }}</span></td>
                                <td class="num text-muted">{{ Format::qty($ingredient->min_stock) }}</td>
                                <td class="num">{{ Format::unitCost($ingredient->cost_per_unit) }}<span class="text-xs text-muted">/{{ $ingredient->unit->label() }}</span></td>
                                <td class="num">{{ Format::rupiah($ingredient->stockValue()) }}</td>
                                <td><x-ui.badge :variant="$state->badge()">{{ $state->label() }}</x-ui.badge></td>
                                <td>

                                    <div class="flex justify-end gap-1">
                                        <x-ui.button :href="route('owner.purchases.create', ['ingredient' => $ingredient->id])" variant="ghost" size="sm" class="btn-icon" icon="shopping-basket" aria-label="Catat pembelian" title="Catat pembelian" />
                                        <x-ui.button :href="route('owner.stock.out.create', ['ingredient' => $ingredient->id])" variant="ghost" size="sm" class="btn-icon" icon="package-minus" aria-label="Stok keluar" title="Stok keluar" />
                                        <x-ui.button :href="route('owner.ingredients.edit', $ingredient)" variant="ghost" size="sm" class="btn-icon" icon="pencil" aria-label="Ubah" />
                                        <x-ui.modal :action="route('owner.ingredients.destroy', $ingredient)" title="Hapus {{ $ingredient->name }}?"
                                                    message="Bahan yang masih dipakai resep tidak dapat dihapus. Riwayat mutasi stoknya tetap tersimpan." />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($ingredients->hasPages())
                <div class="pagination-wrap">{{ $ingredients->links() }}</div>
            @endif
        @endif
    </div>
@endsection
