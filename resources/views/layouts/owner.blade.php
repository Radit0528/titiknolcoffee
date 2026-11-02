@extends('layouts.app')

@section('body')
    <x-sidebar.owner />

    <div class="lg:pl-64">
        <header class="topbar">
            <button type="button" class="btn btn-ghost btn-icon lg:hidden" x-data x-on:click="$store.sidebar.toggle()" aria-label="Buka menu">
                <x-ui.icon name="menu" class="size-5" />
            </button>
            <x-topbar.user />
        </header>

        <main class="main-content">
            @yield('content')
        </main>
    </div>
@endsection
