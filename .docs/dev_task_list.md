# SIMKOTOL - Master Technical Task List (PRD v2.0)

Dokumen ini mencakup **32 User Story** dan **37 Aturan Bisnis** dari `PRD_SIMKOTOL_v2.md` dan `DATABASE_SIMKOTOL.md`, disusun per **Sprint / Section** sesuai Roadmap (Bagian 15 PRD v2.0).

**Progress**: `14 / 45 tasks selesai`

---

## 📌 Standar Wajib Komentar Kode

Setiap method, Service, Livewire Component wajib docblock dengan referensi `BR-xx` / `US-xx`:

```php
/**
 * [BACKEND] Memproses Checkout & Kalkulasi Grand Total (US-15, BR-03 s/d BR-05).
 * Subtotal → Diskon Voucher (BR-09) → Pajak (BR-04) → Service Charge → Pembulatan (BR-05).
 *
 * @param  OrderData  $data
 * @return Order
 */
public function createOrder(OrderData $data): Order
```

```html
{{--
  [FRONTEND] Komponen Panel Keranjang Kasir POS (US-15, S-10).
  Menampilkan item pesanan, catatan per item, kalkulasi diskon + pajak + pembulatan,
  dan tombol trigger modal pembayaran. Berjalan sepenuhnya sisi klien (Alpine.js).
--}}
```

---

## 🚀 SPRINT 0 — Setup Proyek & Fondasi Arsitektur
> **Durasi**: 1 Minggu | **Tujuan**: Finalisasi PRD, setup repo, CI/server, dan koneksi database.

### ⚙️ Backend — Phase B0

- [x] **B0.1 — Inisialisasi Proyek Laravel 12 + Konfigurasi Environment**
  - **Fungsi**: Setup Laravel 13 (PHP 8.4), konfigurasi `.env` (APP, DB, QUEUE, MAIL, Timezone WIB).
  - **Git Commit**: `chore(init): initialize laravel 12 project and configure environment`

- [x] **B0.2 — Database Migrations: Seluruh Skema (27 Tabel + 3 Views)**
  - **Fungsi**: Buat semua migration dari `DATABASE_SIMKOTOL.md`:
    - `users`, `settings`, `number_sequences`
    - `shifts`, `shift_cash_movements`
    - `categories`, `products`, `ingredients`, `suppliers`, `recipes`
    - `purchases`, `purchase_items`
    - `vouchers`, `banners`
    - `orders`, `order_items`, `payments`, `payment_webhook_logs`
    - `order_voids`, `stock_mutations`, `audit_logs`
    - Views: `v_ingredient_stock_status`, `v_product_availability`, `v_daily_sales`
  - **Git Commit**: `feat(backend): run all database migrations and create sql views`

- [x] **B0.3 — Database Seeder: Akun Owner & Data Settings Awal**
  - **Fungsi**: Seeder akun owner (`Hash::make` password & PIN), kategori contoh, settings default (pajak 0%, footer struk).
  - **Git Commit**: `feat(backend): add database seeders for owner account and default settings`

- [x] **B0.4 — Autentikasi & Role Middleware (US-01, BR-38, BR-39)**
  - **Fungsi**: Login/Logout via Laravel Breeze, Middleware `EnsureOwner` & `EnsureKasir`, Rate limit login 5x/menit.
  - **Git Commit**: `feat(backend): implement role-based auth middleware and session guards`

- [x] **B0.5 — Owner PIN Verification & Lockout Service (BR-37)**
  - **Fungsi**: Service verifikasi PIN 6-digit Owner, penguncian otomatis 15 menit jika gagal 5x.
  - **Git Commit**: `feat(backend): add owner pin verification service with 15 min lockout`

### 🎨 Frontend — Phase F0

- [ ] **F0.1 — Tailwind CSS Design Tokens & Font Setup**
  - **Fungsi**: Daftarkan palet *Earthy Coffee* di `tailwind.config.js` (primary, accent, canvas, warning-text `#B45309`), font Outfit/Inter.
  - **Git Commit**: `feat(frontend): setup tailwind coffee palette tokens and typography`

- [ ] **F0.2 — Layout Utama: Sidebar, Topbar & Toast Notification**
  - **Fungsi**: Template Blade induk (`app.blade.php`) dengan sidebar multi-role, topbar profile, dan toast notifikasi global.
  - **Git Commit**: `feat(frontend): build main layout with role-based sidebar and toast component`

- [ ] **F0.3 — Halaman Login (S-01)**
  - **Fungsi**: View form login (email, password, rate-limit lockout message), redirect sesuai role.
  - **Git Commit**: `feat(frontend): create login page with role-based redirect and lockout notice`

---

## 🟢 SPRINT 1 — Autentikasi, Pengguna, Pengaturan, Menu & Kategori
> **Durasi**: 2 Minggu | **Cakupan**: US-01, US-02, US-03, US-04, US-05, US-32

### ⚙️ Backend — Phase B1

- [x] **B1.1 — User Management Service (US-02, BR-38, BR-39)**
  - **Fungsi**: Service CRUD Kasir/Staf — tambah, nonaktifkan (soft-delete), reset password & PIN; cegah hapus user bertransaksi.
  - **Git Commit**: `feat(backend): implement staff user management service`

- [x] **B1.2 — Category Service (US-03)**
  - **Fungsi**: Service CRUD Kategori (nama unik, slug otomatis, urutan, ikon); tolak hapus jika ada menu aktif.
  - **Git Commit**: `feat(backend): add product category crud service`

- [x] **B1.3 — Product Service & Image Resize (US-04, US-05)**
  - **Fungsi**: Service CRUD Produk — upload foto auto-resize 600×600 (maks 2MB), snapshot harga, toggle `is_sold_out`.
  - **Git Commit**: `feat(backend): implement product crud service with image resize and sold-out toggle`

- [x] **B1.4 — Store & Receipt Settings Service (US-32, BR-04, BR-05)**
  - **Fungsi**: Service simpan/baca `settings` (nama toko, pajak %, service charge %, ukuran kertas, masa berlaku QRIS, pembulatan).
  - **Git Commit**: `feat(backend): add store settings service for tax receipt and payment config`

### 🎨 Frontend — Phase F1

- [ ] **F1.1 — Staff User Management View (S-19)**
  - **Fungsi**: Tabel daftar kasir, modal tambah/edit akun, toggle nonaktif, dan reset password.
  - **Git Commit**: `feat(frontend): create staff user management view`

- [ ] **F1.2 — Category & Product Management Views (S-03, S-04)**
  - **Fungsi**: View CRUD Kategori (drag urutan) & Produk (grid card, upload foto, switch status aktif, preview HPP/margin).
  - **Git Commit**: `feat(frontend): build category and product management views with hpp preview`

- [ ] **F1.3 — Store & Receipt Settings View (S-20)**
  - **Fungsi**: View pengaturan nama toko, pajak, service charge, footer struk, dan pilihan ukuran kertas default.
  - **Git Commit**: `feat(frontend): create store and receipt settings page`

---

## 🟡 SPRINT 2 — Bahan Baku, Stok, Resep (BOM) & Notifikasi Stok
> **Durasi**: 2 Minggu | **Cakupan**: US-06, US-07, US-08, US-09, US-10, BR-12 s/d BR-20

### ⚙️ Backend — Phase B2

- [x] **B2.1 — Ingredient Management & Supplier Service (US-06)**
  - **Fungsi**: Service CRUD Bahan Baku (nama, satuan `gram/ml/pcs`, stok awal, stok minimum, harga awal) & Supplier.
  - **Git Commit**: `feat(backend): add ingredient and supplier management service`

- [x] **B2.2 — Stock Purchase & Moving Average Cost Service (US-07, BR-19)**
  - **Fungsi**: Service Stok Masuk Pembelian — tambah stok, buat mutasi `in`, perbarui `cost_per_unit` (moving average).
  - **Git Commit**: `feat(backend): implement stock purchase and moving average cost recalculation`

- [x] **B2.3 — Manual Stock Adjustment Service (US-08)**
  - **Fungsi**: Service stok keluar manual (`spill`, `expired`, `adjustment` +/-) dengan alasan wajib & audit logging.
  - **Git Commit**: `feat(backend): add manual stock adjustment service with audit logging`

- [x] **B2.4 — Recipe (BOM) Builder & HPP Calculator (US-09, BR-18)**
  - **Fungsi**: Service resep BOM (1 bahan hanya sekali per menu, takaran > 0) & kalkulasi otomatis HPP + snapshot ke produk.
  - **Git Commit**: `feat(backend): implement recipe bom service with hpp and margin calculator`

- [x] **B2.5 — Low Stock Alert Service & DB Views (US-10, BR-20)**
  - **Fungsi**: Integrasi SQL View `v_ingredient_stock_status`, query stok menipis (`≤ min_stock`) dan habis (`≤ 0`).
  - **Git Commit**: `feat(backend): add low stock alert service using ingredient stock status view`

### 🎨 Frontend — Phase F2

- [ ] **F2.1 — Ingredient Inventory & Supplier View (S-05)**
  - **Fungsi**: Tabel bahan baku dengan indikator status stok (Aman/Menipis/Habis), modal tambah/edit, CRUD supplier.
  - **Git Commit**: `feat(frontend): build ingredient inventory view with stock status indicator`

- [ ] **F2.2 — Stock In (Purchase) Form & Stock Out Modal (S-06, S-07)**
  - **Fungsi**: Form Stok Masuk (supplier, tanggal, harga) & Modal Stok Keluar (pilih jenis: spill/expired/adjustment, alasan).
  - **Git Commit**: `feat(frontend): create stock in purchase form and stock out adjustment modal`

- [ ] **F2.3 — Interactive Recipe (BOM) Builder View (S-08)**
  - **Fungsi**: Form BOM builder — pilih produk, tambah baris bahan + input takaran (real-time preview HPP total).
  - **Git Commit**: `feat(frontend): build interactive bom recipe builder with real-time hpp preview`

- [ ] **F2.4 — Low Stock Notification Badge & Drawer (S-22)**
  - **Fungsi**: Badge merah di Topbar Navbar (jumlah bahan kritis), slide-out drawer daftar bahan menipis/habis.
  - **Git Commit**: `feat(frontend): add low stock notification badge and alert drawer component`

---

## 🔵 SPRINT 3 — Shift Kasir, POS, Keranjang & Bayar Tunai
> **Durasi**: 2 Minggu | **Cakupan**: US-11, US-12, US-13, US-14, US-15, US-16, US-17, US-19, US-20, BR-01 s/d BR-11, BR-26 s/d BR-31

### ⚙️ Backend — Phase B3

- [ ] **B3.1 — Open Shift Service (US-11, BR-01, BR-02, BR-26)**
  - **Fungsi**: `ShiftService@openShift` — input modal awal, validasi 1 shift open per kasir (via `open_lock` DB constraint).
  - **Git Commit**: `feat(backend): add open shift service with float cash and unique open lock`

- [ ] **B3.2 — Shift Cash Movement Service (US-12, BR-27)**
  - **Fungsi**: Service pencatatan kas masuk / kas keluar harian dalam shift aktif.
  - **Git Commit**: `feat(backend): implement shift cash movement service`

- [ ] **B3.3 — Close Shift & Expected Cash Calculator (US-13, BR-27 s/d BR-31)**
  - **Fungsi**: `ShiftService@closeShift` — hitung Expected Cash, Cash Variance, tolak tutup jika ada order pending.
  - **Git Commit**: `feat(backend): add close shift service with expected cash and variance calculator`

- [ ] **B3.4 — POS Order Creation & Price Snapshot Engine (US-15, US-19, BR-03 s/d BR-07)**
  - **Fungsi**: `CheckoutService@create` — draft pesanan, snapshot harga/HPP per item, kalkulasi pajak/service charge/pembulatan.
  - **Git Commit**: `feat(backend): implement checkout order creation with full price snapshot engine`

- [ ] **B3.5 — Voucher Validation & Quota Engine (US-16, BR-08 s/d BR-11)**
  - **Fungsi**: Service validasi voucher (aktif, periode, kuota, min belanja, max diskon), inkremen/dekremen `used_count`.
  - **Git Commit**: `feat(backend): add voucher validation and quota tracking engine`

- [ ] **B3.6 — Cash Payment Confirmation & Stock Deduction (US-17, BR-12, BR-13, BR-15)**
  - **Fungsi**: Dalam 1 DB transaction: validasi stok (`SELECT ... FOR UPDATE` urut `ingredient_id`), set order `paid`, kurangi stok, buat mutasi `sales`.
  - **Git Commit**: `feat(backend): implement cash payment confirmation with transactional stock deduction`

### 🎨 Frontend — Phase F3

- [ ] **F3.1 — Open Shift Modal (S-09)**
  - **Fungsi**: Modal wajib Buka Shift saat kasir login pertama kali — input modal awal, validasi > 0.
  - **Git Commit**: `feat(frontend): build open shift modal form component`

- [ ] **F3.2 — POS Catalog Grid, Live Search & Promo Banner Carousel (S-10)**
  - **Fungsi**: Grid kartu menu per kategori (tab), pencarian instan (debounce 200ms), badge Ready/Habis, carousel banner promo.
  - **Git Commit**: `feat(frontend): build pos catalog grid with live search category tabs and promo carousel`

- [ ] **F3.3 — Reactive Cart Panel & Item Notes (S-10)**
  - **Fungsi**: Panel keranjang kanan (sticky) — tambah/kurang item, catatan per item, pilih dine-in/take-away. Berjalan sisi klien (Alpine.js).
  - **Git Commit**: `feat(frontend): implement reactive pos cart panel with item notes in alpine js`

- [ ] **F3.4 — Voucher Input & Discount Calculator Component (S-10)**
  - **Fungsi**: Input kode voucher dengan preview diskon real-time dan pesan error spesifik per kondisi.
  - **Git Commit**: `feat(frontend): add voucher code input with discount preview component`

- [ ] **F3.5 — Checkout & Cash Payment Modal (S-11)**
  - **Fungsi**: Modal pilih metode bayar, modal tunai (tombol pas/50k/100k, input nominal, preview kembalian otomatis).
  - **Git Commit**: `feat(frontend): build checkout and cash payment modal with change calculator`

- [ ] **F3.6 — Shift Cash Movement Modal**
  - **Fungsi**: Modal kasir input kas masuk / kas keluar shift (jumlah, keterangan wajib).
  - **Git Commit**: `feat(frontend): add shift cash movement modal component`

- [ ] **F3.7 — Close Shift & Variance Summary Modal (S-15)**
  - **Fungsi**: Modal Tutup Shift — ringkasan transaksi shift, input uang fisik, preview Cash Variance, cetak ringkasan.
  - **Git Commit**: `feat(frontend): build close shift modal with cash variance preview and print summary`

- [ ] **F3.8 — Draft Order (Hold) View (US-19)**
  - **Fungsi**: Tombol tahan pesanan (save draft), daftar draft aktif, lanjutkan draft ke POS.
  - **Git Commit**: `feat(frontend): add draft order hold and resume functionality`

---

## 🟠 SPRINT 4 — QRIS Dynamic, Webhook & Void Transaksi
> **Durasi**: 2 Minggu | **Cakupan**: US-18, US-22, BR-22 s/d BR-37

### ⚙️ Backend — Phase B4

- [ ] **B4.1 — Payment Gateway Service — Dynamic QRIS (US-18, BR-22)**
  - **Fungsi**: `PaymentService@createQris` — request charge Midtrans Core API, simpan `external_id`, set `expires_at` 15 menit.
  - **Git Commit**: `feat(backend): integrate midtrans dynamic qris charge via payment service`

- [ ] **B4.2 — Payment Status Polling Endpoint (US-18)**
  - **Fungsi**: Endpoint `GET /payments/{id}/status` untuk polling dari layar kasir setiap 3–5 detik.
  - **Git Commit**: `feat(backend): add payment status polling api endpoint`

- [ ] **B4.3 — Webhook Receiver & Idempotency Handler (BR-23, BR-24, BR-25)**
  - **Fungsi**: `POST /webhooks/payment/{provider}` — verifikasi HMAC, idempotensi `(provider, event_id)`, log payload, dispatch Job.
  - **Git Commit**: `feat(backend): implement webhook receiver with hmac signature and idempotency guard`

- [ ] **B4.4 — Void Order Service (Rollback & Waste) (US-22, BR-32 s/d BR-36)**
  - **Fungsi**: `VoidService@process` — cek PIN Owner, buat `order_voids`, rollback stok (baca mutasi asli) atau waste, dekremen voucher, adjust shift cash.
  - **Git Commit**: `feat(backend): implement void order service with rollback waste and cash adjustment`

- [ ] **B4.5 — Scheduled Jobs: Expiry & Draft Cleanup (BR-22, BR-29)**
  - **Fungsi**: Laravel Scheduler — tiap menit tandai payment `pending → expired`; tiap hari bersihkan draft dari shift tutup.
  - **Git Commit**: `feat(backend): add scheduled jobs for payment expiry and draft order cleanup`

### 🎨 Frontend — Phase F4

- [ ] **F4.1 — Dynamic QRIS Popup Modal & Countdown Timer (S-11)**
  - **Fungsi**: Modal QR image, countdown `MM:SS`, spinner polling status, tombol *Buat QR Baru* & *Ganti ke Tunai*.
  - **Git Commit**: `feat(frontend): create dynamic qris modal with countdown and auto polling status`

- [ ] **F4.2 — Order Success & Receipt Actions View (S-12)**
  - **Fungsi**: Layar sukses bayar — preview struk (nomor antrean), tombol Cetak 58/80mm, link `wa.me` kirim struk.
  - **Git Commit**: `feat(frontend): build order success screen with thermal print and whatsapp receipt link`

- [ ] **F4.3 — Thermal Receipt Print CSS Stylesheet (58mm & 80mm)**
  - **Fungsi**: CSS `@media print` dengan `@page { size: 58mm }` dan `@page { size: 80mm }` khusus struk thermal.
  - **Git Commit**: `feat(frontend): add thermal receipt print stylesheet for 58mm and 80mm paper`

- [ ] **F4.4 — Transaction History & Void PIN Approval Modal (S-13, S-14)**
  - **Fungsi**: Tabel riwayat transaksi (filter status, re-print "SALINAN"), Modal Void (alasan, rollback/waste, input PIN 6-digit Owner).
  - **Git Commit**: `feat(frontend): build transaction history table and void order pin approval modal`

---

## 🔴 SPRINT 5 — Dashboard Owner, Laporan, Marketing & Audit Log
> **Durasi**: 2 Minggu | **Cakupan**: US-23, US-24, US-25, US-26 s/d US-31, NFR-04, NFR-12

### ⚙️ Backend — Phase B5

- [ ] **B5.1 — Dashboard KPI Data Service (US-26, NFR-04)**
  - **Fungsi**: Service agregasi KPI (omzet, laba kotor, transaksi, stok kritis) + Top 5 produk, cache 60 detik.
  - **Git Commit**: `feat(backend): add dashboard kpi aggregation service with caching`

- [ ] **B5.2 — Sales & Gross Profit Report Service (US-27, US-29)**
  - **Fungsi**: Query Laporan Penjualan (filter rentang, kasir, metode, status) & Laporan Laba Kotor (Pendapatan − HPP − Waste).
  - **Git Commit**: `feat(backend): implement sales and gross profit report service`

- [ ] **B5.3 — Ingredient & Shift Summary Report Service (US-28, US-30)**
  - **Fungsi**: Query Laporan Bahan Baku (stok awal/masuk/keluar/akhir) & Laporan Shift (ringkasan kas per kasir).
  - **Git Commit**: `feat(backend): add ingredient usage and shift summary report service`

- [ ] **B5.4 — PDF & Excel Export Engine (US-27 s/d US-30, NFR-05)**
  - **Fungsi**: Export PDF via DomPDF (A4 portrait/landscape), Excel via Maatwebsite; ekspor > 5000 baris via Queue Job.
  - **Git Commit**: `feat(backend): integrate dompdf and excel export with queue for large reports`

- [ ] **B5.5 — Promo Voucher & Banner Management Service (US-23, US-24)**
  - **Fungsi**: CRUD Voucher (kode unik, kuota, periode, tipe) & Upload Banner (rasio 16:5, maks 2MB).
  - **Git Commit**: `feat(backend): add voucher and banner management service`

- [ ] **B5.6 — System Audit Log Service (US-31, NFR-12)**
  - **Fungsi**: Listener `audit_logs` — catat aksi sensitif (edit harga, stok manual, void, pengaturan, tutup paksa shift).
  - **Git Commit**: `feat(backend): implement system audit log listener service`

### 🎨 Frontend — Phase F5

- [ ] **F5.1 — Owner Dashboard: KPI Cards, Chart & Stock Alert (S-02, S-22)**
  - **Fungsi**: 4 KPI Cards + Chart.js tren 30 hari/12 minggu/12 bulan + Top 5 Produk + Badge & Drawer Stok Kritis.
  - **Git Commit**: `feat(frontend): build owner dashboard with kpi cards chart and stock alert`

- [ ] **F5.2 — Reports View: Penjualan, Stok, Laba & Shift (S-18)**
  - **Fungsi**: Tab multi-laporan + Date Range Picker + filter kasir/metode + Tombol Export PDF/Excel.
  - **Git Commit**: `feat(frontend): create unified report view with date filter and export triggers`

- [ ] **F5.3 — Digital Marketing View: Voucher & Banner (S-16, S-17)**
  - **Fungsi**: Tabel CRUD Voucher (badge status aktif/expired/habis) + Upload/Preview Banner Carousel.
  - **Git Commit**: `feat(frontend): build digital marketing view for vouchers and banners`

- [ ] **F5.4 — Audit Log View (S-21)**
  - **Fungsi**: Tabel Audit Log dengan filter tanggal, pengguna, dan jenis aksi untuk Owner.
  - **Git Commit**: `feat(frontend): create system audit log viewer page for owner`

---

## ✅ SPRINT 6 — Pengujian Menyeluruh, UAT & Deploy Produksi
> **Durasi**: 1–2 Minggu | **Cakupan**: T-01 s/d T-15 (Skenario Kritis PRD)

### ⚙️ Backend — Testing & Bug Fix

- [ ] **T-01 — Feature Test: Penjualan Tunai & Pengurangan Stok BOM**
  - **Skenario**: Jual 1 Es Kopi Susu → Kopi −18g, Susu −100ml, Gula Aren −15ml; mutasi `sales` terhubung ke order.
  - **Git Commit**: `test(backend): add feature test for cash sale and bom stock deduction`

- [ ] **T-02 s/d T-05 — Feature Tests: Race Condition, Webhook & Idempotency**
  - **Skenario**: (T-02) 2 kasir checkout bahan terakhir serentak; (T-03) webhook QRIS 2x → stok 1x; (T-04) signature invalid → HTTP 400; (T-05) webhook terlambat → `needs_review`.
  - **Git Commit**: `test(backend): add concurrency webhook and idempotency feature tests`

- [ ] **T-06 s/d T-15 — Feature Tests: Voucher, Void, Shift & Security**
  - **Skenario**: Voucher invalid, Void PIN gagal 5x (lockout 15 menit), Void rollback vs waste, Tutup shift pending, Akses URL Owner oleh Kasir (403), Cetak struk 58/80mm, Export PDF/Excel, Payment nominal berbeda `needs_review`.
  - **Git Commit**: `test(backend): add all critical scenario feature tests T06 to T15`

### 🎨 Frontend & Deployment

- [ ] **UAT — User Acceptance Testing bersama Owner & Kasir**
  - **Fungsi**: Uji coba paralel pencatatan manual vs sistem minimal 3 hari; dokumentasi bug dan perbaikan.
  - **Git Commit**: `fix: resolve uat reported bugs and ui inconsistencies`

- [ ] **Deploy — Konfigurasi VPS Produksi (NFR-06, NFR-14)**
  - **Fungsi**: Deploy ke VPS Linux (Nginx + PHP-FPM + MySQL 8), aktifkan HTTPS, setup cronjob backup harian `mysqldump --single-transaction`.
  - **Git Commit**: `chore(deploy): configure production vps with https ssl and daily database backup`
