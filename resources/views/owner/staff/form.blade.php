@extends('layouts.owner')

@section('title', $staff->exists ? 'Ubah Kasir' : 'Tambah Kasir')

@section('content')
    <x-ui.page-header :title="$staff->exists ? 'Ubah Akun Kasir' : 'Tambah Kasir'" :back="route('owner.staff.index')" back-label="Staf kasir" />

    <div class="grid max-w-3xl gap-6">
        <form method="POST" action="{{ $staff->exists ? route('owner.staff.update', $staff) : route('owner.staff.store') }}" class="card">
            @csrf
            @if ($staff->exists) @method('PUT') @endif

            <div class="card-body flex flex-col gap-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.field label="Nama" for="name" required>
                        <input id="name" name="name" type="text" maxlength="100" required value="{{ old('name', $staff->name) }}" @class(['input', 'input-invalid' => $errors->has('name')])>
                    </x-form.field>
                    <x-form.field label="Username" for="username" hint="Dipakai untuk login. Huruf kecil, angka, titik, - atau _." required>
                        <input id="username" name="username" type="text" maxlength="50" required autocomplete="off" value="{{ old('username', $staff->username) }}"
                               @class(['input font-mono lowercase', 'input-invalid' => $errors->has('username')])>
                    </x-form.field>
                </div>

                @if (! $staff->exists)
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-form.field label="Password" for="password" hint="Minimal 8 karakter." required>
                            <input id="password" name="password" type="password" required autocomplete="new-password" @class(['input', 'input-invalid' => $errors->has('password')])>
                        </x-form.field>
                        <x-form.field label="Ulangi password" for="password_confirmation" required>
                            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="input">
                        </x-form.field>
                    </div>
                @else
                    <x-form.switch name="is_active" :checked="old('is_active', $staff->is_active)" label="Akun aktif"
                                   description="Akun nonaktif tidak bisa login dan langsung keluar dari semua perangkat." />
                    @error('is_active') <p class="field-error">{{ $message }}</p> @enderror
                @endif
            </div>

            <div class="flex justify-end gap-2 border-t border-line px-5 py-4">
                <x-ui.button :href="route('owner.staff.index')" variant="secondary">Batal</x-ui.button>
                <x-ui.button>Simpan</x-ui.button>
            </div>
        </form>

        @if ($staff->exists)
            <form method="POST" action="{{ route('owner.staff.password', $staff) }}" class="card">
                @csrf
                @method('PUT')
                <div class="card-header"><h2 class="card-title">Reset password</h2></div>
                <div class="card-body flex flex-col gap-5">
                    <p class="text-sm text-muted">Pakai jika kasir lupa password. Kasir akan keluar dari semua perangkat dan harus login dengan password baru.</p>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-form.field label="Password baru" for="password" hint="Minimal 8 karakter." required>
                            <input id="password" name="password" type="password" required autocomplete="new-password" @class(['input', 'input-invalid' => $errors->has('password')])>
                        </x-form.field>
                        <x-form.field label="Ulangi password baru" for="password_confirmation" required>
                            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="input">
                        </x-form.field>
                    </div>
                </div>
                <div class="flex justify-end border-t border-line px-5 py-4">
                    <x-ui.button variant="secondary" icon="key-round">Reset password</x-ui.button>
                </div>
            </form>
        @endif
    </div>
@endsection
