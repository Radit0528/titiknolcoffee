<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\CheckoutException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\ShiftCashMovement;
use App\Models\User;
use App\Support\Format;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ShiftService
{
    public function __construct(private AuditLogger $audit) {}

    public function current(User $user): ?Shift
    {
        return Shift::where('user_id', $user->id)->open()->latest('id')->first();
    }

    /**
     * Open a shift with the starting cash in the drawer.
     *
     * The unique `open_user_id` column guarantees one open shift per cashier
     * even if two tabs submit at the same moment.
     *
     * @throws CheckoutException when the cashier already has an open shift
     */
    public function open(User $user, int $openingCash): Shift
    {
        try {
            return DB::transaction(function () use ($user, $openingCash) {
                if ($this->current($user)) {
                    throw new CheckoutException('Anda masih memiliki shift yang aktif.', 'opening_cash');
                }

                return Shift::create([
                    'user_id' => $user->id,
                    'open_user_id' => $user->id,
                    'opening_cash' => $openingCash,
                    'opened_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new CheckoutException('Anda masih memiliki shift yang aktif.', 'opening_cash');
        }
    }

    /**
     * Record cash put into or taken out of the drawer outside of sales.
     * Cash out can never exceed what the drawer should contain.
     *
     * @throws CheckoutException
     */
    public function recordCash(Shift $shift, User $user, CashMovementType $type, int $amount, string $reason): ShiftCashMovement
    {
        return DB::transaction(function () use ($shift, $user, $type, $amount, $reason) {
            $locked = Shift::whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw new CheckoutException('Shift sudah ditutup.', 'amount');
            }

            if ($type === CashMovementType::Out) {
                $available = $this->summary($locked)['expected_cash'];

                if ($amount > $available) {
                    throw new CheckoutException('Kas keluar melebihi uang di laci (seharusnya '.Format::rupiah($available).').', 'amount');
                }
            }

            return $locked->cashMovements()->create([
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $amount,
                'reason' => trim($reason),
            ]);
        });
    }

    /**
     * Figures for the shift report. Only paid orders count as sales.
     *
     *   expected cash = opening cash + cash sales + cash in − cash out
     *
     * @return array{opening_cash: int, cash_sales: int, qris_sales: int, total_sales: int, cash_in: int, cash_out: int, expected_cash: int, order_count: int, item_count: int, discount: int, void_count: int, void_total: int, pending_count: int, draft_count: int}
     */
    public function summary(Shift $shift): array
    {
        $paidOrders = Order::where('shift_id', $shift->id)->where('status', OrderStatus::Paid);

        $sales = Payment::query()
            ->whereIn('order_id', (clone $paidOrders)->select('id'))
            ->where('status', PaymentStatus::Paid)
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method');

        $movements = ShiftCashMovement::where('shift_id', $shift->id)
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $opening = (int) round((float) $shift->opening_cash);
        $cashSales = (int) round((float) ($sales[PaymentMethod::Cash->value] ?? 0));
        $qrisSales = (int) round((float) ($sales[PaymentMethod::Qris->value] ?? 0));
        $cashIn = (int) round((float) ($movements[CashMovementType::In->value] ?? 0));
        $cashOut = (int) round((float) ($movements[CashMovementType::Out->value] ?? 0));

        return [
            'opening_cash' => $opening,
            'cash_sales' => $cashSales,
            'qris_sales' => $qrisSales,
            'total_sales' => $cashSales + $qrisSales,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'expected_cash' => $opening + $cashSales + $cashIn - $cashOut,
            'order_count' => (clone $paidOrders)->count(),
            'item_count' => (int) OrderItem::whereIn('order_id', (clone $paidOrders)->select('id'))->sum('quantity'),
            'discount' => (int) round((float) (clone $paidOrders)->sum('discount')),
            'void_count' => Order::where('shift_id', $shift->id)->where('status', OrderStatus::Void)->count(),
            'void_total' => (int) round((float) Order::where('shift_id', $shift->id)->where('status', OrderStatus::Void)->sum('grand_total')),
            'pending_count' => Order::where('shift_id', $shift->id)->where('status', OrderStatus::Pending)->count(),
            'draft_count' => Order::where('user_id', $shift->user_id)->where('status', OrderStatus::Draft)->count(),
        ];
    }

    /**
     * Close the shift with the cash physically counted in the drawer.
     *
     *   variance = counted cash − expected cash
     *
     * Refused while a payment of this shift is still waiting (QRIS, once the
     * payment gateway is added), and a
     * non-zero variance must be explained.
     *
     * @throws CheckoutException
     */
    public function close(Shift $shift, int $countedCash, ?string $notes): Shift
    {
        $closed = DB::transaction(function () use ($shift, $countedCash, $notes) {
            $locked = Shift::whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw new CheckoutException('Shift ini sudah ditutup.', 'closing_cash');
            }

            $summary = $this->summary($locked);

            if ($summary['pending_count'] > 0) {
                throw new CheckoutException(
                    "Masih ada {$summary['pending_count']} transaksi yang menunggu pembayaran. Selesaikan atau batalkan dulu.",
                    'closing_cash',
                );
            }

            $variance = $countedCash - $summary['expected_cash'];

            if ($variance !== 0 && blank($notes)) {
                throw new CheckoutException(
                    'Uang fisik '.($variance > 0 ? 'lebih ' : 'kurang ').Format::rupiah(abs($variance)).'. Tulis penjelasan selisih kas.',
                    'closing_notes',
                );
            }

            $locked->forceFill([
                'closed_at' => now(),
                'open_user_id' => null,
                'expected_cash' => $summary['expected_cash'],
                'closing_cash' => $countedCash,
                'cash_variance' => $variance,
                'closing_notes' => filled($notes) ? trim($notes) : null,
            ])->save();

            return $locked;
        });

        $this->audit->log('shift.closed', $closed, [], [
            'expected_cash' => (string) $closed->expected_cash,
            'closing_cash' => (string) $closed->closing_cash,
            'cash_variance' => (string) $closed->cash_variance,
        ]);

        return $closed;
    }
}
