{{-- Thermal receipt body. Used on screen (orders.show) and on the print page. --}}
@props(['order', 'settings', 'logoUrl' => null, 'width' => 58])
@use('App\Support\Format')

<div {{ $attributes->class(['receipt', 'receipt-'.$width]) }}>
    <div class="receipt-center">
        @if ($logoUrl)
            <img src="{{ $logoUrl }}" alt="" class="receipt-logo">
        @endif
        <div class="receipt-store">{{ $settings['store_name'] }}</div>
        @if ($settings['store_address'])<div>{{ $settings['store_address'] }}</div>@endif
        @if ($settings['store_phone'])<div>Telp. {{ $settings['store_phone'] }}</div>@endif
    </div>

    @if ($order->status === \App\Enums\OrderStatus::Void)
        <div class="receipt-void">
            DIBATALKAN
            @if ($order->voidRecord)<small>{{ $order->voidRecord->created_at->format('d/m/Y H:i') }} · {{ $order->voidRecord->reason }}</small>@endif
        </div>
    @endif

    <hr class="receipt-rule">

    <div class="receipt-row"><span>No</span><span>{{ $order->code }}</span></div>
    <div class="receipt-row"><span>Waktu</span><span>{{ ($order->paid_at ?? $order->created_at)->format('d/m/Y H:i') }}</span></div>
    <div class="receipt-row"><span>Kasir</span><span>{{ $order->user->name }}</span></div>
    @if ($order->customer_name)
        <div class="receipt-row"><span>Pelanggan</span><span>{{ $order->customer_name }}</span></div>
    @endif
    <div class="receipt-row"><span>Jenis</span><span>{{ $order->order_type->label() }}</span></div>

    <hr class="receipt-rule">

    @foreach ($order->items as $item)
        <div class="receipt-item-name">{{ $item->product_name }}</div>
        <div class="receipt-row">
            <span>{{ $item->quantity }} x {{ number_format((float) $item->unit_price, 0, ',', '.') }}</span>
            <span>{{ number_format((float) $item->subtotal, 0, ',', '.') }}</span>
        </div>
        @if ($item->note)
            <div class="receipt-note">* {{ $item->note }}</div>
        @endif
    @endforeach

    <hr class="receipt-rule">

    <div class="receipt-row"><span>Subtotal</span><span>{{ Format::rupiah($order->subtotal) }}</span></div>
    @if ((float) $order->discount > 0)
        <div class="receipt-row"><span>Diskon{{ $order->voucher_code ? ' ('.$order->voucher_code.')' : '' }}</span><span>-{{ Format::rupiah($order->discount) }}</span></div>
    @endif
    @if ((float) $order->service_charge > 0)
        <div class="receipt-row"><span>Service {{ Format::percent($order->service_charge_rate) }}</span><span>{{ Format::rupiah($order->service_charge) }}</span></div>
    @endif
    @if ((float) $order->tax > 0)
        <div class="receipt-row"><span>Pajak {{ Format::percent($order->tax_rate) }}</span><span>{{ Format::rupiah($order->tax) }}</span></div>
    @endif
    @if ((float) $order->rounding != 0)
        <div class="receipt-row"><span>Pembulatan</span><span>{{ Format::rupiah($order->rounding) }}</span></div>
    @endif
    <div class="receipt-row receipt-total"><span>TOTAL</span><span>{{ Format::rupiah($order->grand_total) }}</span></div>

    @if ($order->payment)
        <hr class="receipt-rule">
        <div class="receipt-row"><span>{{ $order->payment->method->label() }}</span><span>{{ Format::rupiah($order->payment->cash_received ?? $order->payment->amount) }}</span></div>
        @if ($order->payment->change !== null)
            <div class="receipt-row"><span>Kembali</span><span>{{ Format::rupiah($order->payment->change) }}</span></div>
        @endif
    @endif

    @if ($settings['receipt_footer'])
        <hr class="receipt-rule">
        <div class="receipt-center receipt-muted">{{ $settings['receipt_footer'] }}</div>
    @endif
</div>
