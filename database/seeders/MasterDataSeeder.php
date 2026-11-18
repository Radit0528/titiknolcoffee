<?php

namespace Database\Seeders;

use App\Enums\IngredientUnit;
use App\Models\Category;
use App\Models\Product;
use App\Models\Voucher;
use App\Services\InventoryService;
use App\Services\RecipeService;
use Illuminate\Database\Seeder;

/**
 * Sample coffee-shop data for local development and demos.
 * Uses the same services as the app, so stock gets proper mutations.
 */
class MasterDataSeeder extends Seeder
{
    public function run(InventoryService $inventory, RecipeService $recipes): void
    {
        if (Product::exists()) {
            return; // Never duplicate demo data on a database that already has menu items.
        }

        $kopi = Category::create(['name' => 'Kopi', 'sort_order' => 1]);
        $nonKopi = Category::create(['name' => 'Non-Kopi', 'sort_order' => 2]);

        // [name, unit, opening stock, minimum, cost per unit]
        $rows = [
            'beans' => ['Biji Kopi Blend', IngredientUnit::Gram, 3000, 1000, 150],
            'milk' => ['Susu Segar', IngredientUnit::Ml, 8000, 3000, 18],
            'aren' => ['Gula Aren Cair', IngredientUnit::Ml, 2000, 500, 25],
            'ice' => ['Es Batu', IngredientUnit::Gram, 10000, 3000, 0.5],
            'matcha' => ['Bubuk Matcha', IngredientUnit::Gram, 400, 200, 400],
            'choco' => ['Bubuk Cokelat', IngredientUnit::Gram, 150, 200, 100],
            'cup' => ['Cup Plastik 16oz', IngredientUnit::Pcs, 300, 100, 700],
        ];

        $i = [];
        foreach ($rows as $key => [$name, $unit, $stock, $min, $cost]) {
            $i[$key] = $inventory->createIngredient([
                'name' => $name,
                'unit' => $unit,
                'min_stock' => $min,
                'cost_per_unit' => $cost,
            ], $stock);
        }

        // Items from the real menu that get a sample recipe; TitikNolMenuSeeder
        // adds the rest of the menu, the photos and the final prices.
        $menu = [
            [$kopi, 'Kopi Gula Aren', 9000, ['beans' => 12, 'milk' => 100, 'aren' => 20, 'ice' => 100, 'cup' => 1]],
            [$kopi, 'Americano', 5000, ['beans' => 12, 'ice' => 120, 'cup' => 1]],
            [$kopi, 'Kopi Latte', 9000, ['beans' => 12, 'milk' => 150, 'ice' => 100, 'cup' => 1]],
            [$nonKopi, 'Matcha Latte', 10000, ['matcha' => 5, 'milk' => 150, 'ice' => 100, 'cup' => 1]],
            [$nonKopi, 'Choco Latte', 10000, ['choco' => 20, 'milk' => 150, 'ice' => 100, 'cup' => 1]],
        ];

        foreach ($menu as [$category, $name, $price, $recipe]) {
            $product = Product::create(['category_id' => $category->id, 'name' => $name, 'price' => $price]);

            $recipes->sync($product, collect($recipe)
                ->map(fn ($qty, $key) => ['ingredient_id' => $i[$key]->id, 'quantity' => $qty])
                ->values()
                ->all());
        }

        Voucher::create([
            'code' => 'NGOPIPAGI',
            'name' => 'Diskon ngopi pagi',
            'type' => 'percent',
            'value' => 10,
            'max_discount' => 5000,
            'min_purchase' => 20000,
        ]);
    }
}
