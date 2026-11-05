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

            @if ($order->voidRecord)
                <div class="void-notice" role="status">
                    <x-ui.icon name="ban" class="mt-0.5 size-5 shrink-0" />
                    <div class="text-sm">
                        <p class="font-semibold">Transaksi dibatalkan {{ $order->voidRecord->created_at->translatedFormat('d M Y H:i') }}</p>
                        <p class="mt-1 text-ink">Alasan: {{ $order->voidRecord->reason }}</p>
                        <p class="mt-1 text-muted">
                            Diminta {{ $order->voidRecord->requester->name }} · disetujui {{ $order->voidRecord->approver->name }} (PIN owner) ·
                            {{ Format::rupiah($order->voidRecord->amount) }} dikeluarkan dari penjualan, stok bahan dikembalikan.
                        </p>
                    </div>
                </div>
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
                @if ($canVoid)
                    <div x-data="{ open: @js($errors->void->any()), submitting: false }" x-on:keydown.escape.window="open = false">
                        <x-ui.button type="button" variant="secondary" icon="ban" class="hover:text-danger" x-on:click="open = true">Batalkan (void)</x-ui.button>

                        <template x-teleport="body">
                            <div x-show="open" x-cloak>
                                <div class="modal-backdrop" x-on:click="open = false" x-transition.opacity></div>
                                <form method="POST" action="{{ route('orders.void', $order) }}" class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="void-title"
                                      x-transition x-on:submit="submitting = true">
                                    @csrf
                                    <span class="modal-icon"><x-ui.icon name="ban" /></span>
                                    <h2 id="void-title" class="text-base font-semibold">Batalkan {{ $order->code }}?</h2>
                                    <p class="mt-1.5 text-sm text-muted">
                                        {{ Format::rupiah($order->grand_total) }} dikeluarkan dari penjualan shift ini dan stok bahan dikembalikan.
                                        Kembalikan uang ke pelanggan sesuai metode bayarnya. Butuh persetujuan owner.
                                    </p>
                                    <div class="mt-5 flex flex-col gap-4">
                                        <x-form.field label="Alasan" for="reason" bag="void" required>
                                            <textarea id="reason" name="reason" rows="2" maxlength="255" required placeholder="Contoh: salah input menu, pelanggan batal"
                                                      @class(['input h-auto py-2', 'input-invalid' => $errors->void->has('reason')])>{{ old('reason') }}</textarea>
                                        </x-form.field>
                                        <x-form.field label="PIN owner" for="pin" bag="void" required>
                                            <input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="off"
                                                   @class(['input font-mono tracking-[0.4em]', 'input-invalid' => $errors->void->has('pin')])>
                                        </x-form.field>
                                    </div>
                                    <div class="mt-6 flex justify-end gap-2">
                                        <x-ui.button type="button" variant="secondary" x-on:click="open = false">Kembali</x-ui.button>
                                        <x-ui.button variant="danger" x-bind:disabled="submitting">Ya, batalkan</x-ui.button>
                                    </div>
                                </form>
                            </div>
                        </template>
                    </div>
                @endif
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
