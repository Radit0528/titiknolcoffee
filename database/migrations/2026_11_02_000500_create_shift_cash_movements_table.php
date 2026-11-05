<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 6 — kas masuk/keluar selama shift (mis. tambah uang kembalian,
 * beli es batu pakai uang laci). Closing figures live on `shifts`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 5); // in | out
            $table->decimal('amount', 12, 2);
            $table->string('reason', 255);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['shift_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_cash_movements');
    }
};
