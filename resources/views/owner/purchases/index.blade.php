@extends('layouts.owner')

@section('title', 'Pembelian')

@use('App\Support\Format')

@section('content')
    <x-ui.page-header title="Pembelian Bahan" subtitle="Setiap pembelian menambah stok dan memperbarui harga modal rata-rata.">
        <x-ui.button :href="route('owner.purchases.create')" icon="plus">Catat pembelian</x-ui.button>
    </x-ui.page-header>

    <form method="GET" class="filter-bar">
        <x-form.field label="Dari" for="from">
            <input id="from" type="date" name="from" value="{{ $from }}" class="input">
        </x-form.field>
        <x-form.field label="Sampai" for="to">
            <input id="to" type="date" name="to" value="{{ $to }}" class="input">
        </x-form.field>
        <div class="flex gap-2">
            <x-ui.button variant="secondary">Terapkan</x-ui.button>
            @if ($from || $to)
                <x-ui.button :href="route('owner.purchases.index')" variant="ghost">Reset</x-ui.button>
            @endif
        </div>
    </form>

    <div class="card">
        @if ($purchases->isEmpty())
            <x-ui.empty-state icon="shopping-basket" title="Belum ada pembelian" description="Catat pembelian bahan agar stok dan harga modal selalu terbaru.">
                <x-ui.button :href="route('owner.purchases.create')" icon="plus">Catat pembelian</x-ui.button>
            </x-ui.empty-state>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Kode</th><th>Tanggal</th><th class="num">Bahan</th><th class="num">Total</th><th>Dicatat oleh</th><th class="w-16"></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($purchases as $purchase)
                            <tr>
                                <td class="font-medium">
                                    {{ $purchase->code }}
                                    @if ($purchase->invoice_number)<p class="text-xs text-muted">Nota {{ $purchase->invoice_number }}</p>@endif
                                </td>
                                <td>{{ $purchase->purchased_at->translatedFormat('d M Y') }}</td>
                                <td class="num">{{ $purchase->items_count }}</td>
                                <td class="num font-semibold">{{ Format::rupiah($purchase->total) }}</td>
                                <td class="text-muted">{{ $purchase->user?->name ?? '–' }}</td>
                                <td class="text-right">
                                    <x-ui.button :href="route('owner.purchases.show', $purchase)" variant="ghost" size="sm" class="btn-icon" icon="eye" aria-label="Lihat detail" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="border-t border-line px-4 py-3 text-sm font-semibold">Total periode ini</td>
                            <td class="num border-t border-line px-4 py-3 font-bold">{{ Format::rupiah($periodTotal) }}</td>
                            <td colspan="2" class="border-t border-line"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @if ($purchases->hasPages())
                <div class="pagination-wrap">{{ $purchases->links() }}</div>
            @endif
        @endif
    </div>
@endsection
