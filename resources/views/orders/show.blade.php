@extends($layout)

@section('title', $order->code)

@use('App\Support\Format')

@section('content')
    <div class="mx-auto grid max-w-4xl gap-6 lg:grid-cols-[1fr_auto]">
        <div class="flex flex-col gap-6">
            @if ($justPaid)
                <div class="card flex items-center gap-4 p-5">
                    <span class="grid size-12 shrink-0 place-items-center rounded-full bg-success-soft text-success"><x-ui.icon name="check-circle-2" class="size-6" /></span>
                    <div>
                        <p class="text-lg font-bold">Pembayaran berhasil</p>
                        <p class="text-sm text-muted">{{ $order->code }} · {{ $order->payment?->method->label() }}</p>
                    </div>
                </div>
            @else
                <x-ui.page-header :title="'Transaksi '.$order->code" :subtitle="($order->paid_at ?? $order->created_at)->translatedFormat('l, d F Y H:i')"
                                  :back="route('orders.index')" back-label="Riwayat transaksi" />
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.stat-card label="Total" icon="wallet" :value="Format::rupiah($order->grand_total)" :note="$order->itemCount().' item · '.$order->order_type->label()" />
                @if ($order->payment?->change !== null)
                    <x-ui.stat-card label="Kembalian" icon="check-circle-2" :value="Format::rupiah($order->payment->change)"
                                    :note="'Uang diterima '.Format::rupiah($order->payment->cash_received)" />
                @else
                    <x-ui.stat-card label="Status" icon="check-circle-2" :value="$order->status->label()" />
                @endif
            </div>

            <div class="flex flex-wrap gap-2">
                <x-ui.button :href="route('orders.print', ['order' => $order, 'width' => 58])" variant="secondary" icon="printer" target="_blank">Cetak 58mm</x-ui.button>
                <x-ui.button :href="route('orders.print', ['order' => $order, 'width' => 80])" variant="secondary" icon="printer" target="_blank">Cetak 80mm</x-ui.button>
                @if (auth()->user()->isCashier())
                    <x-ui.button :href="route('cashier.pos')" icon="plus" class="sm:ml-auto">Transaksi baru</x-ui.button>
                @endif
            </div>
        </div>

        <div class="justify-self-center">
            <x-receipt.receipt :order="$order" :settings="$settings" :logo-url="$logoUrl" :width="$settings['receipt_width']" class="receipt-paper" />
        </div>
    </div>
@endsection
