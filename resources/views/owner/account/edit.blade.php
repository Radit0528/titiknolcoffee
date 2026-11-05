@extends('layouts.owner')

@section('title', 'Akun & PIN')

@section('content')
    <x-ui.page-header title="Akun & PIN" :subtitle="$user->name.' · '.$user->username" />

    <div class="grid max-w-5xl gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('owner.account.pin') }}" class="card">
            @csrf
            @method('PUT')
            <div class="card-header">
                <h2 class="card-title">PIN owner</h2>
                @if ($hasPin)
                    <x-ui.badge variant="success">Sudah diatur</x-ui.badge>
                @else
                    <x-ui.badge variant="warning">Belum diatur</x-ui.badge>
                @endif
            </div>
            <div class="card-body flex flex-col gap-5">
                <p class="text-sm text-muted">
                    6 angka untuk menyetujui pembatalan transaksi (void) di layar kasir. Jangan beri tahu kasir.
                    Jika salah 5 kali, PIN dikunci 5 menit untuk kasir tersebut.
                </p>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.field :label="$hasPin ? 'PIN baru' : 'PIN'" for="pin" bag="pin" required>
                        <input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="off"
                               @class(['input font-mono tracking-[0.4em]', 'input-invalid' => $errors->pin->has('pin')])>
                    </x-form.field>
                    <x-form.field label="Ulangi PIN" for="pin_confirmation" bag="pin" required>
                        <input id="pin_confirmation" name="pin_confirmation" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="off"
                               class="input font-mono tracking-[0.4em]">
                    </x-form.field>
                </div>
                <x-form.field label="Password Anda" for="pin_current_password" bag="pin" hint="Untuk memastikan yang mengubah PIN adalah Anda." required>
                    <input id="pin_current_password" name="pin_current_password" type="password" required autocomplete="current-password"
                           @class(['input', 'input-invalid' => $errors->pin->has('pin_current_password')])>
                </x-form.field>
            </div>
            <div class="flex justify-end border-t border-line px-5 py-4">
                <x-ui.button icon="shield-check">{{ $hasPin ? 'Ganti PIN' : 'Simpan PIN' }}</x-ui.button>
            </div>
        </form>

        <form method="POST" action="{{ route('owner.account.password') }}" class="card">
            @csrf
            @method('PUT')
            <div class="card-header"><h2 class="card-title">Ganti password</h2></div>
            <div class="card-body flex flex-col gap-5">
                <x-form.field label="Password saat ini" for="current_password" bag="password" required>
                    <input id="current_password" name="current_password" type="password" required autocomplete="current-password"
                           @class(['input', 'input-invalid' => $errors->password->has('current_password')])>
                </x-form.field>
                <x-form.field label="Password baru" for="password" bag="password" hint="Minimal 8 karakter." required>
                    <input id="password" name="password" type="password" required autocomplete="new-password"
                           @class(['input', 'input-invalid' => $errors->password->has('password')])>
                </x-form.field>
                <x-form.field label="Ulangi password baru" for="password_confirmation" bag="password" required>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="input">
                </x-form.field>
                <p class="hint">Perangkat lain yang sedang login dengan akun ini akan dikeluarkan.</p>
            </div>
            <div class="flex justify-end border-t border-line px-5 py-4">
                <x-ui.button variant="secondary" icon="lock">Ganti password</x-ui.button>
            </div>
        </form>
    </div>
@endsection
