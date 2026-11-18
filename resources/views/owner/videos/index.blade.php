@extends('layouts.owner')

@section('title', 'Video Promo')

@section('content')
    <x-ui.page-header title="Video Promo" subtitle="Berputar otomatis di halaman utama untuk pelanggan, urut dari angka urutan terkecil.">
        <x-ui.button :href="route('owner.videos.create')" icon="plus">Tambah video</x-ui.button>
    </x-ui.page-header>

    @if ($videos->isEmpty())
        <div class="card">
            <x-ui.empty-state icon="video" title="Belum ada video" description="Unggah video potret (MP4) suasana kedai, proses meracik, atau menu andalan.">
                <x-ui.button :href="route('owner.videos.create')" icon="plus">Tambah video</x-ui.button>
            </x-ui.empty-state>
        </div>
    @else
        <div class="video-admin-grid">
            @foreach ($videos as $video)
                <article class="card overflow-hidden">
                    <div class="video-admin-frame">
                        <video src="{{ $video->videoUrl() }}" muted loop playsinline preload="metadata"
                               x-data x-on:mouseenter="$el.play().catch(() => {})" x-on:mouseleave="$el.pause()"></video>
                    </div>
                    <div class="flex flex-col gap-2 p-3">
                        <div class="flex items-start justify-between gap-2">
                            <h2 class="text-sm leading-snug font-semibold">{{ $video->title }}</h2>
                            <x-ui.badge :variant="$video->is_active ? 'success' : 'neutral'">{{ $video->is_active ? 'Tayang' : 'Nonaktif' }}</x-ui.badge>
                        </div>
                        @if ($video->caption)
                            <p class="line-clamp-2 text-xs text-muted">{{ $video->caption }}</p>
                        @endif
                        <div class="flex items-center justify-between gap-1">
                            <span class="text-xs text-muted">Urutan {{ $video->sort_order }}</span>
                            <div class="flex gap-1">
                                <x-ui.button :href="route('owner.videos.edit', $video)" variant="ghost" size="sm" class="btn-icon" icon="pencil" aria-label="Ubah" />
                                <x-ui.modal :action="route('owner.videos.destroy', $video)" title="Hapus video {{ $video->title }}?" message="File videonya juga akan dihapus." />
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
