<?php

namespace App\Services;

use App\Enums\StockMutationType;
use App\Exceptions\InsufficientStockException;
use App\Models\Ingredient;
use App\Models\Purchase;
use App\Models\StockMutation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Create an ingredient. A non-zero opening stock is written as an
     * "initial" mutation so the stock ledger always explains the balance.
     *
     * @param  array<string, mixed>  $data
     */
    public function createIngredient(array $data, float $initialStock): Ingredient
    {
        return DB::transaction(function () use ($data, $initialStock) {
            $ingredient = Ingredient::create($data);

            if ($initialStock > 0) {
                $this->applyMutation($ingredient, StockMutationType::Initial, $initialStock, 'Stok awal saat bahan dibuat');
            }

            return $ingredient->refresh();
        });
    }

    /**
     * Update descriptive fields and cost. Stock is not editable here.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateIngredient(Ingredient $ingredient, array $data): Ingredient
    {
        $oldCost = (string) $ingredient->cost_per_unit;
        $ingredient->update($data);

        if (round((float) $oldCost, 4) !== round((float) $ingredient->cost_per_unit, 4)) {
            $this->audit->log('ingredient.cost_changed', $ingredient,
                ['cost_per_unit' => $oldCost],
                ['cost_per_unit' => (string) $ingredient->cost_per_unit],
            );
        }

        return $ingredient;
    }

    /**
     * Soft-delete an ingredient unless a product recipe still uses it.
     *
     * @return bool false when the ingredient is still used by a recipe
     */
    public function deleteIngredient(Ingredient $ingredient): bool
    {
        if ($ingredient->recipeItems()->exists()) {
            return false;
        }

        $this->audit->log('ingredient.deleted', $ingredient, ['name' => $ingredient->name, 'stock' => (string) $ingredient->stock]);
        $ingredient->delete();

        return true;
    }

    /**
     * Record a purchase (stok masuk). Totals are computed here, never taken
     * from the browser. Each line raises stock and moves the ingredient's
     * cost to the weighted average of old stock and the newly bought stock:
     *
     *   new cost = (stock × cost + qty × unit price) / (stock + qty)
     *
     * Ingredients are locked in id order so two purchases touching the same
     * ingredients cannot deadlock each other.
     *
     * @param  array{invoice_number?: string|null, purchased_at: string, notes?: string|null}  $header
     * @param  array<int, array{ingredient_id: int, quantity: float|string, subtotal: float|string}>  $items
     */
    public function recordPurchase(array $header, array $items): Purchase
    {
        if ($items === []) {
            throw new InvalidArgumentException('Pembelian harus memiliki minimal satu bahan.');
        }

        usort($items, fn ($a, $b) => $a['ingredient_id'] <=> $b['ingredient_id']);

        return DB::transaction(function () use ($header, $items) {
            $purchase = Purchase::create([
                'invoice_number' => $header['invoice_number'] ?? null,
                'purchased_at' => $header['purchased_at'],
                'notes' => $header['notes'] ?? null,
                'user_id' => auth()->id(),
                'total' => 0,
            ]);

            $total = 0.0;

            foreach ($items as $item) {
                $quantity = round((float) $item['quantity'], 3);
                $subtotal = round((float) $item['subtotal'], 2);
                $unitCost = round($subtotal / $quantity, 4);

                $ingredient = Ingredient::whereKey($item['ingredient_id'])->lockForUpdate()->firstOrFail();
                $oldCost = (float) $ingredient->cost_per_unit;
                $stock = max(0.0, (float) $ingredient->stock);

                $newCost = $stock > 0
                    ? round(($stock * $oldCost + $quantity * $unitCost) / ($stock + $quantity), 4)
                    : $unitCost;

                $ingredient->forceFill(['cost_per_unit' => $newCost])->save();

                $purchase->items()->create([
                    'ingredient_id' => $ingredient->id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'subtotal' => $subtotal,
                ]);

                $this->applyMutation($ingredient, StockMutationType::Purchase, $quantity, $purchase->code, $purchase, $unitCost);

                $total += $subtotal;
            }

            $purchase->forceFill([
                'total' => round($total, 2),
                'code' => sprintf('PB-%s-%04d', $purchase->purchased_at->format('Ymd'), $purchase->id),
            ])->save();

            // Mutations were written before the code existed; give them the final label.
            $purchase->mutations()->update(['notes' => 'Pembelian '.$purchase->code]);

            return $purchase->load('items.ingredient');
        });
    }

    /**
     * Remove stock by hand: damaged, expired or spilled (stok keluar).
     *
     * @throws InsufficientStockException when removing more than is on hand
     */
    public function stockOut(Ingredient $ingredient, StockMutationType $type, float $quantity, string $reason): StockMutation
    {
        if (! $type->isWaste()) {
            throw new InvalidArgumentException("Jenis {$type->value} bukan jenis stok keluar.");
        }

        return $this->applyMutation($ingredient, $type, -abs($quantity), $reason);
    }

    /**
     * Set stock to the amount actually counted (stock opname) and record the
     * difference. Returns null when the count already matches.
     */
    public function adjustTo(Ingredient $ingredient, float $countedStock, string $reason): ?StockMutation
    {
        return DB::transaction(function () use ($ingredient, $countedStock, $reason) {
            $locked = Ingredient::whereKey($ingredient->getKey())->lockForUpdate()->firstOrFail();
            $difference = round($countedStock - (float) $locked->stock, 3);

            if ($difference == 0.0) {
                return null;
            }

            return $this->applyMutation($locked, StockMutationType::Adjustment, $difference, $reason);
        });
    }

    /**
     * Change an ingredient's stock and append the matching ledger row.
     *
     * Locks the ingredient row so concurrent changes are serialized, and
     * refuses to take stock below zero.
     *
     * @param  float  $quantity  signed: positive adds, negative removes
     * @param  float|null  $costPerUnit  cost to record; defaults to the ingredient's current cost
     *
     * @throws InsufficientStockException
     */
    public function applyMutation(
        Ingredient $ingredient,
        StockMutationType $type,
        float $quantity,
        ?string $notes = null,
        ?Model $reference = null,
        ?float $costPerUnit = null,
    ): StockMutation {
        return DB::transaction(function () use ($ingredient, $type, $quantity, $notes, $reference, $costPerUnit) {
            $locked = Ingredient::whereKey($ingredient->getKey())->lockForUpdate()->firstOrFail();

            $before = round((float) $locked->stock, 3);
            $after = round($before + $quantity, 3);

            if ($quantity < 0 && $after < 0) {
                throw new InsufficientStockException($locked, abs($quantity));
            }

            $locked->forceFill(['stock' => number_format($after, 3, '.', '')])->save();
            $ingredient->setRawAttributes($locked->getAttributes(), true);

            return StockMutation::create([
                'ingredient_id' => $locked->id,
                'user_id' => auth()->id(),
                'type' => $type,
                'quantity' => $quantity,
                'stock_before' => $before,
                'stock_after' => $after,
                'cost_per_unit' => $costPerUnit ?? $locked->cost_per_unit,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Total value of stock on hand (stock × cost per unit).
     */
    public function inventoryValue(): float
    {
        return (float) Ingredient::query()->sum(DB::raw('stock * cost_per_unit'));
    }
}
