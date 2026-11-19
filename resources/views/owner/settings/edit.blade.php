@extends('layouts.owner')

@section('title', 'Pengaturan Toko')

@section('content')
    <x-ui.page-header title="Pengaturan Toko" subtitle="Identitas toko, struk, pajak, dan pembulatan pembayaran tunai." />

    <form method="POST" action="{{ route('owner.settings.update') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
        @csrf
        @method('PUT')

        <div class="flex flex-col gap-6 lg:col-span-2">
            <section class="card">
                <div class="card-header"><h2 class="card-title">Identitas toko</h2></div>
                <div class="card-body flex flex-col gap-5">
                    <x-form.field label="Nama toko" for="store_name" required>
                        <input id="store_name" name="store_name" type="text" maxlength="100" required value="{{ old('store_name', $settings['store_name']) }}"
                               @class(['input', 'input-invalid' => $errors->has('store_name')])>
                    </x-form.field>
                    <x-form.field label="Alamat" for="store_address">
                        <input id="store_address" name="store_address" type="text" maxlength="255" value="{{ old('store_address', $settings['store_address']) }}"
                               @class(['input', 'input-invalid' => $errors->has('store_address')])>
                    </x-form.field>
                    <x-form.field label="Nomor telepon" for="store_phone">
                        <input id="store_phone" name="store_phone" type="tel" maxlength="30" value="{{ old('store_phone', $settings['store_phone']) }}"
                               @class(['input sm:max-w-64', 'input-invalid' => $errors->has('store_phone')])>
                    </x-form.field>
                    <x-form.field label="Jam buka" for="store_hours" hint="Tampil di halaman pelanggan. Contoh: Setiap hari 07.00–22.00">
                        <input id="store_hours" name="store_hours" type="text" maxlength="100" value="{{ old('store_hours', $settings['store_hours']) }}"
                               @class(['input', 'input-invalid' => $errors->has('store_hours')])>
                    </x-form.field>
                    <x-form.field label="Link Google Maps" for="store_maps_url" hint="Opsional. Salin dari tombol Bagikan di Google Maps.">
                        <input id="store_maps_url" name="store_maps_url" type="url" maxlength="500" placeholder="https://maps.app.goo.gl/…" value="{{ old('store_maps_url', $settings['store_maps_url']) }}"
                               @class(['input', 'input-invalid' => $errors->has('store_maps_url')])>
                    </x-form.field>
                    <x-form.field label="Link Instagram" for="store_instagram_url" hint="Opsional. Link profil Instagram kedai, tampil di halaman pelanggan.">
                        <input id="store_instagram_url" name="store_instagram_url" type="url" maxlength="255" placeholder="https://www.instagram.com/…" value="{{ old('store_instagram_url', $settings['store_instagram_url']) }}"
                               @class(['input', 'input-invalid' => $errors->has('store_instagram_url')])>
                    </x-form.field>
                </div>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">Pajak &amp; biaya</h2></div>
                <div class="card-body grid gap-5 sm:grid-cols-3">
                    <x-form.field label="Pajak (PB1)" for="tax_percent" hint="Dihitung dari subtotal setelah diskon + service." required>
                        <div class="input-group">
                            <input id="tax_percent" name="tax_percent" type="number" min="0" max="100" step="0.01" required value="{{ old('tax_percent', $settings['tax_percent']) }}"
                                   @class(['input', 'input-invalid' => $errors->has('tax_percent')])>
                            <span class="input-addon">%</span>
                        </div>
                    </x-form.field>
                    <x-form.field label="Service charge" for="service_charge_percent" hint="Dihitung dari subtotal setelah diskon." required>
                        <div class="input-group">
                            <input id="service_charge_percent" name="service_charge_percent" type="number" min="0" max="100" step="0.01" required
                                   value="{{ old('service_charge_percent', $settings['service_charge_percent']) }}" @class(['input', 'input-invalid' => $errors->has('service_charge_percent')])>
                            <span class="input-addon">%</span>
                        </div>
                    </x-form.field>
                    <x-form.field label="Pembulatan tunai" for="cash_rounding" hint="Ke kelipatan terdekat, hanya untuk tunai." required>
                        <select id="cash_rounding" name="cash_rounding" class="select">
                            @foreach ($roundingOptions as $option)
                                <option value="{{ $option }}" @selected((int) old('cash_rounding', $settings['cash_rounding']) === $option)>
                                    {{ $option === 0 ? 'Tanpa pembulatan' : \App\Support\Format::rupiah($option) }}
                                </option>
                            @endforeach
                        </select>
                    </x-form.field>
                </div>
            </section>
        </div>

        <div class="flex flex-col gap-6">
            <section class="card" x-data="imagePicker(@js($logoUrl))">
                <div class="card-header"><h2 class="card-title">Struk</h2></div>
                <div class="card-body flex flex-col gap-5">
                    <x-form.field label="Ukuran kertas" for="receipt_width" required>
                        <select id="receipt_width" name="receipt_width" class="select">
                            @foreach ([58, 80] as $width)
                                <option value="{{ $width }}" @selected((int) old('receipt_width', $settings['receipt_width']) === $width)>{{ $width }} mm</option>
                            @endforeach
                        </select>
                    </x-form.field>
                    <x-form.field label="Footer struk" for="receipt_footer">
                        <textarea id="receipt_footer" name="receipt_footer" rows="2" maxlength="255" class="textarea min-h-16">{{ old('receipt_footer', $settings['receipt_footer']) }}</textarea>
                    </x-form.field>
                    <div class="field">
                        <span class="label">Logo</span>
                        <div class="grid h-24 place-items-center overflow-hidden rounded-lg border border-dashed border-line bg-coffee-50 text-coffee-300">
                            <img x-show="preview" x-bind:src="preview" alt="" class="max-h-20 object-contain" x-cloak>
                            <span x-show="!preview"><x-ui.icon name="image-plus" class="size-6" /></span>
                        </div>
                        <input type="hidden" name="remove_logo" x-bind:value="remove ? 1 : 0">
                        <input x-ref="file" name="logo" type="file" accept="image/jpeg,image/png,image/webp" x-on:change="pick"
                               class="hint file:mr-3 file:rounded-md file:border-0 file:bg-coffee-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-coffee-900">
                        <p class="hint">Dicetak hitam putih. Maks. 1 MB.</p>
                        <p class="field-error" x-show="error" x-text="error" x-cloak></p>
                        @error('logo') <p class="field-error">{{ $message }}</p> @enderror
                        <button type="button" class="self-start text-xs font-semibold text-danger hover:underline" x-show="preview" x-cloak x-on:click="clear">Hapus logo</button>
                    </div>
                </div>
            </section>
            <x-ui.button class="w-full">Simpan pengaturan</x-ui.button>
        </div>
    </form>

    {{-- QRIS Kedai: separate form so it never mixes with the store settings above --}}
    <section class="card mt-6" id="qris-kedai" x-data="qrisUploader(@js(old('qris_static_payload', '')))">
        <div class="card-header">
            <div>
                <h2 class="card-title">QRIS Kedai</h2>
                <p class="mt-0.5 text-xs text-muted">QRIS stiker kedai dijadikan QR bernominal di layar kasir. Kasir mengonfirmasi pembayaran secara manual.</p>
            </div>
            @if ($qrisInfo)
                <x-ui.badge variant="success">Aktif</x-ui.badge>
            @else
                <x-ui.badge>Belum diatur</x-ui.badge>
            @endif
        </div>
        <div class="card-body grid gap-6 lg:grid-cols-2">
            <div class="flex flex-col gap-3">
                @if ($qrisInfo)
                    <dl class="totals rounded-xl border border-line p-4">
                        <div><dt>Merchant</dt><dd>{{ $qrisInfo['merchant'] }}</dd></div>
                        <div><dt>Kota</dt><dd>{{ $qrisInfo['city'] ?: '–' }}</dd></div>
                        <div><dt>NMID</dt><dd class="font-mono">{{ $qrisInfo['nmid'] ?? '–' }}</dd></div>
                    </dl>
                @endif
                <form method="POST" action="{{ route('owner.settings.qris.update') }}" class="flex flex-col gap-3">
                    @csrf
                    @method('PUT')
                    <label for="qris_image" class="label">{{ $qrisInfo ? 'Ganti QRIS' : 'Unggah gambar QRIS kedai' }}</label>
                    <input id="qris_image" type="file" accept="image/*" x-on:change="read"
                           class="hint file:mr-3 file:rounded-md file:border-0 file:bg-coffee-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-coffee-900">
                    <p class="hint">Foto atau file QRIS statis dari aplikasi merchant (GoPay Merchant, bank, dll.). Gambar hanya dibaca di browser; yang disimpan hanya isi kodenya.</p>
                    <p class="text-sm text-success" x-show="merchant" x-cloak>Terbaca: <b x-text="merchant"></b></p>
                    <p class="field-error" x-show="error" x-text="error" x-cloak></p>
                    <details class="text-xs text-muted">
                        <summary class="cursor-pointer">Atau tempel isi kode QRIS</summary>
                        <textarea name="qris_static_payload" x-model="payload" rows="3" maxlength="1000" class="textarea mt-2 font-mono text-xs" placeholder="000201010211…"></textarea>
                    </details>
                    @error('qris_static_payload', 'qris') <p class="field-error">{{ $message }}</p> @enderror
                    <x-ui.button class="self-start" x-bind:disabled="!payload">Simpan QRIS</x-ui.button>
                </form>
                @if ($qrisInfo)
                    <form method="POST" action="{{ route('owner.settings.qris.destroy') }}">
                        @csrf
                        @method('DELETE')
                        <button class="text-xs font-semibold text-danger hover:underline">Nonaktifkan QRIS Kedai</button>
                    </form>
                @endif
            </div>
            <div class="rounded-xl bg-warning-soft p-4 text-sm text-ink">
                <p class="font-semibold">Penting untuk kasir</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>Pembayaran QRIS Kedai <b>tidak terkonfirmasi otomatis</b>. Kasir menekan "Sudah diterima" setelah uang masuk.</li>
                    <li>Cek di <b>aplikasi merchant kedai</b> (mis. GoPay Merchant), bukan di layar HP pelanggan. Bukti transfer bisa dipalsukan.</li>
                    <li>Untuk konfirmasi otomatis, gunakan metode DOKU.</li>
                </ul>
            </div>
        </div>
    </section>
@endsection
