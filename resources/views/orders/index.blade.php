@extends($layout)

@section('title', 'Riwayat Transaksi')

@use('App\Support\Format')

@section('content')
    <x-ui.page-header title="Riwayat Transaksi" :subtitle="auth()->user()->isOwner() ? 'Semua transaksi dari seluruh kasir.' : 'Transaksi yang Anda layani.'" />

    <form method="GET" class="mb-4 flex gap-2">
        <div class="search-field flex-1">
            <x-ui.icon name="search" />
            <input type="search" name="q" value="{{ $search }}" placeholder="Cari nomor transaksi atau nama pelanggan…" class="input">
        </div>
        <x-ui.button variant="secondary">Cari</x-ui.button>
    </form>

    <div class="card">
        @if ($orders->isEmpty())
            <x-ui.empty-state icon="receipt-text" title="Belum ada transaksi" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No. transaksi</th><th>Waktu</th>
                            @if (auth()->user()->isOwner())<th>Kasir</th>@endif
                            <th>Pelanggan</th><th class="num">Item</th><th>Metode</th><th class="num">Total</th><th>Status</th><th class="w-16"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td class="font-medium">{{ $order->code }}</td>
                                <td class="whitespace-nowrap">{{ ($order->paid_at ?? $order->created_at)->translatedFormat('d M Y H:i') }}</td>
                                @if (auth()->user()->isOwner())<td>{{ $order->user->name }}</td>@endif
                                <td>{{ $order->customer_name ?? '–' }}</td>
                                <td class="num">{{ (int) $order->item_count }}</td>
                                <td>{{ $order->payment?->method->label() ?? '–' }}</td>
                                <td class="num font-semibold">{{ Format::rupiah($order->grand_total) }}</td>
                                <td><x-ui.badge :variant="$order->status->badge()">{{ $order->status->label() }}</x-ui.badge></td>
                                <td class="text-right">
                                    <x-ui.button :href="route('orders.show', $order)" variant="ghost" size="sm" class="btn-icon" icon="eye" aria-label="Lihat struk" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($orders->hasPages())
                <div class="pagination-wrap">{{ $orders->links() }}</div>
            @endif
        @endif
    </div>
@endsection
