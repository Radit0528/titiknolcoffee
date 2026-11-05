@extends('layouts.owner')

@section('title', 'Banner Promo')

@section('content')
    <x-ui.page-header title="Banner Promo" subtitle="Ditampilkan di layar kasir agar kasir ingat menawarkan promo yang sedang berjalan.">
        <x-ui.button :href="route('owner.banners.create')" icon="plus">Buat banner</x-ui.button>
    </x-ui.page-header>

    @if ($banners->isEmpty())
        <div class="card">
            <x-ui.empty-state icon="megaphone" title="Belum ada banner" description="Buat banner untuk promo, menu baru, atau info penting bagi kasir.">
                <x-ui.button :href="route('owner.banners.create')" icon="plus">Buat banner</x-ui.button>
            </x-ui.empty-state>
        </div>
    @else
        <div class="banner-admin-grid">
            @foreach ($banners as $banner)
                @php([$statusLabel, $statusVariant] = $banner->status())
                <article class="card overflow-hidden">
                    <img src="{{ $banner->imageUrl() }}" alt="" class="banner-admin-image" loading="lazy">
                    <div class="flex flex-col gap-2 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <h2 class="font-semibold leading-snug">{{ $banner->title }}</h2>
                            <x-ui.badge :variant="$statusVariant">{{ $statusLabel }}</x-ui.badge>
                        </div>
                        @if ($banner->description)
                            <p class="text-sm text-muted">{{ $banner->description }}</p>
                        @endif
                        <p class="text-xs text-muted">
                            {{ $banner->starts_at?->translatedFormat('d M Y H:i') ?? 'Mulai sekarang' }} –
                            {{ $banner->ends_at?->translatedFormat('d M Y H:i') ?? 'tanpa batas' }} · Urutan {{ $banner->sort_order }}
                        </p>
                        <div class="mt-1 flex justify-end gap-1">
                            <x-ui.button :href="route('owner.banners.edit', $banner)" variant="ghost" size="sm" class="btn-icon" icon="pencil" aria-label="Ubah" />
                            <x-ui.modal :action="route('owner.banners.destroy', $banner)" title="Hapus banner {{ $banner->title }}?" message="Gambar banner juga akan dihapus." />
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
