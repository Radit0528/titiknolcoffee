<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Video promo untuk halaman pelanggan, dikelola terpisah dari produk.
 * Video yang sudah terlanjur diunggah ke produk dipindahkan ke tabel ini,
 * lalu kolom products.video_path dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_videos', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->string('caption', 255)->nullable();
            $table->string('video_path');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        if (Schema::hasColumn('products', 'video_path')) {
            $order = 0;
            DB::table('products')->whereNotNull('video_path')->orderBy('id')->get(['name', 'video_path'])
                ->each(function ($product) use (&$order) {
                    DB::table('menu_videos')->insert([
                        'title' => mb_substr($product->name, 0, 100),
                        'video_path' => $product->video_path,
                        'is_active' => true,
                        'sort_order' => $order++,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });

            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('video_path');
            });
        }
    }

    /**
     * Restores the structure only; videos stay in their files but are no
     * longer linked to a product.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('video_path')->nullable()->after('image_path');
        });

        Schema::dropIfExists('menu_videos');
    }
};
