<?php

namespace Tests\Feature\Public;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\MenuVideo;
use App\Models\Product;
use App\Services\RecipeService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_menu_prices_and_sold_out_state(): void
    {
        $kopi = Category::factory()->create(['name' => 'Kopi']);
        Category::factory()->create(['name' => 'Rahasia', 'is_active' => false])
            ->products()->create(['name' => 'Menu Tersembunyi', 'price' => 10000, 'is_available' => true]);

        Product::factory()->for($kopi)->create(['name' => 'Americano', 'price' => 18000]);
        Product::factory()->for($kopi)->create(['name' => 'Kopi Libur', 'is_available' => false]);
        $latte = Product::factory()->for($kopi)->create(['name' => 'Cafe Latte', 'price' => 25000]);
        $susu = Ingredient::factory()->create(['unit' => 'ml', 'stock' => 50]);
        app(RecipeService::class)->sync($latte, [['ingredient_id' => $susu->id, 'quantity' => 120]]);
        Product::factory()->for($kopi)->create(['name' => 'Menu Dihapus'])->delete();

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Americano')->assertSee('Rp18.000')
            ->assertSee('Cafe Latte')->assertSee('Rp25.000')
            ->assertDontSee('Menu Tersembunyi')
            ->assertDontSee('Menu Dihapus')
            ->assertSee('Masuk staf');
        // Latte: 50 ml stock < 120 ml per cup; Kopi Libur switched off by the owner.
        $response->assertViewHas('soldOut', fn ($ids) => in_array($latte->id, $ids, true) && count($ids) === 2);
        $response->assertDontSee('HPP');
    }

    public function test_only_banners_in_period_are_shown(): void
    {
        Carbon::setTestNow('2026-11-05 10:00');
        Banner::factory()->create(['title' => 'Promo Pagi']);
        Banner::factory()->create(['title' => 'Promo Besok', 'starts_at' => '2026-11-06 00:00']);
        Banner::factory()->create(['title' => 'Promo Mati', 'is_active' => false]);

        $this->get('/')->assertOk()->assertSee('Promo Pagi')->assertDontSee('Promo Besok')->assertDontSee('Promo Mati');
        Carbon::setTestNow();
    }

    public function test_shop_info_comes_from_settings(): void
    {
        app(SettingService::class)->update([
            'store_name' => 'Kopi Titik Nol', 'store_address' => 'Jl. Jenderal Sudirman 1, Purwokerto',
            'store_phone' => '0812-3456-7890', 'store_hours' => 'Setiap hari 07.00–22.00', 'store_maps_url' => 'https://maps.app.goo.gl/abc',
        ]);

        $this->get('/')->assertOk()
            ->assertSee('Jl. Jenderal Sudirman 1, Purwokerto')
            ->assertSee('Setiap hari 07.00–22.00')
            ->assertSee('tel:081234567890', false)
            ->assertSee('https://maps.app.goo.gl/abc', false);
    }

    public function test_logged_in_staff_get_a_link_to_their_screen(): void
    {
        $this->actingAs($this->owner())->get('/')->assertOk()->assertSee('Buka aplikasi')->assertSee(route('owner.dashboard'), false);
        $this->actingAs($this->cashier())->get('/')->assertOk()->assertSee(route('cashier.pos'), false);
    }

    public function test_login_page_still_works_and_links_back(): void
    {
        $this->get('/login')->assertOk()->assertSee('Lihat menu kedai');
    }

    public function test_titik_nol_logo_is_shown(): void
    {
        $this->assertFileExists(public_path('images/logo-titiknol.png'));
        $this->assertFileExists(public_path('images/logo-titiknol-light.png'));

        $this->get('/')->assertOk()->assertSee('images/logo-titiknol.png', false)->assertSee('alt="Titik Nol Coffee"', false);
        // Dark side panel uses the white-text version; the light card the black one.
        $this->get('/login')->assertOk()->assertSee('images/logo-titiknol-light.png', false)->assertSee('images/logo-titiknol.png', false);
        $this->actingAs($this->owner())->get('/owner')->assertOk()->assertSee('images/logo-titiknol-light.png', false);
    }

    public function test_owner_saves_hours_and_maps_link(): void
    {
        $owner = $this->owner();
        $base = ['store_name' => 'Kopi Titik Nol', 'receipt_width' => 58, 'tax_percent' => 0, 'service_charge_percent' => 0, 'cash_rounding' => 0];

        $this->actingAs($owner)->put('/owner/settings', $base + ['store_hours' => 'Senin–Sabtu 08.00–21.00', 'store_maps_url' => 'bukan-link'])
            ->assertSessionHasErrors('store_maps_url');

        $this->actingAs($owner)->put('/owner/settings', $base + ['store_hours' => 'Senin–Sabtu 08.00–21.00', 'store_maps_url' => 'https://maps.app.goo.gl/xyz'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Senin–Sabtu 08.00–21.00', app(SettingService::class)->get('store_hours'));
    }

    public function test_active_videos_autoplay_in_order_without_a_click(): void
    {
        MenuVideo::factory()->create(['title' => 'Video Kedua', 'sort_order' => 2, 'video_path' => 'menu-videos/kedua.mp4']);
        MenuVideo::factory()->create(['title' => 'Video Pertama', 'sort_order' => 1, 'video_path' => 'menu-videos/pertama.mp4']);
        MenuVideo::factory()->create(['title' => 'Video Mati', 'is_active' => false]);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSeeInOrder(['Video Pertama', 'Video Kedua'])
            ->assertDontSee('Video Mati')
            ->assertSee('menu-videos/pertama.mp4', false)
            // Started by the script when on screen: muted + playsinline is what
            // mobile browsers require to play without a tap.
            ->assertSee('muted loop playsinline', false)
            ->assertSee('x-data="videoStrip(', false);
    }

    public function test_videos_open_full_screen_and_advance_on_their_own(): void
    {
        MenuVideo::factory()->create(['title' => 'Video Pertama', 'caption' => 'Andalan kami', 'sort_order' => 1, 'video_path' => 'menu-videos/pertama.mp4']);
        MenuVideo::factory()->create(['title' => 'Video Kedua', 'sort_order' => 2, 'video_path' => 'menu-videos/kedua.mp4']);

        $html = $this->get('/')->assertOk()
            ->assertSee('Putar Video Pertama layar penuh')
            ->assertSee('x-on:click="open(0)"', false)
            ->assertSee('x-on:click="open(1)"', false)
            // The viewer moves on when a video ends.
            ->assertSee('x-on:ended="go(1)"', false)
            ->getContent();

        // The viewer gets the playlist in display order. @js renders
        // JSON.parse('<JSON as a JS string>'), so decode the JS string first.
        preg_match("/x-data=\"videoStrip\\(JSON\\.parse\\('(.*?)'\\)\\)\"/s", $html, $m);
        $items = json_decode(json_decode('"'.$m[1].'"'), true);
        $this->assertSame(['Video Pertama', 'Video Kedua'], array_column($items, 'title'));
        $this->assertSame('Andalan kami', $items[0]['caption']);
        $this->assertStringEndsWith('menu-videos/pertama.mp4', $items[0]['src']);
    }

    public function test_no_video_section_without_videos(): void
    {
        Product::factory()->for(Category::factory()->create())->create();

        $this->get('/')->assertOk()->assertDontSee('video-strip', false)->assertDontSee('<video', false);
    }
}
