@extends('layouts.owner')

@section('title', $purchase->code)

@use('App\Support\Format')

@section('content')
    <x-ui.page-header :title="'Pembelian '.$purchase->code" :subtitle="$purchase->purchased_at->translatedFormat('l, d F Y')"
                      :back="route('owner.purchases.index')" back-label="Pembelian" />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Bahan</th><th class="num">Jumlah</th><th class="num">Harga per satuan</th><th class="num">Subtotal</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($purchase->items as $item)
                            <tr>
                                <td class="font-medium">{{ $item->ingredient->name }}</td>
                                <td class="num">{{ Format::qty($item->quantity) }} {{ $item->ingredient->unit->label() }}</td>
                                <td class="num">{{ Format::unitCost($item->unit_cost) }}</td>
                                <td class="num font-medium">{{ Format::rupiah($item->subtotal) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="border-t border-line px-4 py-3 font-semibold">Total</td>
                            <td class="num border-t border-line px-4 py-3 text-lg font-bold">{{ Format::rupiah($purchase->total) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card self-start">
            <div class="card-header"><h2 class="card-title">Informasi</h2></div>
            <dl class="costing-list px-5 py-2">
                <div><dt>Nomor nota</dt><dd>{{ $purchase->invoice_number ?? '–' }}</dd></div>
                <div><dt>Dicatat oleh</dt><dd>{{ $purchase->user?->name ?? '–' }}</dd></div>
                <div><dt>Waktu input</dt><dd>{{ $purchase->created_at->translatedFormat('d M Y H:i') }}</dd></div>
            </dl>
            @if ($purchase->notes)
                <p class="border-t border-line px-5 py-4 text-sm text-muted">{{ $purchase->notes }}</p>
            @endif
        </div>
    </div>
@endsection
