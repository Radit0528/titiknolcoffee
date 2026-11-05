<?php

namespace Tests\Feature\Owner;

use App\Models\AuditLog;
use App\Models\Banner;
use App\Models\Ingredient;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BannerAndAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_owner_creates_updates_and_deletes_banner(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post('/owner/banners', [
            'title' => 'Promo Pagi', 'description' => 'Diskon 10%', 'image' => UploadedFile::fake()->image('promo.jpg', 800, 350),
            'is_active' => '1', 'sort_order' => '2',
        ])->assertRedirect('/owner/banners');

        $banner = Banner::firstOrFail();
        Storage::disk('public')->assertExists($banner->image_path);
        $oldPath = $banner->image_path;

        $this->actingAs($owner)->put("/owner/banners/{$banner->id}", [
            'title' => 'Promo Siang', 'image' => UploadedFile::fake()->image('baru.png', 800, 350), 'is_active' => '1',
        ])->assertRedirect('/owner/banners');

        $banner->refresh();
        $this->assertSame('Promo Siang', $banner->title);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($banner->image_path);

        $this->actingAs($owner)->delete("/owner/banners/{$banner->id}")->assertRedirect('/owner/banners');
        $this->assertSame(0, Banner::count());
        Storage::disk('public')->assertMissing($banner->image_path);
        $this->assertSame(['banner.created', 'banner.deleted', 'banner.updated'], AuditLog::orderBy('action')->pluck('action')->all());
    }

    public function test_banner_validation(): void
    {
        $this->actingAs($this->owner())->post('/owner/banners', [
            'title' => '', 'image' => UploadedFile::fake()->create('virus.svg', 10, 'image/svg+xml'),
            'starts_at' => '2026-11-10 10:00', 'ends_at' => '2026-11-09 10:00',
        ])->assertSessionHasErrors(['title', 'image', 'ends_at']);

        $this->actingAs($this->owner())->post('/owner/banners', ['title' => 'Tanpa gambar'])->assertSessionHasErrors('image');
    }

    public function test_pos_shows_only_banners_in_period(): void
    {
        Carbon::setTestNow('2026-11-04 10:00');
        Banner::factory()->create(['title' => 'Tayang Sekarang']);
        Banner::factory()->create(['title' => 'Belum Mulai', 'starts_at' => '2026-11-05 00:00']);
        Banner::factory()->create(['title' => 'Sudah Lewat', 'ends_at' => '2026-11-03 23:59']);
        Banner::factory()->create(['title' => 'Dimatikan', 'is_active' => false]);

        $cashier = $this->cashier();
        Shift::factory()->for($cashier)->create();

        $this->actingAs($cashier)->get('/kasir')->assertOk()
            ->assertSee('Tayang Sekarang')
            ->assertDontSee('Belum Mulai')
            ->assertDontSee('Sudah Lewat')
            ->assertDontSee('Dimatikan');

        $this->actingAs($this->owner())->get('/owner/banners')->assertOk()
            ->assertSee('Terjadwal')->assertSee('Berakhir')->assertSee('Nonaktif')->assertSee('Tayang');
        Carbon::setTestNow();
    }

    public function test_audit_log_page_filters(): void
    {
        $owner = $this->owner();
        $kasir = User::factory()->cashier()->create(['name' => 'Budi Kasir']);

        AuditLog::create(['user_id' => $owner->id, 'action' => 'product.price_changed', 'auditable_type' => 'App\Models\Product', 'auditable_id' => 7,
            'old_values' => ['price' => '18000.00'], 'new_values' => ['price' => '20000.00'], 'created_at' => '2026-11-01 09:00']);
        AuditLog::create(['user_id' => $kasir->id, 'action' => 'shift.closed', 'created_at' => '2026-11-03 21:00']);
        $susu = Ingredient::factory()->create(['name' => 'Susu UHT']);
        AuditLog::create(['user_id' => $owner->id, 'action' => 'recipe.updated', 'old_values' => [$susu->id => '120.000'], 'new_values' => [$susu->id => '150.000'], 'created_at' => '2026-11-01 08:00']);

        $this->actingAs($owner)->get('/owner/audit-log')->assertOk()
            ->assertSee('Harga produk diubah')->assertSee('Produk #7')->assertSee('harga')->assertSee('18.000')->assertSee('20.000')
            ->assertSee('Shift ditutup')
            ->assertSee('Susu UHT')->assertSee('150');

        $this->actingAs($owner)->get('/owner/audit-log?action=shift.closed')->assertOk()
            ->assertDontSee('Produk #7')->assertSee('Budi Kasir');

        $this->actingAs($owner)->get("/owner/audit-log?user={$owner->id}")->assertOk()->assertSee('Produk #7')->assertDontSee('Budi Kasir</td>', false);

        $this->actingAs($owner)->get('/owner/audit-log?from=2026-11-02&to=2026-11-03')->assertOk()
            ->assertDontSee('Produk #7')->assertSee('Shift ditutup');

        $this->actingAs($owner)->get('/owner/audit-log?from=bukan-tanggal')->assertOk();
    }
}
