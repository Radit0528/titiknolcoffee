<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Concerns\ParsesDateFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\PurchaseRequest;
use App\Models\Ingredient;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    use ParsesDateFilters;

    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $from = $this->dateFilter($request->query('from'));
        $to = $this->dateFilter($request->query('to'));
        $supplierId = $request->integer('supplier') ?: null;

        $query = Purchase::query()
            ->when($from, fn ($q) => $q->whereDate('purchased_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('purchased_at', '<=', $to))
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId));

        return view('owner.purchases.index', [
            'purchases' => (clone $query)->with(['supplier', 'user'])->withCount('items')
                ->latest('purchased_at')->latest('id')
                ->paginate(15)->withQueryString(),
            'periodTotal' => (float) (clone $query)->sum('total'),
            'suppliers' => Supplier::withTrashed()->orderBy('name')->get(),
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
            'supplierId' => $supplierId,
        ]);
    }

    public function create(Request $request): View
    {
        $ingredients = Ingredient::orderBy('name')->get();

        return view('owner.purchases.create', [
            'suppliers' => Supplier::orderBy('name')->get(),
            'ingredientOptions' => $ingredients->map(fn (Ingredient $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'unit' => $i->unit->label(),
                'supplier_id' => $i->supplier_id,
            ])->values(),
            // "Beli lagi" from the ingredient list pre-fills that ingredient.
            'initialItems' => old('items', $request->integer('ingredient')
                ? [['ingredient_id' => $request->integer('ingredient'), 'quantity' => '', 'subtotal' => '']]
                : []),
        ]);
    }

    public function store(PurchaseRequest $request): RedirectResponse
    {
        $purchase = $this->inventory->recordPurchase($request->purchaseHeader(), $request->purchaseItems());

        return redirect()->route('owner.purchases.show', $purchase)
            ->with('success', "Pembelian {$purchase->code} tersimpan dan stok bertambah.");
    }

    public function show(Purchase $purchase): View
    {
        $purchase->load(['items.ingredient', 'supplier', 'user']);

        return view('owner.purchases.show', compact('purchase'));
    }
}
