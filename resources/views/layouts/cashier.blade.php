@extends('layouts.app')

@section('body')
    <header class="topbar">
        <a href="{{ route('cashier.pos') }}" class="flex items-center gap-3">
            <x-brand-mark />
            <div class="leading-tight">
                <p class="text-sm font-bold tracking-wide">SIMKOTOL</p>
                <p class="text-[0.7rem] text-muted">Kasir</p>
            </div>
        </a>

        <nav class="cashier-nav ml-4" aria-label="Menu kasir">
            <x-cashier.nav-link route="cashier.pos" icon="shopping-cart">Kasir</x-cashier.nav-link>
            <x-cashier.nav-link route="cashier.drafts.index" icon="save">Draft @if ($draftCount)<span class="sidebar-badge">{{ $draftCount }}</span>@endif</x-cashier.nav-link>
            <x-cashier.nav-link route="orders.index" active="orders.*" icon="receipt-text">Riwayat</x-cashier.nav-link>
        </nav>

        <div class="ml-auto flex items-center gap-3">
            @if ($currentShift)
                <span class="shift-chip" title="Modal awal {{ \App\Support\Format::rupiah($currentShift->opening_cash) }}">
                    Shift sejak {{ $currentShift->opened_at->format('H:i') }}
                </span>
            @endif
            <x-topbar.user />
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-ui.button variant="ghost" size="sm" icon="log-out" aria-label="Keluar"><span class="hidden sm:inline">Keluar</span></x-ui.button>
            </form>
        </div>
    </header>

    <main class="main-content pb-24 md:pb-8">
        @yield('content')
    </main>

    <nav class="cashier-nav-mobile" aria-label="Menu kasir">
        <x-cashier.nav-link route="cashier.pos" icon="shopping-cart">Kasir</x-cashier.nav-link>
        <x-cashier.nav-link route="cashier.drafts.index" icon="save">Draft{{ $draftCount ? " ($draftCount)" : '' }}</x-cashier.nav-link>
        <x-cashier.nav-link route="orders.index" active="orders.*" icon="receipt-text">Riwayat</x-cashier.nav-link>
    </nav>
@endsection
