# SIMKOTOL — Sistem Manajemen Kopi Titik Nol

Aplikasi POS dan inventory kedai kopi (Laravel 13, MySQL, Blade, Tailwind CSS 4, Alpine.js).

## Status pengerjaan

| Tahap | Isi | Status |
|---|---|---|
| 1 — Foundation | Laravel, MySQL, Tailwind/Vite, Alpine, login (rate limit), role Owner/Kasir, layout | Selesai |
| 2 — Master Data | Kategori, produk (+gambar), bahan baku, resep/BOM + HPP & margin | Selesai |
| 3 — Inventory | Pembelian (stok masuk), stok keluar, penyesuaian/opname, riwayat mutasi, notifikasi stok menipis | Selesai |
| 4 — POS | Buka shift, katalog + keranjang, draft/tahan pesanan, voucher, checkout tunai, potong stok otomatis, struk 58/80mm, riwayat transaksi, pengaturan toko, manajemen voucher | Selesai |
| 6 — Shift | Kas masuk/keluar, ringkasan shift, tutup shift dengan hitung selisih kas, laporan shift (layar & cetak) | Selesai |
| 7 — Laporan | Dashboard (omzet, laba kotor, grafik 14 hari, menu terlaris), laporan penjualan/laba kotor/bahan baku/shift dengan filter, export PDF & Excel | Selesai |
| 8 — Keamanan & promo | Staf kasir (tambah, nonaktifkan, reset password), PIN owner, void transaksi dengan PIN owner, banner promo di layar kasir, halaman audit log | Selesai |
| Tambahan | Halaman pelanggan di `/` (promo, menu, info kedai) dan **video promo** potret yang berputar otomatis (menu *Video Promo*, terpisah dari produk) | Selesai |
| 5 — Payment | QRIS Midtrans — **ditunda ke tahap akhir** (sebelum final testing) | Ditunda |
| 9 | Final testing & dokumentasi | Belum |

## Instalasi di Laragon (Windows)

Syarat: Laragon dengan **PHP 8.4**, MySQL, Composer, Node.js 20+.
Ekstensi PHP yang dipakai: `pdo_mysql`, `mbstring`, `fileinfo`, `gd` (validasi gambar), `intl`.
Untuk menjalankan test juga perlu `pdo_sqlite` dan `sqlite3`.

1. Salin folder `simkotol` ke `C:\laragon\www\simkotol`.
2. Buka **Terminal** Laragon di folder tersebut, lalu jalankan:

   ```bash
   composer install
   copy .env.example .env
   php artisan key:generate
   ```

3. Buat database `simkotol` (Laragon → Database, atau `mysql -u root -e "CREATE DATABASE simkotol"`).
   `.env.example` sudah memakai user `root` tanpa password (bawaan Laragon). Sesuaikan jika berbeda.
4. Migrasi, data awal, dan aset:

   ```bash
   php artisan migrate --seed
   php artisan storage:link
   npm install
   npm run build
   ```

5. Buka `http://simkotol.test` (Laragon → Reload agar virtual host terbentuk),
   atau jalankan `php artisan serve` lalu buka `http://127.0.0.1:8000`.

### Akun awal

| Role | Username | Password |
|---|---|---|
| Owner | `owner` | `password` |
| Kasir | `kasir` | `password` |

**Atur PIN owner** di menu *Akun & PIN* sebelum operasional; tanpa PIN, transaksi tidak bisa di-void.

Kasir harus **membuka shift** (isi modal awal) sebelum bisa bertransaksi. Data contoh juga berisi
voucher `NGOPIPAGI` (10%, maks Rp5.000, min. belanja Rp20.000).

**Ganti password ini sebelum dipakai untuk operasional.** Seeder data contoh (menu, bahan)
hanya berjalan jika `APP_ENV` bukan `production`.

### Video menu (halaman pelanggan)

Owner mengunggah video MP4 potret (9:16, maks. 20 MB) di menu **Video Promo**. Video aktif berputar otomatis tanpa suara di halaman utama (di bawah hero), hanya saat terlihat di layar; pelanggan bisa mengetuk video untuk menyalakan suara. Batas upload bawaan PHP
biasanya hanya 2 MB, jadi naikkan di `php.ini` (Laragon → PHP → php.ini):

```ini
upload_max_filesize = 25M
post_max_size = 30M
```

Lalu restart Laragon. Di server produksi dengan Nginx, tambahkan juga `client_max_body_size 30m;`.

### QRIS Kedai (QRIS statis → bernominal)

Selain tunai dan DOKU, kasir bisa memakai **QRIS Kedai**: QRIS stiker milik kedai diubah menjadi QR berisi
nominal pesanan (tag 01 `11`→`12`, tambah tag 54 nominal, hitung ulang CRC16). Aktifkan di **Pengaturan → QRIS Kedai**
dengan mengunggah gambar QRIS statis (dibaca di browser; yang disimpan hanya isi kodenya).

- Tidak ada notifikasi dari bank/e-wallet, jadi kasir menekan **Sudah diterima** setelah dana terlihat di aplikasi
  merchant kedai (bukan dari layar HP pelanggan). Setiap konfirmasi tercatat di audit log beserta nama kasir.
- Stok dan voucher dipesan saat QR ditampilkan dan dikembalikan jika dibatalkan, sama seperti alur DOKU.
- Masuk ringkasan shift sebagai penjualan non-tunai (tidak masuk laci).
- Uji dulu dengan beberapa aplikasi (GoPay, m-banking) sebelum dipakai melayani pelanggan.

## Pengembangan

```bash
npm run dev            # Vite + hot reload (jalankan bersama php artisan serve)
php artisan test       # seluruh test (SQLite in-memory)
./vendor/bin/pint      # format kode
```

## Keputusan desain yang perlu diketahui

- **Stok tidak bisa diedit langsung.** Setiap perubahan (stok awal, pembelian, stok keluar, penyesuaian)
  tercatat di `stock_mutations` dengan stok sebelum/sesudah, harga, user, dan alasan.
- **Harga modal = rata-rata tertimbang.** Saat pembelian: `(stok × harga lama + jumlah beli × harga beli) / (stok + jumlah beli)`.
  Harga yang benar-benar dibayar tetap tersimpan di detail pembelian dan mutasi.
- **Pembelian tidak bisa diedit/dihapus** karena stoknya sudah masuk ke ledger. Salah input dikoreksi
  lewat penyesuaian stok (tercatat dengan alasan).
- **Stok tidak bisa minus.** Pengurangan stok memakai `lockForUpdate()` di dalam transaksi database;
  sudah diuji dengan 5 proses bersamaan di MySQL/MariaDB.
- **Satuan bahan terkunci setelah dibuat**, karena takaran resep bergantung pada satuan itu.
- **HPP dihitung langsung** dari resep × harga modal bahan saat ini (tidak disimpan sebagai cache
  yang bisa basi). Snapshot HPP disimpan per transaksi saat modul POS dibuat (Tahap 4).
- **Produk dan bahan di-soft-delete** agar riwayat transaksi dan mutasi stok tetap utuh.
  Kategori yang pernah punya produk tidak bisa dihapus; nonaktifkan saja.
- **Font di-host sendiri** (`@fontsource-variable/plus-jakarta-sans`) sehingga tampilan tetap benar
  walau internet kedai mati.
- **Checkout dihitung ulang di server.** Browser hanya mengirim id produk, jumlah, catatan, dan kode voucher.
  Harga, diskon, pajak, service charge, dan total dihitung `CheckoutService`; harga & HPP disimpan sebagai
  snapshot di `order_items`.
- **Rumus total:** service = (subtotal − diskon) × service%; pajak = (subtotal − diskon + service) × pajak%;
  pembulatan tunai ke kelipatan terdekat (pengaturan toko).
- **Anti double-submit:** setiap checkout membawa `client_token` (UUID, unik di database). Klik ganda atau
  kirim ulang mengembalikan pesanan yang sama; sudah diuji 4 request bersamaan → 1 pesanan.
- **Satu shift aktif per kasir** dijamin oleh index unik `shifts.open_user_id`.
- Draft (tahan pesanan) tidak mengurangi stok dan tidak memakai kuota voucher.
- Nomor transaksi `TN-YYMMDD-NNNNN` diambil dari id, jadi bisa melompat jika ada checkout yang ditolak.
- **Tutup shift:** uang seharusnya = modal awal + penjualan tunai + kas masuk − kas keluar; selisih = uang fisik −
  uang seharusnya. Selisih selain nol wajib diberi catatan. Kas keluar tidak boleh melebihi isi laci (dikunci
  per shift, diuji 2 proses bersamaan). Shift ditolak ditutup jika masih ada transaksi menunggu pembayaran.
- **Laporan laba kotor:** pendapatan = subtotal − diskon + service charge (pajak bukan pendapatan); laba kotor =
  pendapatan − HPP snapshot transaksi; nilai bahan terbuang ditampilkan terpisah.
- **Laporan bahan baku** dihitung dari mutasi stok: stok awal = jumlah mutasi sebelum periode, sehingga stok akhir
  selalu cocok dengan stok di sistem.
- Laporan web, PDF, dan Excel dibangun dari satu sumber data (`ReportService`), jadi angkanya selalu sama.
  Excel menyimpan angka sebagai angka (bukan teks) agar bisa dihitung lanjut.
- **Void transaksi** hanya untuk transaksi lunas yang shift-nya masih terbuka, wajib alasan dan **PIN owner**
  (juga saat owner sendiri yang login). Efeknya: status `void`, stok bahan dikembalikan persis sebanyak yang dipotong
  (mutasi "Pengembalian (void)"), kuota voucher dikembalikan, dan transaksi keluar dari penjualan/laporan/kas shift.
  Uang dikembalikan ke pelanggan secara manual. Sudah diuji 4 void bersamaan → 1 berhasil, stok kembali sekali.
- **PIN owner** 6 angka disimpan sebagai hash. Salah 5 kali → dikunci 5 menit per pengguna yang mencoba, dan setiap
  PIN salah tercatat di audit log. Mengganti PIN wajib memasukkan password owner; PIN yang mudah ditebak (123456, 111111, …) ditolak.
- **Akun kasir tidak dihapus**, hanya dinonaktifkan (riwayat tetap utuh). Akun nonaktif langsung keluar dari semua
  perangkat. Kasir yang shift-nya masih terbuka tidak bisa dinonaktifkan. Reset password juga mengeluarkan kasir dari semua perangkat.
- **Audit log hanya bisa dibaca**, tidak ada fitur ubah/hapus.
- Banner promo tampil di layar kasir hanya jika aktif dan berada dalam periode tayang.
- Login memakai **username** (bukan email), dibatasi 5 percobaan gagal per username+IP per menit.
