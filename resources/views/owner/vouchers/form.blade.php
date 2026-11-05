@extends('layouts.owner')

@section('title', $voucher->exists ? 'Ubah Voucher' : 'Buat Voucher')

@section('content')
    <x-ui.page-header :title="$voucher->exists ? 'Ubah Voucher' : 'Buat Voucher'" :back="route('owner.vouchers.index')" back-label="Voucher" />

    <form method="POST" action="{{ $voucher->exists ? route('owner.vouchers.update', $voucher) : route('owner.vouchers.store') }}" class="card max-w-3xl"
          x-data="{ type: @js(old('type', $voucher->type?->value)) }">
        @csrf
        @if ($voucher->exists) @method('PUT') @endif

        <div class="card-body flex flex-col gap-5">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.field label="Kode voucher" for="code" hint="Huruf besar, angka, - atau _. Contoh: NGOPIPAGI" required>
                    <input id="code" name="code" type="text" maxlength="30" required value="{{ old('code', $voucher->code) }}"
                           @class(['input font-mono uppercase', 'input-invalid' => $errors->has('code')])>
                </x-form.field>
                <x-form.field label="Nama promo" for="name" required>
                    <input id="name" name="name" type="text" maxlength="100" required value="{{ old('name', $voucher->name) }}" @class(['input', 'input-invalid' => $errors->has('name')])>
                </x-form.field>
            </div>

            <div class="grid gap-5 sm:grid-cols-3">
                <x-form.field label="Jenis diskon" for="type" required>
                    <select id="type" name="type" x-model="type" @class(['select', 'input-invalid' => $errors->has('type')])>
                        @foreach ($types as $case)
                            <option value="{{ $case->value }}">{{ $case->label() }}</option>
                        @endforeach
                    </select>
                </x-form.field>
                <x-form.field label="Nilai diskon" for="value" required>
                    <div class="input-group">
                        <span class="input-addon" x-show="type === 'fixed'">Rp</span>
                        <input id="value" name="value" type="number" min="0" step="any" required value="{{ old('value', $voucher->value !== null ? (float) $voucher->value : '') }}"
                               @class(['input', 'input-invalid' => $errors->has('value')])>
                        <span class="input-addon" x-show="type === 'percent'">%</span>
                    </div>
                </x-form.field>
                <x-form.field label="Maksimum diskon" for="max_discount" hint="Opsional, untuk diskon persen.">
                    <div class="input-group">
                        <span class="input-addon">Rp</span>
                        <input id="max_discount" name="max_discount" type="number" min="0" step="1" value="{{ old('max_discount', $voucher->max_discount !== null ? (int) $voucher->max_discount : '') }}"
                               @class(['input', 'input-invalid' => $errors->has('max_discount')])>
                    </div>
                </x-form.field>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.field label="Minimum pembelian" for="min_purchase">
                    <div class="input-group">
                        <span class="input-addon">Rp</span>
                        <input id="min_purchase" name="min_purchase" type="number" min="0" step="1" value="{{ old('min_purchase', $voucher->min_purchase !== null ? (int) $voucher->min_purchase : '') }}"
                               @class(['input', 'input-invalid' => $errors->has('min_purchase')])>
                    </div>
                </x-form.field>
                <x-form.field label="Kuota penggunaan" for="quota" hint="Kosongkan jika tidak dibatasi.">
                    <input id="quota" name="quota" type="number" min="1" value="{{ old('quota', $voucher->quota) }}" @class(['input', 'input-invalid' => $errors->has('quota')])>
                </x-form.field>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.field label="Mulai berlaku" for="starts_at">
                    <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $voucher->starts_at?->format('Y-m-d\TH:i')) }}" @class(['input', 'input-invalid' => $errors->has('starts_at')])>
                </x-form.field>
                <x-form.field label="Berakhir" for="ends_at">
                    <input id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', $voucher->ends_at?->format('Y-m-d\TH:i')) }}" @class(['input', 'input-invalid' => $errors->has('ends_at')])>
                </x-form.field>
            </div>

            <x-form.switch name="is_active" :checked="old('is_active', $voucher->is_active)" label="Aktif" description="Voucher nonaktif ditolak saat dipakai di kasir." />

            @if ($voucher->exists)
                <p class="hint">Sudah dipakai {{ $voucher->used_count }} kali.</p>
            @endif
        </div>

        <div class="flex justify-end gap-2 border-t border-line px-5 py-4">
            <x-ui.button :href="route('owner.vouchers.index')" variant="secondary">Batal</x-ui.button>
            <x-ui.button>Simpan</x-ui.button>
        </div>
    </form>
@endsection
