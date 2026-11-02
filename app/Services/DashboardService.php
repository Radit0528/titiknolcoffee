<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Purchase;

/**
 * Owner dashboard figures. Sales-based figures (omzet, transaksi, laba,
 * menu terlaris, grafik) are added in Tahap 7 once orders exist.
 */
class DashboardService
{
    public function __construct(private InventoryService $inventory) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'inventoryValue' => $this->inventory->inventoryValue(),
            'lowStockCount' => Ingredient::lowStock()->count(),
            'productCount' => Product::count(),
            'availableProductCount' => Product::available()->count(),
            'activeCategoryCount' => Category::active()->count(),
            'purchasesThisMonth' => (float) Purchase::query()
                ->whereBetween('purchased_at', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->sum('total'),
            'productsWithoutRecipe' => Product::query()
                ->whereDoesntHave('recipe.items')
                ->orderBy('name')
                ->limit(5)
                ->get(['id', 'name']),
            'lowStockIngredients' => Ingredient::lowStock()
                ->orderByRaw('stock / NULLIF(min_stock, 0)')
                ->limit(6)
                ->get(),
        ];
    }
}
