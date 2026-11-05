{{-- Shift money breakdown: how the expected cash is reached. --}}
@props(['summary'])
@use('App\Support\Format')

<dl {{ $attributes->class('costing-list') }}>
    <div><dt>Modal awal</dt><dd>{{ Format::rupiah($summary['opening_cash']) }}</dd></div>
    <div><dt>Penjualan tunai</dt><dd>+{{ Format::rupiah($summary['cash_sales']) }}</dd></div>
    <div><dt>Kas masuk</dt><dd>+{{ Format::rupiah($summary['cash_in']) }}</dd></div>
    <div><dt>Kas keluar</dt><dd>-{{ Format::rupiah($summary['cash_out']) }}</dd></div>
    <div class="!border-t-2 !border-coffee-900/10"><dt class="!font-semibold !text-ink">Uang seharusnya di laci</dt><dd class="text-lg">{{ Format::rupiah($summary['expected_cash']) }}</dd></div>
    @if ($summary['qris_sales'] > 0)
        <div><dt>Penjualan QRIS <span class="hint">(tidak masuk laci)</span></dt><dd>{{ Format::rupiah($summary['qris_sales']) }}</dd></div>
    @endif
    @if (($summary['void_count'] ?? 0) > 0)
        <div><dt>Transaksi dibatalkan <span class="hint">({{ $summary['void_count'] }}×, sudah dikeluarkan dari penjualan)</span></dt><dd>{{ Format::rupiah($summary['void_total']) }}</dd></div>
    @endif
</dl>
