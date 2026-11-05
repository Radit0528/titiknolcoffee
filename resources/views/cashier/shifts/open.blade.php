@extends('layouts.cashier')

@section('title', 'Buka Shift')

@section('content')
    <div class="mx-auto max-w-md pt-6">
        <form method="POST" action="{{ route('cashier.shift.store') }}" class="card" x-data="openShift(@js(old('opening_cash', '')))">
            @csrf
            <div class="card-body flex flex-col gap-5">
                <div>
                    <h1 class="text-xl font-bold">Buka Shift</h1>
                    <p class="mt-1 text-sm text-muted">Hitung uang di laci kas sebelum mulai melayani. Jumlah ini menjadi modal awal shift.</p>
                </div>

                <x-form.field label="Modal awal" for="opening_cash" required>
                    <div class="input-group">
                        <span class="input-addon">Rp</span>
                        <input id="opening_cash" name="opening_cash" type="number" min="0" step="1" required autofocus inputmode="numeric"
                               x-model="amount" @class(['input cash-input', 'input-invalid' => $errors->has('opening_cash')])>
                    </div>
                </x-form.field>

                <div class="cash-options">
                    @foreach ([100000, 200000, 300000, 500000] as $amount)
                        <button type="button" class="cash-option" x-on:click="set({{ $amount }})" x-bind:aria-pressed="amount == {{ $amount }}">{{ \App\Support\Format::rupiah($amount) }}</button>
                    @endforeach
                </div>

                <x-ui.button class="h-12 text-base">Buka shift <span x-text="formatted"></span></x-ui.button>
            </div>
        </form>
        @if ($recentShifts->isNotEmpty())
            <section class="card mt-6">
                <div class="card-header"><h2 class="card-title">Shift sebelumnya</h2></div>
                <ul class="divide-y divide-line">
                    @foreach ($recentShifts as $past)
                        <li>
                            <a href="{{ route('shifts.show', $past) }}" class="flex items-center justify-between gap-3 px-5 py-3 text-sm hover:bg-coffee-50/50">
                                <span>{{ $past->opened_at->translatedFormat('d M Y H:i') }} – {{ $past->closed_at->format('H:i') }}</span>
                                <span @class(['font-semibold tabular-nums', 'text-success' => (float) $past->cash_variance == 0, 'text-danger' => (float) $past->cash_variance != 0])>
                                    {{ (float) $past->cash_variance == 0 ? 'Kas sesuai' : 'Selisih '.\App\Support\Format::rupiah($past->cash_variance) }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endsection
