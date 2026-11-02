<?php

namespace App\Http\Controllers\Owner;

use App\Enums\StockMutationType;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Concerns\ParsesDateFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\StockAdjustmentRequest;
use App\Http\Requests\Owner\StockOutRequest;
use App\Models\Ingredient;
use App\Models\StockMutation;
use App\Services\InventoryService;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Stok keluar, penyesuaian stok (stock opname), and the mutation ledger.
 */
class StockMovementController extends Controller
{
    use ParsesDateFilters;

    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $type = StockMutationType::tryFrom((string) $request->query('type'));
        $ingredientId = $request->integer('ingredient') ?: null;
        $from = $this->dateFilter($request->query('from'));
        $to = $this->dateFilter($request->query('to'));

        $mutations = StockMutation::query()
            ->with(['ingredient', 'user', 'reference'])
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($ingredientId, fn ($q) => $q->where('ingredient_id', $ingredientId))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<', $to->copy()->addDay()))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('owner.stock.index', [
            'mutations' => $mutations,
            'types' => StockMutationType::cases(),
            'ingredients' => Ingredient::withTrashed()->orderBy('name')->get(['id', 'name']),
            'type' => $type,
            'ingredientId' => $ingredientId,
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
        ]);
    }

    public function createOut(Request $request): View
    {
        $ingredients = Ingredient::orderBy('name')->get();

        return view('owner.stock.out', [
            'ingredients' => $ingredients,
            'options' => $this->options($ingredients),
            'types' => StockMutationType::stockOutTypes(),
            'selected' => old('ingredient_id', $request->integer('ingredient') ?: null),
        ]);
    }

    public function storeOut(StockOutRequest $request): RedirectResponse
    {
        $ingredient = Ingredient::findOrFail($request->integer('ingredient_id'));
        $type = StockMutationType::from($request->validated('type'));

        try {
            $this->inventory->stockOut($ingredient, $type, (float) $request->validated('quantity'), $request->validated('reason'));
        } catch (InsufficientStockException $e) {
            return back()->withInput()->withErrors(['quantity' => $e->getMessage()]);
        }

        return redirect()->route('owner.stock.index')
            ->with('success', "Stok keluar {$ingredient->name} ({$type->label()}) tercatat.");
    }

    public function createAdjustment(Request $request): View
    {
        $ingredients = Ingredient::orderBy('name')->get();

        return view('owner.stock.adjustment', [
            'ingredients' => $ingredients,
            'options' => $this->options($ingredients),
            'selected' => old('ingredient_id', $request->integer('ingredient') ?: null),
        ]);
    }

    public function storeAdjustment(StockAdjustmentRequest $request): RedirectResponse
    {
        $ingredient = Ingredient::findOrFail($request->integer('ingredient_id'));
        $mutation = $this->inventory->adjustTo($ingredient, (float) $request->validated('counted_stock'), $request->validated('reason'));

        if (! $mutation) {
            return redirect()->route('owner.stock.index')
                ->with('success', "Stok {$ingredient->name} sudah sesuai, tidak ada penyesuaian.");
        }

        $sign = $mutation->quantity > 0 ? '+' : '';

        return redirect()->route('owner.stock.index')
            ->with('success', "Stok {$ingredient->name} disesuaikan ({$sign}".Format::qty($mutation->quantity)." {$ingredient->unit->label()}).");
    }

    /**
     * Plain data for the Alpine stock form (current stock preview).
     *
     * @param  Collection<int, Ingredient>  $ingredients
     */
    private function options(Collection $ingredients): Collection
    {
        return $ingredients->map(fn (Ingredient $i) => [
            'id' => $i->id,
            'name' => $i->name,
            'unit' => $i->unit->label(),
            'stock' => (float) $i->stock,
        ])->values();
    }
}
