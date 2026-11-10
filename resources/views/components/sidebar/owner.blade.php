{{-- Only modules that already have a working backend are listed. --}}
<aside class="sidebar" x-data x-bind:data-open="$store.sidebar.open">
    <div class="sidebar-brand">
        <x-brand-mark />
        <div class="leading-tight">
            <p class="text-sm font-bold tracking-wide text-white">SIMKOTOL</p>
            <p class="text-[0.7rem] text-coffee-300">Kopi Titik Nol</p>
        </div>
        <button type="button" class="ml-auto text-coffee-300 hover:text-white lg:hidden" x-on:click="$store.sidebar.close()" aria-label="Tutup menu">
            <x-ui.icon name="x" class="size-5" />
        </button>
    </div>

    <nav class="sidebar-nav">
        <x-sidebar.link route="owner.dashboard" icon="layout-dashboard">Dashboard</x-sidebar.link>
        <x-sidebar.link route="orders.index" active="orders.*" icon="receipt-text">Transaksi</x-sidebar.link>
        <x-sidebar.link route="owner.reports.index" active="owner.reports.*" icon="chart-column">Laporan</x-sidebar.link>

        <p class="sidebar-section">Menu</p>
        <x-sidebar.link route="owner.products.index" active="owner.products.*" icon="coffee">Produk</x-sidebar.link>
        <x-sidebar.link route="owner.categories.index" active="owner.categories.*" icon="tags">Kategori</x-sidebar.link>
        <x-sidebar.link route="owner.recipes.index" active="owner.recipes.*" icon="book-open-text">Resep &amp; HPP</x-sidebar.link>

        <p class="sidebar-section">Inventori</p>
        <x-sidebar.link route="owner.ingredients.index" active="owner.ingredients.*" icon="boxes" :badge="$lowStockCount ?: null">Bahan Baku</x-sidebar.link>
        <x-sidebar.link route="owner.purchases.index" active="owner.purchases.*" icon="shopping-basket">Pembelian</x-sidebar.link>
        <x-sidebar.link route="owner.stock.index" active="owner.stock.*" icon="arrow-left-right">Mutasi Stok</x-sidebar.link>

        <p class="sidebar-section">Toko</p>
        <x-sidebar.link route="owner.vouchers.index" active="owner.vouchers.*" icon="ticket">Voucher</x-sidebar.link>
        <x-sidebar.link route="owner.banners.index" active="owner.banners.*" icon="megaphone">Banner Promo</x-sidebar.link>
        <x-sidebar.link route="owner.settings.edit" icon="settings">Pengaturan</x-sidebar.link>

        <p class="sidebar-section">Keamanan</p>
        <x-sidebar.link route="owner.staff.index" active="owner.staff.*" icon="users">Staf Kasir</x-sidebar.link>
        <x-sidebar.link route="owner.account.edit" icon="key-round">Akun &amp; PIN</x-sidebar.link>
        <x-sidebar.link route="owner.audit.index" icon="history">Audit Log</x-sidebar.link>
    </nav>

    <div class="sidebar-footer">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="sidebar-link w-full">
                <x-ui.icon name="log-out" />
                <span>Keluar</span>
            </button>
        </form>
    </div>
</aside>
<div class="sidebar-overlay" x-data x-show="$store.sidebar.open" x-cloak x-transition.opacity x-on:click="$store.sidebar.close()"></div>
