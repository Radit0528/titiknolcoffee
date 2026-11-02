@extends('layouts.owner')

@section('title', 'Voucher')

@use('App\Support\Format')

@section('content')
    <x-ui.page-header title="Voucher & Promo" subtitle="Kode diskon yang bisa dipakai kasir saat transaksi.">
        <x-ui.button :href="route('owner.vouchers.create')" icon="plus">Buat voucher</x-ui.button>
    </x-ui.page-header>

    <div class="card">
        @if ($vouchers->isEmpty())
            <x-ui.empty-state icon="ticket" title="Belum ada voucher">
                <x-ui.button :href="route('owner.vouchers.create')" icon="plus">Buat voucher</x-ui.button>
            </x-ui.empty-state>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Kode</th><th>Diskon</th><th class="num">Min. belanja</th><th>Periode</th><th class="num">Terpakai</th><th>Status</th><th class="w-24"></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($vouchers as $voucher)
                            <tr>
                                <td>
                                    <p class="font-mono font-semibold">{{ $voucher->code }}</p>
                                    <p class="text-xs text-muted">{{ $voucher->name }}</p>
                                </td>
                                <td>{{ $voucher->describe() }}</td>
                                <td class="num">{{ (float) $voucher->min_purchase > 0 ? Format::rupiah($voucher->min_purchase) : '–' }}</td>
                                <td class="text-sm whitespace-nowrap">
                                    {{ $voucher->starts_at?->translatedFormat('d M Y') ?? 'Kapan saja' }} –
                                    {{ $voucher->ends_at?->translatedFormat('d M Y') ?? 'tanpa batas' }}
                                </td>
                                <td class="num">{{ $voucher->used_count }}{{ $voucher->quota ? ' / '.$voucher->quota : '' }}</td>
                                <td>
                                    @if (! $voucher->is_active)
                                        <x-ui.badge>Nonaktif</x-ui.badge>
                                    @elseif ($voucher->ends_at?->isPast())
                                        <x-ui.badge variant="danger">Kedaluwarsa</x-ui.badge>
                                    @elseif ($voucher->remainingQuota() === 0)
                                        <x-ui.badge variant="warning">Kuota habis</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="success">Aktif</x-ui.badge>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        <x-ui.button :href="route('owner.vouchers.edit', $voucher)" variant="ghost" size="sm" class="btn-icon" icon="pencil" aria-label="Ubah" />
                                        <x-ui.modal :action="route('owner.vouchers.destroy', $voucher)" title="Hapus voucher {{ $voucher->code }}?"
                                                    message="Voucher yang sudah pernah dipakai tidak dapat dihapus, nonaktifkan saja." />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($vouchers->hasPages())
                <div class="pagination-wrap">{{ $vouchers->links() }}</div>
            @endif
        @endif
    </div>
@endsection
