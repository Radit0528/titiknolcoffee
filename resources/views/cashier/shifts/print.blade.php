<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Shift #{{ $shift->id }}</title>
    @vite(['resources/css/print.css', 'resources/js/cashier/receipt.js'])
</head>
<body>
    @use('App\Support\Format')
    <div class="print-actions">
        <button type="button" data-print>Cetak ulang</button>
        <a href="{{ route('shifts.show', $shift) }}">Kembali</a>
    </div>

    <div class="receipt receipt-{{ $width }}">
        <div class="receipt-center">
            <div class="receipt-store">{{ $storeName }}</div>
            <div>LAPORAN SHIFT</div>
        </div>
        <hr class="receipt-rule">
        <div class="receipt-row"><span>Kasir</span><span>{{ $shift->user->name }}</span></div>
        <div class="receipt-row"><span>Buka</span><span>{{ $shift->opened_at->format('d/m/y H:i') }}</span></div>
        <div class="receipt-row"><span>Tutup</span><span>{{ $shift->closed_at?->format('d/m/y H:i') ?? '-' }}</span></div>
        <hr class="receipt-rule">
        <div class="receipt-row"><span>Transaksi</span><span>{{ $summary['order_count'] }}</span></div>
        <div class="receipt-row"><span>Item</span><span>{{ $summary['item_count'] }}</span></div>
        <div class="receipt-row"><span>Diskon</span><span>{{ Format::rupiah($summary['discount']) }}</span></div>
        <div class="receipt-row"><span>Tunai</span><span>{{ Format::rupiah($summary['cash_sales']) }}</span></div>
        @if ($summary['qris_sales'] > 0)
            <div class="receipt-row"><span>QRIS</span><span>{{ Format::rupiah($summary['qris_sales']) }}</span></div>
        @endif
        <div class="receipt-row receipt-total"><span>PENJUALAN</span><span>{{ Format::rupiah($summary['total_sales']) }}</span></div>
        <hr class="receipt-rule">
        <div class="receipt-row"><span>Modal awal</span><span>{{ Format::rupiah($summary['opening_cash']) }}</span></div>
        <div class="receipt-row"><span>+ Tunai</span><span>{{ Format::rupiah($summary['cash_sales']) }}</span></div>
        <div class="receipt-row"><span>+ Kas masuk</span><span>{{ Format::rupiah($summary['cash_in']) }}</span></div>
        <div class="receipt-row"><span>- Kas keluar</span><span>{{ Format::rupiah($summary['cash_out']) }}</span></div>
        <div class="receipt-row receipt-total"><span>SEHARUSNYA</span><span>{{ Format::rupiah($summary['expected_cash']) }}</span></div>
        @unless ($shift->isOpen())
            <div class="receipt-row"><span>Uang fisik</span><span>{{ Format::rupiah($shift->closing_cash) }}</span></div>
            <div class="receipt-row receipt-total"><span>SELISIH</span><span>{{ (float) $shift->cash_variance > 0 ? '+' : '' }}{{ Format::rupiah($shift->cash_variance) }}</span></div>
            @if ($shift->closing_notes)
                <div class="receipt-note">{{ $shift->closing_notes }}</div>
            @endif
        @endunless
        @if ($movements->isNotEmpty())
            <hr class="receipt-rule">
            @foreach ($movements as $movement)
                <div class="receipt-row"><span>{{ $movement->type->value === 'in' ? '+' : '-' }} {{ $movement->reason }}</span><span>{{ number_format((float) $movement->amount, 0, ',', '.') }}</span></div>
            @endforeach
        @endif
        <hr class="receipt-rule">
        <div class="receipt-center receipt-muted">Dicetak {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</body>
</html>
