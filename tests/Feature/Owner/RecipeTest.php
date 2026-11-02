<?php

namespace Tests\Feature\Owner;

use App\Models\Ingredient;
use App\Models\Product;
use App\Services\RecipeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Kopi Susu Aren from the spec:
     * biji kopi 18 g × Rp250 + susu 120 ml × Rp20 + gula aren 20 ml × Rp30 + es 100 g × Rp1
     * = 4.500 + 2.400 + 600 + 100 = Rp7.600
     *
     * @return array{Product, array<string, Ingredient>}
     */
    private function kopiSusuAren(): array
    {
        $product = Product::factory()->create(['name' => 'Kopi Susu Aren', 'price' => 22000]);
        $ingredients = [
            'beans' => Ingredient::factory()->create(['name' => 'Biji Kopi', 'unit' => 'gram', 'cost_per_unit' => 250]),
            'milk' => Ingredient::factory()->create(['name' => 'Susu', 'unit' => 'ml', 'cost_per_unit' => 20]),
            'aren' => Ingredient::factory()->create(['name' => 'Gula Aren', 'unit' => 'ml', 'cost_per_unit' => 30]),
            'ice' => Ingredient::factory()->create(['name' => 'Es Batu', 'unit' => 'gram', 'cost_per_unit' => 1]),
        ];

        return [$product, $ingredients];
    }

    public function test_owner_saves_recipe_and_hpp_is_calculated(): void
    {
        [$product, $i] = $this->kopiSusuAren();

        $this->actingAs($this->owner())->put("/owner/recipes/{$product->id}", [
            'items' => [
                ['ingredient_id' => $i['beans']->id, 'quantity' => 18],
                ['ingredient_id' => $i['milk']->id, 'quantity' => 120],
                ['ingredient_id' => $i['aren']->id, 'quantity' => 20],
                ['ingredient_id' => $i['ice']->id, 'quantity' => 100],
            ],
        ])->assertRedirect(route('owner.recipes.edit', $product));

        $costing = app(RecipeService::class)->costing($product->fresh()->load('recipe.items.ingredient'));

        $this->assertSame(7600.0, $costing['hpp']);
        $this->assertSame(14400.0, $costing['margin']);
        $this->assertSame(65.5, $costing['margin_percent']);
        $this->assertTrue($costing['has_recipe']);
    }

    public function test_hpp_handles_fractional_unit_cost(): void
    {
        $product = Product::factory()->create(['price' => 10000]);
        $water = Ingredient::factory()->create(['cost_per_unit' => 0.25]);

        $recipe = app(RecipeService::class)->sync($product, [['ingredient_id' => $water->id, 'quantity' => 250]]);

        $this->assertSame(62.5, app(RecipeService::class)->hpp($product->setRelation('recipe', $recipe)));
    }

    public function test_hpp_follows_ingredient_cost_changes(): void
    {
        [$product, $i] = $this->kopiSusuAren();
        $service = app(RecipeService::class);
        $service->sync($product, [['ingredient_id' => $i['milk']->id, 'quantity' => 120]]);

        $i['milk']->update(['cost_per_unit' => 25]);

        $this->assertSame(3000.0, $service->hpp($product->fresh()->load('recipe.items.ingredient')));
    }

    public function test_saving_replaces_previous_lines_and_logs_change(): void
    {
        [$product, $i] = $this->kopiSusuAren();
        $owner = $this->owner();

        $this->actingAs($owner)->put("/owner/recipes/{$product->id}", [
            'items' => [['ingredient_id' => $i['beans']->id, 'quantity' => 18], ['ingredient_id' => $i['milk']->id, 'quantity' => 120]],
        ]);
        $this->actingAs($owner)->put("/owner/recipes/{$product->id}", [
            'items' => [['ingredient_id' => $i['beans']->id, 'quantity' => 20]],
        ]);

        $items = $product->fresh()->recipe->items;
        $this->assertCount(1, $items);
        $this->assertSame('20.000', $items->first()->quantity);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_duplicate_ingredient_and_non_positive_quantity_are_rejected(): void
    {
        [$product, $i] = $this->kopiSusuAren();

        $this->actingAs($this->owner())->put("/owner/recipes/{$product->id}", [
            'items' => [
                ['ingredient_id' => $i['milk']->id, 'quantity' => 100],
                ['ingredient_id' => $i['milk']->id, 'quantity' => 0],
            ],
        ])->assertSessionHasErrors(['items.0.ingredient_id', 'items.1.quantity']);

        $this->assertDatabaseCount('recipe_items', 0);
    }

    public function test_deleted_ingredient_cannot_be_added_to_recipe(): void
    {
        $product = Product::factory()->create();
        $gone = Ingredient::factory()->create();
        $gone->delete();

        $this->actingAs($this->owner())->put("/owner/recipes/{$product->id}", [
            'items' => [['ingredient_id' => $gone->id, 'quantity' => 10]],
        ])->assertSessionHasErrors('items.0.ingredient_id');
    }

    public function test_product_without_recipe_has_zero_hpp_and_is_flagged(): void
    {
        $product = Product::factory()->create(['name' => 'Tanpa Resep']);

        $costing = app(RecipeService::class)->costing($product);
        $this->assertSame(0.0, $costing['hpp']);
        $this->assertFalse($costing['has_recipe']);

        $this->actingAs($this->owner())->get('/owner')->assertOk()->assertSee('Tanpa Resep');
    }

    public function test_recipe_editor_page_renders(): void
    {
        [$product] = $this->kopiSusuAren();

        $this->actingAs($this->owner())->get("/owner/recipes/{$product->id}")
            ->assertOk()->assertSee('Perhitungan HPP')->assertSee('Biji Kopi');
    }
}
