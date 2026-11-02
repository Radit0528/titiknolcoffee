<?php

namespace Tests\Feature\Owner;

use App\Enums\StockMutationType;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\RecipeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngredientManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_ingredient_and_opening_stock_is_recorded_as_mutation(): void
    {
        $owner = $this->owner();
        $supplier = Supplier::factory()->create();

        $this->actingAs($owner)->post('/owner/ingredients', [
            'name' => 'Susu Segar',
            'unit' => 'ml',
            'supplier_id' => $supplier->id,
            'min_stock' => 3000,
            'cost_per_unit' => 20,
            'initial_stock' => 8000,
        ])->assertRedirect(route('owner.ingredients.index'));

        $ingredient = Ingredient::where('name', 'Susu Segar')->firstOrFail();
        $this->assertSame('8000.000', $ingredient->stock);

        $mutation = $ingredient->mutations()->sole();
        $this->assertSame(StockMutationType::Initial, $mutation->type);
        $this->assertSame('8000.000', $mutation->quantity);
        $this->assertSame('0.000', $mutation->stock_before);
        $this->assertSame('8000.000', $mutation->stock_after);
        $this->assertSame($owner->id, $mutation->user_id);
    }

    public function test_zero_opening_stock_creates_no_mutation(): void
    {
        $this->actingAs($this->owner())->post('/owner/ingredients', [
            'name' => 'Croissant', 'unit' => 'pcs', 'min_stock' => 10, 'cost_per_unit' => 7000, 'initial_stock' => 0,
        ]);

        $this->assertDatabaseCount('stock_mutations', 0);
    }

    public function test_update_cannot_change_stock_or_unit(): void
    {
        $ingredient = Ingredient::factory()->create(['stock' => 1000, 'unit' => 'ml']);

        $this->actingAs($this->owner())->put("/owner/ingredients/{$ingredient->id}", [
            'name' => $ingredient->name,
            'unit' => 'gram',
            'min_stock' => 100,
            'cost_per_unit' => 20,
        ])->assertSessionHasErrors('unit');

        $this->actingAs($this->owner())->put("/owner/ingredients/{$ingredient->id}", [
            'name' => $ingredient->name,
            'min_stock' => 100,
            'cost_per_unit' => 20,
            'stock' => 999999,
            'initial_stock' => 999999,
        ])->assertSessionHasErrors('initial_stock');

        $this->actingAs($this->owner())->put("/owner/ingredients/{$ingredient->id}", [
            'name' => $ingredient->name,
            'min_stock' => 100,
            'cost_per_unit' => 20,
            'stock' => 999999,
        ])->assertRedirect(route('owner.ingredients.index'));

        $this->assertSame('1000.000', $ingredient->fresh()->stock);
    }

    public function test_cost_change_is_audited(): void
    {
        $ingredient = Ingredient::factory()->create(['cost_per_unit' => 20]);

        $this->actingAs($this->owner())->put("/owner/ingredients/{$ingredient->id}", [
            'name' => $ingredient->name, 'min_stock' => 100, 'cost_per_unit' => 22.5,
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'ingredient.cost_changed', 'auditable_id' => $ingredient->id]);
    }

    public function test_stock_status_follows_minimum(): void
    {
        $this->assertSame('Aman', Ingredient::factory()->make(['stock' => 500, 'min_stock' => 200])->stockStatus()->label());
        $this->assertSame('Menipis', Ingredient::factory()->make(['stock' => 200, 'min_stock' => 200])->stockStatus()->label());
        $this->assertSame('Habis', Ingredient::factory()->make(['stock' => 0, 'min_stock' => 200])->stockStatus()->label());
    }

    public function test_status_filter_lists_only_matching_ingredients(): void
    {
        Ingredient::factory()->create(['name' => 'Bahan Aman', 'stock' => 900, 'min_stock' => 100]);
        Ingredient::factory()->create(['name' => 'Bahan Tipis', 'stock' => 50, 'min_stock' => 100]);
        Ingredient::factory()->create(['name' => 'Bahan Kosong', 'stock' => 0, 'min_stock' => 100]);

        $this->actingAs($this->owner())->get('/owner/ingredients?status=low')
            ->assertOk()->assertSee('Bahan Tipis')->assertDontSee('Bahan Aman')->assertDontSee('Bahan Kosong');

        $this->actingAs($this->owner())->get('/owner/ingredients?status=empty')
            ->assertOk()->assertSee('Bahan Kosong')->assertDontSee('Bahan Tipis');
    }

    public function test_ingredient_used_in_recipe_cannot_be_deleted(): void
    {
        $ingredient = Ingredient::factory()->create();
        app(RecipeService::class)->sync(Product::factory()->create(), [['ingredient_id' => $ingredient->id, 'quantity' => 10]]);

        $this->actingAs($this->owner())->delete("/owner/ingredients/{$ingredient->id}")->assertSessionHas('error');

        $this->assertNotSoftDeleted($ingredient);
    }

    public function test_unused_ingredient_is_soft_deleted(): void
    {
        $ingredient = Ingredient::factory()->create();

        $this->actingAs($this->owner())->delete("/owner/ingredients/{$ingredient->id}");

        $this->assertSoftDeleted($ingredient);
    }

    public function test_edit_page_shows_mutation_history(): void
    {
        $this->actingAs($this->owner())->post('/owner/ingredients', [
            'name' => 'Gula Aren', 'unit' => 'ml', 'min_stock' => 500, 'cost_per_unit' => 30, 'initial_stock' => 2000,
        ]);
        $ingredient = Ingredient::where('name', 'Gula Aren')->firstOrFail();

        $this->actingAs($this->owner())->get("/owner/ingredients/{$ingredient->id}/edit")
            ->assertOk()->assertSee('Stok awal')->assertSee('+2.000');
    }
}
