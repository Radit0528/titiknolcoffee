@extends('layouts.cashier')

@section('title', 'Shift Saya')

@use('App\Support\Format')

@section('content')
    <x-ui.page-header title="Shift Saya" :subtitle="'Dibuka '.$shift->opened_at->translatedFormat('l, d F Y H:i')">
        <x-ui.button :href="route('shifts.print', $shift)" variant="secondary" icon="printer" target="_blank">Cetak ringkasan</x-ui.button>
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Total penjualan" icon="wallet" :value="Format::rupiah($summary['total_sales'])" :note="$summary['order_count'].' transaksi · '.$summary['item_count'].' item'" />
        <x-ui.stat-card label="Tunai" icon="wallet" :value="Format::rupiah($summary['cash_sales'])" />
        <x-ui.stat-card label="Kas masuk / keluar" icon="arrow-left-right" :value="'+'.Format::rupiah($summary['cash_in']).' / -'.Format::rupiah($summary['cash_out'])" />
        <x-ui.stat-card label="Uang di laci" icon="wallet" :value="Format::rupiah($summary['expected_cash'])" note="Seharusnya, menurut sistem" />
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="flex flex-col gap-6 lg:col-span-3">
            {{-- Kas masuk / keluar --}}
            <section class="card" x-data="{ type: @js(old('type', 'out')) }">
                <div class="card-header"><h2 class="card-title">Catat kas masuk / keluar</h2></div>
                <form method="POST" action="{{ route('cashier.shift.cash') }}" class="card-body flex flex-col gap-4" x-data="{ busy: false }" x-on:submit="busy = true">
                    @csrf
                    <div class="segmented" role="group" aria-label="Jenis kas">
                        @foreach ($types as $case)
                            <button type="button" x-on:click="type = '{{ $case->value }}'" x-bind:aria-pressed="type === '{{ $case->value }}'">{{ $case->label() }}</button>
                        @endforeach
                    </div>
                    <input type="hidden" name="type" x-bind:value="type">
                    <div class="grid gap-4 sm:grid-cols-[12rem_1fr]">
                        <x-form.field label="Nominal" for="amount" required>
                            <div class="input-group">
                                <span class="input-addon">Rp</span>
                                <input id="amount" name="amount" type="number" min="1" step="1" required inputmode="numeric" value="{{ old('amount') }}"
                                       @class(['input', 'input-invalid' => $errors->has('amount')])>
                            </div>
                        </x-form.field>
                        <x-form.field label="Alasan" for="reason" hint="Contoh: beli es batu, tambah uang kembalian." required>
                            <input id="reason" name="reason" type="text" maxlength="255" required value="{{ old('reason') }}" @class(['input', 'input-invalid' => $errors->has('reason')])>
                        </x-form.field>
                    </div>
                    <x-ui.button class="self-end" x-bind:disabled="busy">Simpan</x-ui.button>
                </form>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">Riwayat kas shift ini</h2></div>
                @if ($movements->isEmpty())
                    <p class="px-5 py-6 text-sm text-muted">Belum ada kas masuk atau keluar.</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($movements as $movement)
                            <li class="flex items-start justify-between gap-3 px-5 py-3 text-sm">
                                <div>
                                    <p class="font-medium">{{ $movement->reason }}</p>
                                    <p class="text-xs text-muted">{{ $movement->type->label() }} · {{ $movement->created_at->format('H:i') }}</p>
                                </div>
                                <span @class(['font-semibold tabular-nums', 'text-success' => $movement->type->value === 'in', 'text-danger' => $movement->type->value === 'out'])>
                                    {{ $movement->type->value === 'in' ? '+' : '-' }}{{ Format::rupiah($movement->amount) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        {{-- Tutup shift --}}
        <section class="card self-start lg:col-span-2" x-data="{ counted: @js(old('closing_cash', '')), expected: {{ $summary['expected_cash'] }}, busy: false }">
            <div class="card-header"><h2 class="card-title">Tutup shift</h2></div>
            <x-shift.summary :summary="$summary" class="px-5 py-2" />

            <form method="POST" action="{{ route('cashier.shift.close') }}" class="flex flex-col gap-4 border-t border-line p-5" x-on:submit="busy = true">
                @csrf
                @if ($summary['pending_count'])
                    <div class="alert alert-warning">
                        <x-ui.icon name="alert-triangle" />
                        <p>{{ $summary['pending_count'] }} transaksi masih menunggu pembayaran. Selesaikan atau batalkan sebelum menutup shift.</p>
                    </div>
                @endif
                @if ($summary['draft_count'])
                    <p class="hint">Ada {{ $summary['draft_count'] }} draft pesanan. Draft tetap tersimpan dan bisa dilanjutkan di shift berikutnya.</p>
                @endif

                <x-form.field label="Uang fisik di laci (hasil hitung)" for="closing_cash" required>
                    <div class="input-group">
                        <span class="input-addon">Rp</span>
                        <input id="closing_cash" name="closing_cash" type="number" min="0" step="1" required inputmode="numeric" x-model="counted"
                               @class(['input cash-input', 'input-invalid' => $errors->has('closing_cash')])>
                    </div>
                </x-form.field>

                <div class="flex items-center justify-between rounded-xl border border-line px-4 py-3" x-show="counted !== ''" x-cloak>
                    <span class="text-sm font-medium">Selisih kas</span>
                    <span class="text-lg font-bold tabular-nums"
                          x-bind:class="(counted - expected) === 0 ? 'text-success' : 'text-danger'"
                          x-text="((counted - expected) > 0 ? '+' : (counted - expected) < 0 ? '-' : '') + 'Rp' + Math.abs(counted - expected).toLocaleString('id-ID')"></span>
                </div>

                <x-form.field label="Catatan" for="closing_notes" hint="Wajib diisi jika ada selisih kas.">
                    <textarea id="closing_notes" name="closing_notes" rows="2" maxlength="1000" @class(['textarea min-h-16', 'input-invalid' => $errors->has('closing_notes')])>{{ old('closing_notes') }}</textarea>
                </x-form.field>

                <x-ui.button variant="danger" class="h-11" x-bind:disabled="busy || counted === ''">Tutup shift</x-ui.button>
            </form>
        </section>
    </div>
@endsection
