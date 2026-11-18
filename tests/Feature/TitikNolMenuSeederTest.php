<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\RecipeService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TitikNolMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TitikNolMenuSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_seeds_the_full_menu_with_prices_and_photos(): void
    {
        $this->seed(TitikNolMenuSeeder::class);

        $this->assertSame(20, Product::count());
        $this->assertSame(['Kopi', 'Non-Kopi'], Category::ordered()->pluck('name')->all());
        $this->assertSame(13, Category::firstWhere('name', 'Kopi')->products()->count());
        $this->assertSame(7, Category::firstWhere('name', 'Non-Kopi')->products()->count());

        $this->assertSame('12000.00', Product::firstWhere('name', 'Kopi Sea Salt Cream')->price);
        $this->assertSame('5000.00', Product::firstWhere('name', 'Americano')->price);
        $this->assertSame('4000.00', Product::firstWhere('name', 'Es Teh Solo')->price);
        $this->assertSame('Non-Kopi', Product::firstWhere('name', 'Lemontea')->category->name);

        Product::all()->each(function (Product $product) {
            $this->assertNotNull($product->image_path, "{$product->name} has no photo");
            Storage::disk('public')->assertExists($product->image_path);
        });
    }

    public function test_running_again_updates_without_duplicates_or_replacing_owner_photos(): void
    {
        $kopi = Category::factory()->create(['name' => 'Kopi', 'sort_order' => 1]);
        Storage::disk('public')->put('products/foto-owner.jpg', 'x');
        $americano = Product::factory()->for($kopi)->create(['name' => 'Americano', 'price' => 18000, 'image_path' => 'products/foto-owner.jpg']);

        $this->seed(TitikNolMenuSeeder::class);
        $this->seed(TitikNolMenuSeeder::class);

        $this->assertSame(20, Product::count());
        $this->assertSame(1, Category::where('name', 'Kopi')->count());

        $americano->refresh();
        $this->assertSame('5000.00', $americano->price);
        $this->assertSame('products/foto-owner.jpg', $americano->image_path);
    }

    public function test_fresh_install_gets_the_real_menu_with_sample_recipes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(20, Product::count());
        $this->assertFalse(Product::where('name', 'Kopi Susu Aren')->exists());

        // Sample recipes keep a positive margin at the real, low prices.
        foreach (['Kopi Gula Aren', 'Americano', 'Kopi Latte', 'Matcha Latte', 'Choco Latte'] as $name) {
            $product = Product::firstWhere('name', $name);
            $hpp = app(RecipeService::class)->hpp($product);
            $this->assertGreaterThan(0, $hpp, "{$name} has no recipe");
            $this->assertLessThan((float) $product->price, $hpp, "{$name} sells below cost");
        }

        $this->get('/')->assertOk()->assertSee('Kopi Sea Salt Cream')->assertSee('Rp12.000')->assertSee('Es Teh Solo')->assertDontSee('Teh &amp; Segar', false);
    }

    public function test_old_tea_category_is_merged_into_non_kopi(): void
    {
        // State after the first version of this seeder.
        $teh = Category::factory()->create(['name' => 'Teh & Segar', 'sort_order' => 3]);
        Product::factory()->for($teh)->create(['name' => 'Lemontea', 'price' => 5000]);

        $this->seed(TitikNolMenuSeeder::class);

        $this->assertFalse(Category::where('name', 'Teh & Segar')->exists());
        $this->assertSame('Non-Kopi', Product::firstWhere('name', 'Lemontea')->category->name);
    }

    public function test_old_tea_category_stays_when_owner_added_products_to_it(): void
    {
        $teh = Category::factory()->create(['name' => 'Teh & Segar']);
        Product::factory()->for($teh)->create(['name' => 'Teh Tarik Buatan Owner']);

        $this->seed(TitikNolMenuSeeder::class);

        $this->assertTrue(Category::where('name', 'Teh & Segar')->exists());
    }
}
