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
                        <input id="opening_cash" name="opening_cash" type="number" min="0" step="1000" required autofocus inputmode="numeric"
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
        <p class="hint mt-4 text-center">Kas masuk/keluar dan penutupan shift tersedia di Tahap 6.</p>
    </div>
@endsection
