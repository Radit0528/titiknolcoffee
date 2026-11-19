<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // QRIS Kedai: the QR payload shown to the customer and the cashier who confirmed it.
            $table->text('qr_payload')->nullable()->after('payment_url');
            $table->foreignId('confirmed_by')->nullable()->after('qr_payload')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn('qr_payload');
        });
    }
};
