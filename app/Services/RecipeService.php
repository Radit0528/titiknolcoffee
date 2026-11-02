<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Recipe;
use Illuminate\Support\Facades\DB;

class RecipeService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * HPP (cost of goods) of one serving: Σ takaran × harga modal per satuan.
     *
     * Expects `recipe.items.ingredient` to be eager loaded when called in a loop.
     */
    public function hpp(Product $product): float
    {
        $recipe = $product->recipe;

        if (! $recipe) {
            return 0.0;
        }

        return round($recipe->items->sum(fn ($item) => $item->cost()), 2);
    }

    /**
     * @return array{hpp: float, margin: float, margin_percent: float|null, has_recipe: bool}
     */
    public function costing(Product $product): array
    {
        $hpp = $this->hpp($product);
        $price = (float) $product->price;
        $margin = round($price - $hpp, 2);

        return [
            'hpp' => $hpp,
            'margin' => $margin,
            'margin_percent' => $price > 0 ? round($margin / $price * 100, 1) : null,
            'has_recipe' => (bool) $product->recipe?->items->isNotEmpty(),
        ];
    }

    /**
     * Replace a product's recipe lines with the given set.
     *
     * @param  array<int, array{ingredient_id: int, quantity: float|string}>  $items
     */
    public function sync(Product $product, array $items, ?string $notes = null): Recipe
    {
        return DB::transaction(function () use ($product, $items, $notes) {
            $recipe = Recipe::firstOrCreate(['product_id' => $product->id]);
            $before = $recipe->items()->pluck('quantity', 'ingredient_id')->map(fn ($q) => (string) $q)->all();

            $recipe->update(['notes' => $notes]);
            $recipe->items()->delete();

            foreach ($items as $item) {
                $recipe->items()->create([
                    'ingredient_id' => $item['ingredient_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            $after = $recipe->items()->pluck('quantity', 'ingredient_id')->map(fn ($q) => (string) $q)->all();

            if ($before != $after) {
                $this->audit->log('recipe.updated', $product, $before, $after);
            }

            return $recipe->load('items.ingredient');
        });
    }
}
