@extends('layouts.app')

@section('title', 'Masuk')

@section('body')
    <div class="login-shell">
        <aside class="login-aside">
            <div class="flex items-center gap-3">
                <x-brand-mark class="size-10" />
                <div class="leading-tight">
                    <p class="font-bold tracking-wide text-white">SIMKOTOL</p>
                    <p class="text-xs text-coffee-300">Sistem Manajemen Kopi Titik Nol</p>
                </div>
            </div>

            <div class="relative z-10">
                <p class="login-quote">Setiap cangkir tercatat, setiap gram bahan terhitung.</p>
                <p class="mt-4 max-w-sm text-sm text-coffee-300">Kasir, stok, resep, dan laporan kedai dalam satu tempat.</p>
            </div>

            <p class="text-xs text-coffee-500">&copy; {{ now()->year }} Kopi Titik Nol</p>
        </aside>

        <section class="login-panel">
            <div class="login-card">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <x-brand-mark class="size-10" />
                    <p class="font-bold tracking-wide">SIMKOTOL</p>
                </div>

                <h1 class="text-2xl font-bold tracking-tight">Masuk</h1>
                <p class="mt-1 text-sm text-muted">Gunakan akun yang diberikan oleh pemilik kedai.</p>

                <form method="POST" action="{{ route('login.store') }}" class="mt-8 flex flex-col gap-5" x-data="{ busy: false }" x-on:submit="busy = true">
                    @csrf

                    <x-form.field label="Username" for="username">
                        <input id="username" name="username" type="text" value="{{ old('username') }}" autocomplete="username" autocapitalize="none" required autofocus
                               @class(['input', 'input-invalid' => $errors->has('username')])>
                    </x-form.field>

                    <x-form.field label="Password" for="password">
                        <input id="password" name="password" type="password" autocomplete="current-password" required @class(['input', 'input-invalid' => $errors->has('password')])>
                    </x-form.field>

                    <label class="flex items-center gap-2 text-sm text-muted">
                        <input type="checkbox" name="remember" value="1" class="size-4 rounded accent-coffee-900">
                        Ingat saya di perangkat ini
                    </label>

                    <x-ui.button class="w-full" x-bind:disabled="busy">
                        <span x-show="!busy">Masuk</span>
                        <span x-show="busy" x-cloak>Memproses…</span>
                    </x-ui.button>
                </form>
            </div>
        </section>
    </div>
@endsection
