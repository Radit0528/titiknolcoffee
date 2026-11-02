<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\RecipeRequest;
use App\Models\Ingredient;
use App\Models\Product;
use App\Services\RecipeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecipeController extends Controller
{
    public function __construct(private RecipeService $recipes) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $products = Product::query()
            ->with(['category', 'recipe.items.ingredient'])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('owner.recipes.index', [
            'products' => $products,
            'costing' => $products->getCollection()->mapWithKeys(fn (Product $p) => [$p->id => $this->recipes->costing($p)]),
            'search' => $search,
        ]);
    }

    public function edit(Product $product): View
    {
        $product->load('recipe.items.ingredient', 'category');

        $ingredients = Ingredient::orderBy('name')->get();

        return view('owner.recipes.edit', [
            'product' => $product,
            'costing' => $this->recipes->costing($product),
            // Plain data for the Alpine recipe editor (live HPP preview).
            'ingredientOptions' => $ingredients->map(fn (Ingredient $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'unit' => $i->unit->label(),
                'cost' => (float) $i->cost_per_unit,
            ])->values(),
            'initialItems' => old('items', $product->recipe?->items->map(fn ($item) => [
                'ingredient_id' => $item->ingredient_id,
                'quantity' => (float) $item->quantity,
            ])->values()->all() ?? []),
        ]);
    }

    public function update(RecipeRequest $request, Product $product): RedirectResponse
    {
        $this->recipes->sync($product, $request->recipeItems(), $request->validated('notes'));

        return redirect()->route('owner.recipes.edit', $product)->with('success', 'Resep berhasil disimpan.');
    }
}
