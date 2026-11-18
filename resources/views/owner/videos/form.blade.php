@extends('layouts.owner')

@use('App\Http\Requests\Owner\MenuVideoRequest')

@section('title', $video->exists ? 'Ubah Video' : 'Tambah Video')

@section('content')
    <x-ui.page-header :title="$video->exists ? 'Ubah Video Promo' : 'Tambah Video Promo'" :back="route('owner.videos.index')" back-label="Video promo" />

    <form method="POST" action="{{ $video->exists ? route('owner.videos.update', $video) : route('owner.videos.store') }}" enctype="multipart/form-data"
          class="grid max-w-5xl gap-6 lg:grid-cols-[1fr_20rem]" x-data="{ busy: false }" x-on:submit="busy = true">
        @csrf
        @if ($video->exists) @method('PUT') @endif

        <div class="card self-start">
            <div class="card-body flex flex-col gap-5">
                <x-form.field label="Judul" for="title" required hint="Tampil di bawah video. Contoh: Es Kopi Susu Aren">
                    <input id="title" name="title" type="text" maxlength="100" required value="{{ old('title', $video->title) }}" @class(['input', 'input-invalid' => $errors->has('title')])>
                </x-form.field>
                <x-form.field label="Keterangan" for="caption" hint="Opsional, maks. 255 karakter.">
                    <textarea id="caption" name="caption" rows="2" maxlength="255" @class(['input h-auto py-2', 'input-invalid' => $errors->has('caption')])>{{ old('caption', $video->caption) }}</textarea>
                </x-form.field>
                <x-form.field label="Urutan" for="sort_order" hint="Angka kecil tampil lebih dulu.">
                    <input id="sort_order" name="sort_order" type="number" min="0" max="999" step="1" value="{{ old('sort_order', $video->sort_order) }}" @class(['input sm:max-w-32', 'input-invalid' => $errors->has('sort_order')])>
                </x-form.field>
                <x-form.switch name="is_active" :checked="old('is_active', $video->is_active)" label="Aktif" description="Video nonaktif tidak tampil di halaman utama." />
            </div>
            <div class="flex justify-end gap-2 border-t border-line px-5 py-4">
                <x-ui.button :href="route('owner.videos.index')" variant="secondary">Batal</x-ui.button>
                <x-ui.button x-bind:disabled="busy">
                    <span x-show="!busy">Simpan</span>
                    <span x-show="busy" x-cloak>Mengunggah…</span>
                </x-ui.button>
            </div>
        </div>

        <div class="card self-start" x-data="videoPicker(@js($video->exists ? $video->videoUrl() : null), {{ MenuVideoRequest::VIDEO_MAX_KB / 1024 }})">
            <div class="card-header"><h2 class="card-title">Video{{ $video->exists ? '' : ' *' }}</h2></div>
            <div class="card-body flex flex-col gap-3">
                <div class="video-upload-frame">
                    <video x-show="preview" x-bind:src="preview" x-on:loadedmetadata="checkShape" muted loop playsinline controls preload="metadata" x-cloak></video>
                    <span x-show="!preview"><x-ui.icon name="video" class="size-8" /></span>
                </div>
                <input id="video" name="video" type="file" accept="video/mp4" @required(! $video->exists)
                       class="hint file:mr-3 file:rounded-md file:border-0 file:bg-coffee-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-coffee-900" x-on:change="pick">
                <p class="hint">MP4 potret (9:16), maks. {{ MenuVideoRequest::VIDEO_MAX_KB / 1024 }} MB, idealnya 10–30 detik.{{ $video->exists ? ' Kosongkan jika tidak ingin mengganti video.' : '' }}</p>
                <p class="text-xs text-warning" x-show="warning" x-text="warning" x-cloak></p>
                <p class="field-error" x-show="error" x-text="error" x-cloak></p>
                @error('video') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </form>
@endsection
