<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Outlet container: tidak ada dine in. Pesanan lama yang tercatat dine in
 * diubah ke take away supaya tetap bisa dibaca oleh enum OrderType.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')->where('order_type', 'dine_in')->update(['order_type' => 'take_away']);
    }

    public function down(): void
    {
        // Jenis pesanan asli tidak disimpan, jadi tidak bisa dikembalikan.
    }
};
