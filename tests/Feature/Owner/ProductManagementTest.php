<?php

namespace Tests\Feature\Owner;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_owner_creates_product_with_image_and_is_sent_to_recipe(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->owner())->post('/owner/products', [
            'category_id' => $category->id,
            'name' => 'Kopi Susu Aren',
            'price' => 22000,
            'is_available' => '1',
            'image' => UploadedFile::fake()->image('kopi.jpg', 600, 450),
        ]);

        $product = Product::where('name', 'Kopi Susu Aren')->firstOrFail();
        $response->assertRedirect(route('owner.recipes.edit', $product));

        $this->assertSame('22000.00', $product->price);
        $this->assertTrue($product->is_available);
        Storage::disk('public')->assertExists($product->image_path);
    }

    public function test_product_requires_valid_data(): void
    {
        $this->actingAs($this->owner())->post('/owner/products', [
            'category_id' => 999,
            'name' => '',
            'price' => -1,
        ])->assertSessionHasErrors(['category_id', 'name', 'price']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_non_image_upload_is_rejected(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->owner())->post('/owner/products', [
            'category_id' => $category->id,
            'name' => 'Teh',
            'price' => 10000,
            'image' => UploadedFile::fake()->create('script.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('image');
    }

    public function test_oversized_image_is_rejected(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->owner())->post('/owner/products', [
            'category_id' => $category->id,
            'name' => 'Teh',
            'price' => 10000,
            'image' => UploadedFile::fake()->image('besar.jpg')->size(3000),
        ])->assertSessionHasErrors('image');
    }

    public function test_price_change_is_written_to_audit_log(): void
    {
        $owner = $this->owner();
        $product = Product::factory()->create(['price' => 20000]);

        $this->actingAs($owner)->put("/owner/products/{$product->id}", [
            'category_id' => $product->category_id,
            'name' => $product->name,
            'price' => 23000,
            'is_available' => '1',
        ])->assertRedirect(route('owner.products.index'));

        $log = AuditLog::where('action', 'product.price_changed')->sole();
        $this->assertSame($owner->id, $log->user_id);
        $this->assertSame(['price' => '20000.00'], $log->old_values);
        $this->assertSame(['price' => '23000.00'], $log->new_values);
    }

    public function test_updating_without_price_change_does_not_log(): void
    {
        $product = Product::factory()->create(['price' => 20000]);

        $this->actingAs($this->owner())->put("/owner/products/{$product->id}", [
            'category_id' => $product->category_id,
            'name' => 'Nama Baru',
            'price' => '20000',
        ]);

        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame('Nama Baru', $product->fresh()->name);
    }

    public function test_replacing_image_deletes_old_file(): void
    {
        $owner = $this->owner();
        $product = Product::factory()->create(['image_path' => UploadedFile::fake()->image('a.jpg')->store('products', 'public')]);
        $old = $product->image_path;

        $this->actingAs($owner)->put("/owner/products/{$product->id}", [
            'category_id' => $product->category_id,
            'name' => $product->name,
            'price' => $product->price,
            'image' => UploadedFile::fake()->image('b.png'),
        ]);

        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($product->fresh()->image_path);
    }

    public function test_toggle_marks_product_sold_out_and_back(): void
    {
        $product = Product::factory()->create(['is_available' => true]);
        $owner = $this->owner();

        $this->actingAs($owner)->patch("/owner/products/{$product->id}/toggle");
        $this->assertFalse($product->fresh()->is_available);

        $this->actingAs($owner)->patch("/owner/products/{$product->id}/toggle");
        $this->assertTrue($product->fresh()->is_available);
    }

    public function test_delete_is_soft_so_history_survives(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->owner())->delete("/owner/products/{$product->id}")
            ->assertRedirect(route('owner.products.index'));

        $this->assertSoftDeleted($product);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.deleted', 'auditable_id' => $product->id]);
    }

    public function test_product_list_can_be_searched_and_filtered(): void
    {
        $kopi = Category::factory()->create();
        Product::factory()->for($kopi)->create(['name' => 'Kopi Susu Aren']);
        Product::factory()->create(['name' => 'Matcha Latte']);

        $this->actingAs($this->owner())->get('/owner/products?q=aren')
            ->assertOk()->assertSee('Kopi Susu Aren')->assertDontSee('Matcha Latte');

        $this->actingAs($this->owner())->get("/owner/products?category={$kopi->id}")
            ->assertOk()->assertSee('Kopi Susu Aren')->assertDontSee('Matcha Latte');
    }
}
