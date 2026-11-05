<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\StockMutationType;
use App\Exceptions\CheckoutException;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderVoid;
use App\Models\Shift;
use App\Models\StockMutation;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;

/**
 * Cancel a paid order. The owner approves with their PIN (checked by the
 * caller through OwnerPinService); this service applies the effects:
 *
 *   - order status → void (reports and shift totals only count paid orders)
 *   - every ingredient deducted by the sale is put back (mutation type "void")
 *   - the voucher use is released
 *   - one order_voids row records who asked, who approved, why, and the amount
 */
class VoidService
{
    public function __construct(
        private InventoryService $inventory,
        private AuditLogger $audit,
    ) {}

    /**
     * @throws CheckoutException
     */
    public function void(Order $order, User $requestedBy, User $approvedBy, string $reason): OrderVoid
    {
        return DB::transaction(function () use ($order, $requestedBy, $approvedBy, $reason) {
            // Lock the shift first (same order as closing a shift), then the order.
            $shift = $order->shift_id ? Shift::whereKey($order->shift_id)->lockForUpdate()->first() : null;
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === OrderStatus::Void) {
                throw new CheckoutException('Transaksi ini sudah dibatalkan.', 'reason');
            }
            if ($locked->status !== OrderStatus::Paid) {
                throw new CheckoutException('Hanya transaksi lunas yang bisa dibatalkan.', 'reason');
            }
            if (! $shift?->isOpen()) {
                throw new CheckoutException('Shift transaksi ini sudah ditutup, jadi tidak bisa dibatalkan lagi.', 'reason');
            }

            $this->returnStock($locked);
            $this->releaseVoucher($locked);

            $locked->update(['status' => OrderStatus::Void]);

            $void = OrderVoid::create([
                'order_id' => $locked->id,
                'requested_by' => $requestedBy->id,
                'approved_by' => $approvedBy->id,
                'reason' => $reason,
                'amount' => $locked->grand_total,
            ]);

            $this->audit->log('order.voided', $locked, ['status' => OrderStatus::Paid->value], [
                'status' => OrderStatus::Void->value,
                'code' => $locked->code,
                'amount' => (float) $locked->grand_total,
                'reason' => $reason,
                'approved_by' => $approvedBy->name,
            ]);

            $order->setRawAttributes($locked->getAttributes(), true);

            return $void;
        });
    }

    /**
     * Put back exactly what the sale took, ingredient by ingredient (id order
     * for consistent locking), recorded at the cost of the original sale.
     */
    private function returnStock(Order $order): void
    {
        $sold = StockMutation::query()
            ->where('reference_type', $order->getMorphClass())
            ->where('reference_id', $order->id)
            ->where('type', StockMutationType::Sale)
            ->orderBy('ingredient_id')
            ->get();

        foreach ($sold->groupBy('ingredient_id') as $ingredientId => $rows) {
            $quantity = round(-$rows->sum(fn ($m) => (float) $m->quantity), 3);

            if ($quantity <= 0) {
                continue;
            }

            $this->inventory->applyMutation(
                Ingredient::withTrashed()->findOrFail($ingredientId),
                StockMutationType::VoidReturn,
                $quantity,
                'Void '.$order->code,
                $order,
                (float) $rows->first()->cost_per_unit,
            );
        }
    }

    private function releaseVoucher(Order $order): void
    {
        if (! $order->voucher_id) {
            return;
        }

        $voucher = Voucher::whereKey($order->voucher_id)->lockForUpdate()->first();
        $deleted = $voucher?->usages()->where('order_id', $order->id)->delete();

        if ($deleted && $voucher->used_count > 0) {
            $voucher->decrement('used_count');
        }
    }
}
