# SIMKOTOL - UI/UX Design System & Layout Blueprint (PRD v2.0 Aligned)

Dokumen ini berisi panduan sistem desain visual dan cetak biru (*blueprint*) antarmuka untuk **SIMKOTOL (Sistem Manajemen Kopi Titik Nol)** berbasis Tailwind CSS yang telah diverifikasi 100% selaras dengan **PRD v2.0** (`PRD_SIMKOTOL_v2.md`) dan **Database Schema** (`DATABASE_SIMKOTOL.md`).

---

## 1. Konfigurasi Design System (Tailwind CSS Tokens)

Gunakan token berikut pada file `tailwind.config.js` aplikasi **Laravel 12** (Livewire 3 + Alpine.js):

```javascript
// tailwind.config.js
module.exports = {
  theme: {
    extend: {
      colors: {
        brand: {
          primary: '#3C2317',   // Deep Coffee Espresso (Navbar/Sidebar/Primary Action Header)
          'primary-alt': '#3D241C', // Hover/Active state Sidebar
          secondary: '#8C6246', // Warm Terracotta / Clay (Active states/Borders)
          accent: '#D4A373',    // Light Caramel (Hanya Latar/Badge - JANGAN dipakai sebagai Teks font!)
          canvas: '#F8F5F0',    // Cream White Canvas (Background Utama)
          card: '#FCFAF7',      // Soft Off-White (Card/Panel Container)
          dark: '#1A0F0A',      // Charcoal Coffee Text
          muted: '#7A685D',     // Warm Gray-Brown Text (WCAG AA ~4.8:1)
        },
        status: {
          success: '#2E7D32',   // Hijau Transaksi Sukses / Stok Aman
          warning: '#D97706',   // Amber Warning Stok Menipis (Latar Badge)
          'warning-text': '#B45309', // Amber Gelap Khusus Teks Tampil (WCAG AA Lolos)
          danger: '#DC2626',    // Merah Void / Stok Habis
        }
      },
      fontFamily: {
        sans: ['Outfit', 'Inter', 'sans-serif'],
      },
      borderRadius: {
        'brand': '14px',        // Standard rounded-xl (12-16px) sesuai PRD v2.0
      },
      boxShadow: {
        'soft': '0 4px 20px -2px rgba(60, 35, 23, 0.05)',
        'card-hover': '0 10px 25px -5px rgba(60, 35, 23, 0.1)',
      }
    },
  },
}
```

---

## 2. Blueprint Layar POS Kasir (Touch-Optimized - S-10)

Layar kasir menggunakan tata letak **Split-Screen Dual Panel (Tablet Landscape / Desktop)**:
* **Panel Kiri (Katalog Menu - 65% Lebar Layar)**: Grid produk responsif dengan pencarian instan (debounce 200ms), tab kategori, dan carousel banner promo (S-17). Target sentuh minimal 44x44 px.
* **Panel Kanan (Cart & Payment - 35% Lebar Layar)**: Ringkasan daftar belanja, catatan per item, input voucher, kalkulasi pajak/service charge/pembulatan, dan tombol aksi pembayaran.

### Visual Structure (Wireframe Concept)

```
+-----------------------------------------------------------------------------------------+
|  [SIMKOTOL POS]  | Search Menu [🔍 ...] | [Semua] [Kopi] [Non-Kopi] [Snack] | Shift: Aktif |
+-------------------------------------------------------------------+---------------------+
| [CAROUSEL BANNER PROMO: Diskon 20% Hari Jumat!                  ] | KERANJANG BELANJA   |
+-------------------------------------------------------------------+ Order #SKT-261007-1 |
|  +------------------+  +------------------+  +------------------+ | ------------------- |
|  | Es Kopi Susu     |  | Americano Hot    |  | Croissant Butter | | 1x Es Kopi Susu     |
|  | Rp 20.000        |  | Rp 18.000        |  | Rp 25.000        | |    Rp 20.000         |
|  | [Stok: Aman]     |  | [Stok: Aman]     |  | [Stok: 3 pcs ⚠️]  | | 1x Croissant Butter |
|  +------------------+  +------------------+  +------------------+ |    Rp 25.000         |
|  +------------------+  +------------------+  +------------------+ | ------------------- |
|  | Matcha Latte     |  | V60 Manual Brew  |  | French Fries     | | Subtotal: Rp 45.000|
|  | Rp 24.000        |  | Rp 22.000        |  | Rp 15.000        | | Voucher:  -Rp 5.000|
|  | [Stok: Aman]     |  | [Stok: Aman]     |  | [Stok: HABIS ❌] | | Pembulatan:-Rp  100|
|  +------------------+  +------------------+  +------------------+ | TOTAL:    Rp 39.900|
|                                                                   | ------------------- |
|                                                                   | [ TUNAI ]  [ QRIS ] |
|                                                                   | [ PROSES BAYAR -> ] |
+-------------------------------------------------------------------+---------------------+
```

---

## 3. Blueprint Layar Dashboard Owner (S-02)

Layar dashboard owner difokuskan pada **High-Density Information**:
1. **Header Topbar**: Profil Owner, Lencana Notifikasi Stok Kritis dengan jumlah item (`S-22`).
2. **Top Row (KPI Cards)**: 4 KPI Utama (Penjualan Hari Ini, Total Transaksi, Laba Kotor, Bahan Baku Kritis).
3. **Main Content**: Chart Tren Penjualan Harian/Mingguan/Bulanan (Chart.js) + Top 5 Produk Terlaris + Tabel Quick-Stock Mutasi Bahan.

---

## 4. Pemetaan Layar UI (S-01 s/d S-22 Sesuai PRD v2.0)

| Kode Layar | Nama Layar | Role | Utama UI Component |
| :--- | :--- | :--- | :--- |
| **S-01** | Login Screen | Semua | Form Email & Password, Rate limit lockout notification |
| **S-02** | Dashboard Owner | Owner | KPI Cards, Chart.js, Top 5 Products, Stock Alerts |
| **S-03** | Form Kategori | Owner | Data Table + Modal CRUD Kategori |
| **S-04** | Form Menu & HPP | Owner | Grid Menu, Upload Image Resize 600x600, Kalkulator Margin HPP |
| **S-05** | Form Bahan Baku | Owner | Data Table Stok Bahan, Satuan (gram/ml/pcs), Min Stock Alert |
| **S-06** | Form Stok Masuk | Owner | Form Pembelian Bahan + Rekalkulasi Moving Average Cost |
| **S-07** | Form Stok Keluar | Owner | Form Manual Spill/Expired/Adjustment + Reason Text |
| **S-08** | Manajemen Resep (BOM) | Owner | Interactive Composition Builder (Product -> Ingredients) |
| **S-09** | Buka Shift | Kasir | Modal Form Input Modal Awal Kas (*Float Cash*) |
| **S-10** | POS Main Screen | Kasir | Split-Screen Catalog + Cart Panel |
| **S-11** | Modal Pembayaran | Kasir | Modal Quick Cash (Tombol 50k, 100k) & Dynamic QRIS Display |
| **S-12** | Screen Struk & Action | Kasir | Preview Struk Thermal (58mm/80mm) + Direct WA Link |
| **S-13** | Riwayat Transaksi | Kasir/Owner| List Pesanan Shift Aktif / Filter Date Range + Print Duplicate |
| **S-14** | Detail Order & Void Modal | Kasir/Owner| Detail Order + Modal Approval PIN Owner 6-digit & Stock Action |
| **S-15** | Tutup Shift | Kasir | Modal Count Cash, Automatic Variance Preview, Print Summary |
| **S-16** | Voucher Diskon | Owner | CRUD Voucher (Fix/Percent, Quota, Expiry, Min Purchase) |
| **S-17** | Banner Promo | Owner | Upload Banner Aspect Ratio 16:5 Carousel |
| **S-18** | Laporan & Export | Owner | Date Range Selector, Table Analytics, Export PDF/Excel Buttons |
| **S-19** | Pengguna & Hak Akses | Owner | CRUD Kasir, Reset Password, Hash PIN Code Approval |
| **S-20** | Pengaturan Toko | Owner | Settings Store Name, Tax %, Service Charge %, Struk Footer |
| **S-21** | Audit Log | Owner | Log Table (Sensitif actions: Price change, Void, Stock Manual) |
| **S-22** | Notifikasi Stok | Owner | Red Badge Navbar Drawer & List Bahan Menipis/Habis |

---

## 5. Panduan Komponen Micro-Interactions & Aksesibilitas (a11y)

* **Aksesibilitas Kontras (WCAG AA)**: Teks warning wajib menggunakan warna `#B45309` (`status.warning-text`). Badge latar amber `#D97706` tidak boleh digunakan langsung sebagai warna font teks.
* **Hover State Card**: `transition-all duration-200 hover:-translate-y-1 hover:shadow-card-hover`
* **Active Category Badge**: Background `#3C2317` dengan teks putih `#FFFFFF`.
* **Pop-Up QRIS Dynamic**: Modal centered dengan ring countdown timer visual (15:00 min) dan spinner polling status real-time.
