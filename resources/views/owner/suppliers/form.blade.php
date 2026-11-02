@extends('layouts.owner')

@section('title', $supplier->exists ? 'Ubah Supplier' : 'Tambah Supplier')

@section('content')
    <x-ui.page-header :title="$supplier->exists ? 'Ubah Supplier' : 'Tambah Supplier'" :back="route('owner.suppliers.index')" back-label="Supplier" />

    <form method="POST" action="{{ $supplier->exists ? route('owner.suppliers.update', $supplier) : route('owner.suppliers.store') }}" class="card max-w-2xl">
        @csrf
        @if ($supplier->exists) @method('PUT') @endif

        <div class="card-body flex flex-col gap-5">
            <x-form.field label="Nama supplier" for="name" required>
                <input id="name" name="name" type="text" maxlength="150" required value="{{ old('name', $supplier->name) }}" @class(['input', 'input-invalid' => $errors->has('name')])>
            </x-form.field>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.field label="Nomor telepon" for="phone">
                    <input id="phone" name="phone" type="tel" maxlength="30" value="{{ old('phone', $supplier->phone) }}" @class(['input', 'input-invalid' => $errors->has('phone')])>
                </x-form.field>
                <x-form.field label="Email" for="email">
                    <input id="email" name="email" type="email" maxlength="255" value="{{ old('email', $supplier->email) }}" @class(['input', 'input-invalid' => $errors->has('email')])>
                </x-form.field>
            </div>

            <x-form.field label="Alamat" for="address">
                <textarea id="address" name="address" rows="3" maxlength="1000" @class(['textarea', 'input-invalid' => $errors->has('address')])>{{ old('address', $supplier->address) }}</textarea>
            </x-form.field>
        </div>

        <div class="flex justify-end gap-2 border-t border-line px-5 py-4">
            <x-ui.button :href="route('owner.suppliers.index')" variant="secondary">Batal</x-ui.button>
            <x-ui.button>Simpan</x-ui.button>
        </div>
    </form>
@endsection
