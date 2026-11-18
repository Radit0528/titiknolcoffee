@extends('layouts.app')

@section('title', 'Menu')

@use('App\Support\Format')

@section('body')
    <div class="public-shell">
        <header class="public-header">
            <div class="public-container flex h-16 items-center gap-3">
                <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2.5">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="" class="size-9 rounded-lg object-contain">
                    @else
                        <x-brand-mark class="size-9" />
                    @endif
                    <span class="truncate font-bold">{{ $settings['store_name'] }}</span>
                </a>
                <nav class="ml-auto flex items-center gap-1 text-sm">
                    <a href="#menu" class="public-nav-link hidden sm:inline-flex">Menu</a>
                    <a href="#lokasi" class="public-nav-link hidden sm:inline-flex">Lokasi</a>
                    @auth
                        <x-ui.button :href="route(auth()->user()->role->homeRoute())" size="sm" icon="arrow-right">Buka aplikasi</x-ui.button>
                    @else
                        <x-ui.button :href="route('login')" variant="secondary" size="sm" icon="log-in">Masuk staf</x-ui.button>
                    @endauth
                </nav>
            </div>
        </header>

        <main>
            <section class="public-hero">
                <div class="public-container">
                    <p class="text-sm font-semibold tracking-wide text-amber-400 uppercase">Selamat datang</p>
                    <h1 class="public-hero-title">{{ $settings['store_name'] }}</h1>
                    <div class="mt-5 flex flex-wrap gap-2">
                        @if ($settings['store_hours'])
                            <span class="public-chip"><x-ui.icon name="clock" /> {{ $settings['store_hours'] }}</span>
                        @endif
                        @if ($settings['store_address'])
                            <span class="public-chip"><x-ui.icon name="map-pin" /> {{ $settings['store_address'] }}</span>
                        @endif
                    </div>
                    <div class="mt-7 flex flex-wrap gap-2">
                        <a href="#menu" class="btn public-hero-cta">Lihat menu</a>
                    </div>
                </div>
            </section>

            @if ($videos->isNotEmpty())
                <section class="public-container pt-8" aria-label="Video"
                         x-data="videoStrip(@js($videos->map(fn ($v) => ['title' => $v->title, 'caption' => $v->caption, 'src' => $v->videoUrl()])->values()))">
                    <div class="video-strip">
                        @foreach ($videos as $video)
                            <figure class="video-card">
                                <video src="{{ $video->videoUrl() }}" data-id="{{ $video->id }}" muted loop playsinline preload="metadata"
                                       x-on:click="open({{ $loop->index }})"></video>
                                <button type="button" class="video-expand" x-on:click="open({{ $loop->index }})" aria-label="Putar {{ $video->title }} layar penuh">
                                    <x-ui.icon name="maximize-2" />
                                </button>
                                <button type="button" class="video-sound" x-on:click="toggleSound({{ $video->id }})"
                                        x-bind:aria-label="soundOn === {{ $video->id }} ? 'Matikan suara' : 'Nyalakan suara'">
                                    <span x-show="soundOn !== {{ $video->id }}"><x-ui.icon name="volume-x" /></span>
                                    <span x-show="soundOn === {{ $video->id }}" x-cloak><x-ui.icon name="volume-2" /></span>
                                </button>
                                <figcaption class="video-card-text">
                                    <p class="text-sm leading-tight font-bold">{{ $video->title }}</p>
                                    @if ($video->caption)<p class="mt-0.5 line-clamp-2 text-xs text-white/85">{{ $video->caption }}</p>@endif
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>

                    {{-- Full-screen viewer: plays with sound and moves on to the next video by itself. --}}
                    <div class="story" x-ref="viewer" x-show="isOpen" x-cloak role="dialog" aria-modal="true" aria-label="Video promo layar penuh"
                         x-on:keydown.escape.window="close()"
                         x-on:keydown.arrow-right.window="isOpen && go(1)"
                         x-on:keydown.arrow-left.window="isOpen && go(-1)">
                        <div class="story-frame">
                            <video x-ref="player" class="story-video" playsinline preload="auto"
                                   x-on:ended="go(1)" x-on:timeupdate="onTime"></video>

                            <div class="story-zones">
                                <button type="button" class="story-zone" x-on:click="go(-1)" aria-label="Video sebelumnya"></button>
                                <button type="button" class="story-zone" x-on:click="togglePause()" x-bind:aria-label="paused ? 'Lanjutkan' : 'Jeda'"></button>
                                <button type="button" class="story-zone" x-on:click="go(1)" aria-label="Video berikutnya"></button>
                            </div>

                            <div class="story-paused" x-show="paused" x-cloak><x-ui.icon name="pause" /></div>

                            <div class="story-top">
                                <div class="story-bars">
                                    <template x-for="(item, i) in items" :key="i">
                                        <span class="story-bar"><span x-bind:style="{ width: barWidth(i) }"></span></span>
                                    </template>
                                </div>
                                <div class="mt-3 flex items-center justify-between gap-3">
                                    <span class="text-xs font-semibold text-white/80" x-text="(current + 1) + ' / ' + items.length"></span>
                                    <div class="flex gap-2">
                                        <button type="button" class="story-btn" x-on:click="toggleMute()" x-bind:aria-label="muted ? 'Nyalakan suara' : 'Matikan suara'">
                                            <span x-show="muted" x-cloak><x-ui.icon name="volume-x" /></span>
                                            <span x-show="!muted"><x-ui.icon name="volume-2" /></span>
                                        </button>
                                        <button type="button" class="story-btn" x-on:click="close()" aria-label="Tutup"><x-ui.icon name="x" /></button>
                                    </div>
                                </div>
                            </div>

                            <div class="story-info">
                                <p class="text-xl leading-tight font-bold" x-text="items[current]?.title"></p>
                                <p class="mt-1 line-clamp-3 text-sm text-white/85" x-show="items[current]?.caption" x-text="items[current]?.caption"></p>
                                <p class="mt-2 text-xs text-white/70">Pesan langsung di kasir.</p>
                            </div>
                        </div>
                    </div>
                </section>
            @endif

            @if ($banners->isNotEmpty())
                <section class="public-container pt-8" aria-label="Promo">
                    <h2 class="public-section-title">Promo</h2>
                    <div class="promo-strip">
                        @foreach ($banners as $banner)
                            <figure class="promo-card">
                                <img src="{{ $banner->imageUrl() }}" alt="" loading="lazy">
                                <figcaption class="promo-card-text">
                                    <p class="text-sm leading-tight font-bold">{{ $banner->title }}</p>
                                    @if ($banner->description)<p class="mt-0.5 line-clamp-2 text-xs text-white/85">{{ $banner->description }}</p>@endif
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                </section>
            @endif

            <section id="menu" class="public-container scroll-mt-20 pt-8 pb-4">
                <h2 class="public-section-title">Menu</h2>

                @if ($categories->isEmpty())
                    <div class="card">
                        <x-ui.empty-state icon="coffee" title="Menu belum tersedia" description="Silakan kembali lagi nanti." />
                    </div>
                @else
                    <nav class="public-category-nav" aria-label="Kategori menu">
                        @foreach ($categories as $category)
                            <a href="#kategori-{{ $category->id }}" class="category-tab">{{ $category->name }}</a>
                        @endforeach
                    </nav>

                    @foreach ($categories as $category)
                        <div id="kategori-{{ $category->id }}" class="scroll-mt-32 pt-6">
                            <h3 class="mb-3 text-lg font-bold">{{ $category->name }}</h3>
                            <div class="public-menu-grid">
                                @foreach ($category->products as $product)
                                    @php($isSoldOut = in_array($product->id, $soldOut, true))
                                    <article @class(['public-menu-card', 'is-sold-out' => $isSoldOut])>
                                        <div class="public-menu-media">
                                            @if ($product->imageUrl())
                                                <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" loading="lazy">
                                            @else
                                                <x-ui.icon name="coffee" />
                                            @endif
                                            @if ($isSoldOut)
                                                <span class="public-sold-out">Habis</span>
                                            @endif
                                        </div>
                                        <div class="flex flex-1 flex-col gap-1 p-3">
                                            <p class="leading-snug font-semibold">{{ $product->name }}</p>
                                            @if ($product->description)
                                                <p class="line-clamp-2 text-xs text-muted">{{ $product->description }}</p>
                                            @endif
                                            <p class="mt-auto pt-2 font-bold tabular-nums">{{ Format::rupiah($product->price) }}</p>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @endif
            </section>

            <section id="lokasi" class="public-container scroll-mt-20 py-10">
                <div class="card grid gap-6 p-6 sm:grid-cols-[1fr_auto] sm:items-center">
                    <div class="flex flex-col gap-3">
                        <h2 class="text-lg font-bold">Kunjungi kami</h2>
                        @if ($settings['store_address'])
                            <p class="public-info"><x-ui.icon name="map-pin" /> {{ $settings['store_address'] }}</p>
                        @endif
                        @if ($settings['store_hours'])
                            <p class="public-info"><x-ui.icon name="clock" /> {{ $settings['store_hours'] }}</p>
                        @endif
                        @if ($settings['store_phone'])
                            <p class="public-info"><x-ui.icon name="phone" /> <a href="tel:{{ preg_replace('/[^0-9+]/', '', $settings['store_phone']) }}" class="hover:underline">{{ $settings['store_phone'] }}</a></p>
                        @endif
                    </div>
                    @if ($settings['store_maps_url'])
                        <x-ui.button :href="$settings['store_maps_url']" variant="secondary" icon="map-pin" target="_blank" rel="noopener">Buka di Google Maps</x-ui.button>
                    @endif
                </div>
            </section>
        </main>

        <footer class="border-t border-line py-6 text-center text-xs text-muted">
            &copy; {{ now()->year }} {{ $settings['store_name'] }} · Harga dapat berubah sewaktu-waktu.
        </footer>
    </div>
@endsection
