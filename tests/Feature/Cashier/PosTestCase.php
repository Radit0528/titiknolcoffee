<?php

namespace Tests\Feature\Cashier;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Shift;
use App\Models\User;
use App\Services\RecipeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Shared fixture: Kopi Susu Aren (Rp22.000) = beans 18 g @250 + milk 120 ml @20 + aren 20 ml @30
 * → HPP Rp7.500 per cup.
 */
abstract class PosTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;

    protected Shift $shift;

    protected Product $kopi;

    /** @var array<string, Ingredient> */
    protected array $ing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->cashier()->create();
        $this->shift = Shift::factory()->for($this->cashier)->create();

        $category = Category::factory()->create(['name' => 'Kopi']);
        $this->kopi = Product::factory()->for($category)->create(['name' => 'Kopi Susu Aren', 'price' => 22000]);

        $this->ing = [
            'beans' => Ingredient::factory()->create(['name' => 'Biji Kopi', 'unit' => 'gram', 'stock' => 1000, 'cost_per_unit' => 250]),
            'milk' => Ingredient::factory()->create(['name' => 'Susu', 'unit' => 'ml', 'stock' => 1000, 'cost_per_unit' => 20]),
            'aren' => Ingredient::factory()->create(['name' => 'Gula Aren', 'unit' => 'ml', 'stock' => 1000, 'cost_per_unit' => 30]),
        ];

        app(RecipeService::class)->sync($this->kopi, [
            ['ingredient_id' => $this->ing['beans']->id, 'quantity' => 18],
            ['ingredient_id' => $this->ing['milk']->id, 'quantity' => 120],
            ['ingredient_id' => $this->ing['aren']->id, 'quantity' => 20],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function cart(int $quantity = 2, array $overrides = []): array
    {
        return array_merge([
            'client_token' => (string) Str::uuid(),
            'items' => [['product_id' => $this->kopi->id, 'quantity' => $quantity, 'note' => 'less sugar']],
            'order_type' => 'dine_in',
            'customer_name' => 'Rani',
            'voucher_code' => null,
            'cash_received' => 50000,
        ], $overrides);
    }

    protected function checkout(array $payload)
    {
        return $this->actingAs($this->cashier)->postJson('/kasir/checkout', $payload);
    }
}
