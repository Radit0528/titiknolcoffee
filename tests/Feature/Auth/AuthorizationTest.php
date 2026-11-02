<?php

namespace Tests\Feature\Auth;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public static function ownerPages(): array
    {
        return [
            'dashboard' => ['/owner'],
            'categories' => ['/owner/categories'],
            'products' => ['/owner/products'],
            'ingredients' => ['/owner/ingredients'],
            'suppliers' => ['/owner/suppliers'],
            'recipes' => ['/owner/recipes'],
        ];
    }

    #[DataProvider('ownerPages')]
    public function test_guest_is_redirected_to_login(string $url): void
    {
        $this->get($url)->assertRedirect(route('login'));
    }

    #[DataProvider('ownerPages')]
    public function test_cashier_cannot_open_owner_pages(string $url): void
    {
        $this->actingAs($this->cashier())->get($url)->assertForbidden();
    }

    #[DataProvider('ownerPages')]
    public function test_owner_can_open_owner_pages(string $url): void
    {
        $this->actingAs($this->owner())->get($url)->assertOk();
    }

    public function test_cashier_cannot_change_master_data(): void
    {
        $cashier = $this->cashier();
        $product = Product::factory()->create(['price' => 20000]);

        $this->actingAs($cashier)->post('/owner/categories', ['name' => 'Hack', 'sort_order' => 1])->assertForbidden();
        $this->actingAs($cashier)->put("/owner/products/{$product->id}", ['price' => 1])->assertForbidden();
        $this->actingAs($cashier)->delete("/owner/products/{$product->id}")->assertForbidden();

        $this->assertDatabaseMissing('categories', ['name' => 'Hack']);
        $this->assertSame('20000.00', $product->fresh()->price);
    }

    public function test_owner_cannot_open_cashier_screen(): void
    {
        $this->actingAs($this->owner())->get('/kasir')->assertForbidden();
    }

    public function test_pos_catalog_shows_only_active_categories(): void
    {
        $visible = Category::factory()->create(['name' => 'Kopi']);
        $hidden = Category::factory()->create(['name' => 'Rahasia', 'is_active' => false]);
        Product::factory()->for($visible)->create(['name' => 'Kopi Susu Aren']);
        Product::factory()->for($hidden)->create(['name' => 'Menu Tersembunyi']);

        $cashier = $this->cashier();
        Shift::factory()->for($cashier)->create();

        $this->actingAs($cashier)->get('/kasir')
            ->assertOk()
            ->assertSee('Kopi Susu Aren')
            ->assertDontSee('Menu Tersembunyi');
    }

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $cashier = $this->cashier();
        $this->actingAs($cashier);

        $cashier->update(['is_active' => false]);

        $this->get('/kasir')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_owner_and_cashier_roles_are_distinct(): void
    {
        $this->assertTrue(User::factory()->owner()->make()->isOwner());
        $this->assertTrue(User::factory()->cashier()->make()->isCashier());
    }
}
