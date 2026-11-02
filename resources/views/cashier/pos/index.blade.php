@extends('layouts.cashier')

@section('title', 'Kasir')

@use('App\Support\Format')

@section('content')
    <div class="pos-shell" x-data="pos(@js($catalog), @js($config), @js($draft))" x-on:keydown.window="onKey">
        {{-- Catalogue --}}
        <section class="pos-catalog" aria-label="Daftar menu">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="search-field flex-1">
                    <x-ui.icon name="search" />
                    <input type="search" x-ref="search" x-model="search" placeholder="Cari menu…  (tekan /)" class="input" aria-label="Cari menu">
                </div>
                @if ($draft)
                    <x-ui.badge variant="warning">Melanjutkan draft</x-ui.badge>
                @endif
            </div>

            <div class="category-tabs" role="toolbar" aria-label="Filter kategori">
                <button type="button" class="category-tab" x-on:click="category = 'all'" x-bind:aria-pressed="category === 'all'">Semua</button>
                @foreach ($categories as $category)
                    <button type="button" class="category-tab" x-on:click="category = '{{ $category->id }}'"
                            x-bind:aria-pressed="category === '{{ $category->id }}'">{{ $category->name }}</button>
                @endforeach
            </div>

            @if ($catalog->isEmpty())
                <div class="card">
                    <x-ui.empty-state icon="coffee" title="Belum ada menu" description="Owner belum menambahkan produk ke kategori aktif." />
                </div>
            @else
                <div class="product-grid">
                    <template x-for="product in visibleProducts" x-bind:key="product.id">
                        <button type="button" class="product-tile" x-on:click="add(product)" x-bind:disabled="!canAdd(product)">
                            <div class="product-tile-media">
                                <img x-show="product.image" x-bind:src="product.image" alt="" loading="lazy">
                                <span x-show="!product.image"><x-ui.icon name="coffee" /></span>
                            </div>
                            <span class="product-qty" x-show="quantityOf(product.id)" x-text="quantityOf(product.id)"></span>
                            <div class="flex flex-1 flex-col gap-1 p-3">
                                <p class="text-[0.68rem] font-semibold tracking-wide text-muted uppercase" x-text="product.category"></p>
                                <p class="text-sm leading-snug font-semibold" x-text="product.name"></p>
                                <div class="mt-auto flex items-center justify-between gap-2 pt-2">
                                    <span class="text-sm font-bold tabular-nums" x-text="money(product.price)"></span>
                                    <span class="text-[0.7rem] font-semibold" x-bind:class="canAdd(product) ? 'text-warning' : 'text-danger'" x-text="stockLabel(product)"></span>
                                </div>
                            </div>
                        </button>
                    </template>
                </div>
                <p class="py-10 text-center text-sm text-muted" x-show="visibleProducts.length === 0" x-cloak>Menu tidak ditemukan.</p>
            @endif
        </section>

        {{-- Cart --}}
        <aside class="pos-cart" x-bind:data-open="cartOpen ? 'true' : 'false'" aria-label="Keranjang">
            <div class="card-header">
                <h2 class="card-title">Pesanan <span class="text-muted" x-show="itemCount" x-text="'(' + itemCount + ' item)'"></span></h2>
                <div class="flex items-center gap-1">
                    <button type="button" class="text-xs font-semibold text-danger hover:underline" x-show="lines.length" x-on:click="clearCart">Kosongkan</button>
                    <button type="button" class="btn btn-ghost btn-sm btn-icon lg:hidden" x-on:click="cartOpen = false" aria-label="Tutup keranjang"><x-ui.icon name="x" /></button>
                </div>
            </div>

            <div class="flex flex-col gap-3 border-b border-line px-5 py-3">
                <div class="segmented" role="group" aria-label="Jenis pesanan">
                    @foreach ($orderTypes as $type)
                        <button type="button" x-on:click="orderType = '{{ $type->value }}'" x-bind:aria-pressed="orderType === '{{ $type->value }}'">{{ $type->label() }}</button>
                    @endforeach
                </div>
                <input type="text" x-model="customerName" maxlength="100" placeholder="Nama pelanggan (opsional)" class="input h-9" aria-label="Nama pelanggan">
            </div>

            <div class="cart-lines">
                <template x-for="l in lines" x-bind:key="l.key">
                    <div class="cart-line">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold" x-text="l.name"></p>
                                <p class="text-xs text-muted tabular-nums" x-text="money(l.price)"></p>
                            </div>
                            <p class="text-sm font-semibold tabular-nums" x-text="money(l.price * l.quantity)"></p>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <div class="qty-stepper">
                                <button type="button" x-on:click="decrement(l)" aria-label="Kurangi"><x-ui.icon name="minus" /></button>
                                <span x-text="l.quantity"></span>
                                <button type="button" x-on:click="increment(l)" aria-label="Tambah"><x-ui.icon name="plus" /></button>
                            </div>
                            <div class="flex items-center gap-1">
                                <button type="button" class="btn btn-ghost btn-sm" x-on:click="toggleNote(l)">
                                    <x-ui.icon name="sticky-note" /><span x-text="l.note ? 'Ubah catatan' : 'Catatan'"></span>
                                </button>
                                <button type="button" class="btn btn-ghost btn-sm btn-icon hover:text-danger" x-on:click="remove(l)" aria-label="Hapus"><x-ui.icon name="trash-2" /></button>
                            </div>
                        </div>
                        <p class="text-xs text-warning italic" x-show="l.note && noteFor !== l.key" x-text="'Catatan: ' + l.note"></p>
                        <input type="text" class="input h-9" maxlength="255" placeholder="Misal: less sugar, tanpa es" x-show="noteFor === l.key" x-model="l.note"
                               x-on:keydown.enter.prevent="noteFor = null" aria-label="Catatan pesanan">
                    </div>
                </template>
                <div class="empty-state" x-show="lines.length === 0">
                    <span class="empty-state-icon"><x-ui.icon name="shopping-cart" /></span>
                    <p class="text-sm font-semibold">Keranjang kosong</p>
                    <p class="text-sm text-muted">Pilih menu di sebelah kiri.</p>
                </div>
            </div>

            <div class="flex flex-col gap-3 border-t border-line px-5 py-4">
                <div x-show="!voucher">
                    <div class="flex gap-2">
                        <input type="text" x-model="voucherInput" placeholder="Kode voucher" class="input h-9 uppercase" maxlength="30"
                               x-on:keydown.enter.prevent="applyVoucher" aria-label="Kode voucher">
                        <x-ui.button type="button" variant="secondary" size="sm" class="h-9" x-on:click="applyVoucher" x-bind:disabled="!lines.length || !voucherInput.trim()">Pakai</x-ui.button>
                    </div>
                    <p class="field-error mt-1" x-show="voucherError" x-text="voucherError"></p>
                </div>
                <div class="flex items-center justify-between rounded-lg bg-success-soft px-3 py-2 text-sm" x-show="voucher" x-cloak>
                    <span class="flex items-center gap-2 font-semibold text-success"><x-ui.icon name="ticket" class="size-4" /><span x-text="voucher?.code"></span></span>
                    <button type="button" class="text-xs font-semibold text-muted hover:text-danger" x-on:click="removeVoucher">Lepas</button>
                </div>

                <dl class="totals">
                    <div><dt>Subtotal</dt><dd x-text="money(subtotal)"></dd></div>
                    <div x-show="discount" x-cloak><dt>Diskon</dt><dd class="text-success" x-text="'-' + money(discount)"></dd></div>
                    <div x-show="serviceCharge" x-cloak><dt>Service {{ Format::percent($config['serviceRate']) }}</dt><dd x-text="money(serviceCharge)"></dd></div>
                    <div x-show="tax" x-cloak><dt>Pajak {{ Format::percent($config['taxRate']) }}</dt><dd x-text="money(tax)"></dd></div>
                    <div class="border-t border-line pt-2 text-base font-bold"><dt class="!text-ink">Total</dt><dd x-text="money(total)"></dd></div>
                </dl>

                <p class="field-error" x-show="notice" x-text="notice" x-cloak></p>

                <div class="grid grid-cols-[auto_1fr] gap-2">
                    <x-ui.button type="button" variant="secondary" icon="save" x-on:click="saveDraft" x-bind:disabled="!lines.length || busy">Tahan</x-ui.button>
                    <x-ui.button type="button" variant="accent" x-on:click="openCheckout" x-bind:disabled="!lines.length">Bayar <span x-text="money(total)"></span></x-ui.button>
                </div>
            </div>
        </aside>

        <button type="button" class="cart-bar" x-show="!cartOpen && lines.length" x-cloak x-on:click="cartOpen = true">
            <span class="flex items-center gap-2"><x-ui.icon name="shopping-cart" class="size-5" /> <span x-text="itemCount + ' item'"></span></span>
            <span x-text="money(total)"></span>
        </button>
        <div class="sidebar-overlay !z-30" x-show="cartOpen" x-cloak x-transition.opacity x-on:click="cartOpen = false"></div>

        {{-- Cash payment --}}
        <template x-teleport="body">
            <div x-show="checkoutOpen" x-cloak>
                <div class="modal-backdrop" x-on:click="closeCheckout" x-transition.opacity></div>
                <div class="pay-panel" role="dialog" aria-modal="true" aria-labelledby="pay-title" x-transition>
                    <div class="mb-4 flex items-center justify-between">
                        <h2 id="pay-title" class="text-lg font-bold">Pembayaran Tunai</h2>
                        <button type="button" class="btn btn-ghost btn-sm btn-icon" x-on:click="closeCheckout" aria-label="Tutup"><x-ui.icon name="x" /></button>
                    </div>

                    <dl class="totals mb-4">
                        <div><dt>Subtotal</dt><dd x-text="money(subtotal)"></dd></div>
                        <div x-show="discount"><dt>Diskon <span x-text="voucher ? '(' + voucher.code + ')' : ''"></span></dt><dd class="text-success" x-text="'-' + money(discount)"></dd></div>
                        <div x-show="serviceCharge"><dt>Service charge</dt><dd x-text="money(serviceCharge)"></dd></div>
                        <div x-show="tax"><dt>Pajak</dt><dd x-text="money(tax)"></dd></div>
                        <div x-show="rounding"><dt>Pembulatan</dt><dd x-text="(rounding > 0 ? '+' : '') + money(rounding)"></dd></div>
                    </dl>

                    <div class="pay-total mb-4 flex items-center justify-between">
                        <span class="text-sm text-coffee-300">Total bayar</span>
                        <span class="text-2xl font-bold tabular-nums" x-text="money(cashTotal)"></span>
                    </div>

                    <div class="flex flex-col gap-3">
                        <label for="cash" class="label">Uang diterima</label>
                        <div class="input-group">
                            <span class="input-addon">Rp</span>
                            <input id="cash" x-ref="cash" type="number" inputmode="numeric" min="0" step="500" class="input cash-input" x-model="cashReceived"
                                   x-on:keydown.enter.prevent="pay" placeholder="0">
                        </div>
                        <div class="cash-options">
                            <template x-for="(amount, i) in quickCash" x-bind:key="amount">
                                <button type="button" class="cash-option" x-on:click="setCash(amount)" x-bind:aria-pressed="cashValue === amount"
                                        x-text="i === 0 ? 'Uang pas' : money(amount)"></button>
                            </template>
                        </div>

                        <div class="flex items-center justify-between rounded-xl border border-line px-4 py-3">
                            <span class="text-sm font-medium" x-text="change >= 0 ? 'Kembalian' : 'Kurang'"></span>
                            <span class="text-xl font-bold tabular-nums" x-bind:class="change >= 0 ? 'text-success' : 'text-danger'" x-text="money(Math.abs(change))"></span>
                        </div>

                        <p class="field-error" x-show="error" x-text="error"></p>

                        <x-ui.button type="button" variant="primary" class="h-12 text-base" x-on:click="pay" x-bind:disabled="!canPay">
                            <span x-show="!busy">Selesaikan pembayaran</span>
                            <span x-show="busy" x-cloak>Memproses…</span>
                        </x-ui.button>
                        <p class="hint text-center">Pembayaran QRIS tersedia setelah integrasi Midtrans (Tahap 5).</p>
                    </div>
                </div>
            </div>
        </template>
    </div>
@endsection
