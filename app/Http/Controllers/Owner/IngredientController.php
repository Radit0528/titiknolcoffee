<?php

namespace App\Http\Controllers\Owner;

use App\Enums\IngredientUnit;
use App\Enums\StockStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\IngredientRequest;
use App\Models\Ingredient;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IngredientController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = StockStatus::tryFrom((string) $request->query('status'));

        $ingredients = Ingredient::query()
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->when($status === StockStatus::Empty, fn ($q) => $q->where('stock', '<=', 0))
            ->when($status === StockStatus::Low, fn ($q) => $q->where('stock', '>', 0)->whereColumn('stock', '<=', 'min_stock'))
            ->when($status === StockStatus::Safe, fn ($q) => $q->whereColumn('stock', '>', 'min_stock')->where('stock', '>', 0))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('owner.ingredients.index', [
            'ingredients' => $ingredients,
            'search' => $search,
            'status' => $status,
            'lowCount' => Ingredient::lowStock()->count(),
            'inventoryValue' => $this->inventory->inventoryValue(),
        ]);
    }

    public function create(): View
    {
        return view('owner.ingredients.form', [
            'ingredient' => new Ingredient,
            'units' => IngredientUnit::cases(),
        ]);
    }

    public function store(IngredientRequest $request): RedirectResponse
    {
        $this->inventory->createIngredient($request->ingredientData(), (float) $request->validated('initial_stock'));

        return redirect()->route('owner.ingredients.index')->with('success', 'Bahan baku berhasil ditambahkan.');
    }

    public function edit(Ingredient $ingredient): View
    {
        return view('owner.ingredients.form', [
            'ingredient' => $ingredient,
            'units' => IngredientUnit::cases(),
            'mutations' => $ingredient->mutations()->with('user')->latest('id')->limit(10)->get(),
        ]);
    }

    public function update(IngredientRequest $request, Ingredient $ingredient): RedirectResponse
    {
        $this->inventory->updateIngredient($ingredient, $request->ingredientData());

        return redirect()->route('owner.ingredients.index')->with('success', 'Bahan baku berhasil diperbarui.');
    }

    public function destroy(Ingredient $ingredient): RedirectResponse
    {
        if (! $this->inventory->deleteIngredient($ingredient)) {
            return back()->with('error', "{$ingredient->name} masih dipakai di resep. Hapus dari resep terlebih dahulu.");
        }

        return redirect()->route('owner.ingredients.index')->with('success', 'Bahan baku berhasil dihapus.');
    }
}
