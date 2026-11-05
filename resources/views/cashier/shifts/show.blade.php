@extends($layout)

@section('title', 'Laporan Shift')

@use('App\Support\Format')

@section('content')
    <x-ui.page-header :title="'Laporan Shift · '.$shift->user->name"
                      :subtitle="$shift->opened_at->translatedFormat('d M Y H:i').' – '.($shift->closed_at?->translatedFormat('d M Y H:i') ?? 'masih berjalan')">
        <x-ui.button :href="route('shifts.print', $shift)" variant="secondary" icon="printer" target="_blank">Cetak</x-ui.button>
        @if (auth()->user()->isCashier() && ! $shift->isOpen())
            <x-ui.button :href="route('cashier.shift.create')" icon="plus">Buka shift baru</x-ui.button>
        @endif
    </x-ui.page-header>

    @if (! $shift->isOpen())
        @php($variance = (int) round((float) $shift->cash_variance))
        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <x-ui.stat-card label="Uang seharusnya" icon="wallet" :value="Format::rupiah($shift->expected_cash)" />
            <x-ui.stat-card label="Uang fisik" icon="wallet" :value="Format::rupiah($shift->closing_cash)" />
            <x-ui.stat-card label="Selisih kas" icon="alert-triangle"
                            :value="($variance > 0 ? '+' : '').Format::rupiah($variance)"
                            :note="$variance === 0 ? 'Kas sesuai' : ($variance > 0 ? 'Uang lebih' : 'Uang kurang')" />
        </div>
        @if ($shift->closing_notes)
            <div class="alert alert-info mb-6"><x-ui.icon name="sticky-note" /><p>{{ $shift->closing_notes }}</p></div>
        @endif
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card">
            <div class="card-header"><h2 class="card-title">Rincian kas</h2></div>
            <x-shift.summary :summary="$summary" class="px-5 py-2" />
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Penjualan</h2></div>
            <dl class="costing-list px-5 py-2">
                <div><dt>Transaksi lunas</dt><dd>{{ $summary['order_count'] }}</dd></div>
                <div><dt>Item terjual</dt><dd>{{ $summary['item_count'] }}</dd></div>
                <div><dt>Total diskon</dt><dd>{{ Format::rupiah($summary['discount']) }}</dd></div>
                <div><dt>Total penjualan</dt><dd>{{ Format::rupiah($summary['total_sales']) }}</dd></div>
            </dl>
            @if ($movements->isNotEmpty())
                <div class="border-t border-line px-5 py-3">
                    <p class="mb-2 text-xs font-semibold tracking-wide text-muted uppercase">Kas masuk / keluar</p>
                    <ul class="text-sm">
                        @foreach ($movements as $movement)
                            <li class="flex justify-between gap-3 py-1">
                                <span>{{ $movement->created_at->format('H:i') }} · {{ $movement->reason }}</span>
                                <span class="tabular-nums">{{ $movement->type->value === 'in' ? '+' : '-' }}{{ Format::rupiah($movement->amount) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
    </div>
@endsection
