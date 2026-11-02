<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StockMutationType;
use App\Exceptions\CheckoutException;
use App\Exceptions\InsufficientStockException;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shift;
use App\Models\User;
use App\Models\Voucher;
use App\Support\Format;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Prices a cart and turns it into a paid order.
 *
 * Nothing from the browser is trusted except product ids, quantities, notes
 * and the voucher code: prices, discount, tax, totals and stock are all
 * recomputed here inside one database transaction.
 */
class CheckoutService
{
    public function __construct(
        private VoucherService $vouchers,
        private RecipeService $recipes,
        private InventoryService $inventory,
        private SettingService $settings,
    ) {}

    /**
     * Price a cart. Amounts are whole rupiah.
     *
     *   subtotal  = Σ price × qty
     *   discount  = voucher, capped at subtotal
     *   service   = (subtotal − discount) × service %
     *   tax       = (subtotal − discount + service) × tax %
     *   total     = subtotal − discount + service + tax
     *
     * @param  array<int, array{product_id: int, quantity: int, note?: string|null}>  $items
     * @return array{lines: list<array{product: Product, quantity: int, note: ?string, unit_price: int, subtotal: int}>, voucher: ?Voucher, subtotal: int, discount: int, service_charge: int, tax: int, total: int, service_rate: float, tax_rate: float}
     *
     * @throws CheckoutException
     */
    public function price(array $items, ?string $voucherCode = null, bool $lockVoucher = false): array
    {
        if ($items === []) {
            throw new CheckoutException('Keranjang masih kosong.', 'items');
        }

        $products = Product::with(['category', 'recipe.items.ingredient'])
            ->whereIn('id', array_column($items, 'product_id'))
            ->get()
            ->keyBy('id');

        $lines = [];
        foreach ($items as $item) {
            $product = $products->get($item['product_id']);

            if (! $product) {
                throw new CheckoutException('Ada menu di keranjang yang sudah tidak tersedia. Muat ulang halaman.', 'items');
            }
            if (! $product->is_available || ! $product->category?->is_active) {
                throw new CheckoutException("{$product->name} sedang habis.", 'items');
            }

            $unitPrice = (int) round((float) $product->price);
            $quantity = (int) $item['quantity'];

            $lines[] = [
                'product' => $product,
                'quantity' => $quantity,
                'note' => filled($item['note'] ?? null) ? trim($item['note']) : null,
                'unit_price' => $unitPrice,
                'subtotal' => $unitPrice * $quantity,
            ];
        }

        $subtotal = array_sum(array_column($lines, 'subtotal'));

        $voucher = filled($voucherCode) ? $this->vouchers->validate($voucherCode, $subtotal, $lockVoucher) : null;
        $discount = $voucher ? $this->vouchers->discountFor($voucher, $subtotal) : 0;

        $serviceRate = $this->settings->serviceChargeRate();
        $taxRate = $this->settings->taxRate();

        $base = $subtotal - $discount;
        $service = (int) round($base * $serviceRate / 100);
        $tax = (int) round(($base + $service) * $taxRate / 100);

        return [
            'lines' => $lines,
            'voucher' => $voucher,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'service_charge' => $service,
            'tax' => $tax,
            'total' => $base + $service + $tax,
            'service_rate' => $serviceRate,
            'tax_rate' => $taxRate,
        ];
    }

    /**
     * Amount added to (or taken off) a cash total to reach the nearest
     * rounding unit from settings, e.g. 21.450 → 21.500 with unit 500.
     */
    public function cashRounding(int $total): int
    {
        $unit = $this->settings->cashRounding();

        return $unit > 0 ? (int) (round($total / $unit) * $unit) - $total : 0;
    }

    /**
     * Pay a cart in cash: create the order, take stock, record the payment.
     *
     * Idempotent per `client_token`: repeating the same request (double click,
     * network retry) returns the order that was already created.
     *
     * @param  array{client_token: string, items: array, order_type: string, customer_name?: ?string, voucher_code?: ?string, cash_received: int|float|string, draft_id?: ?int}  $data
     *
     * @throws CheckoutException
     */
    public function checkoutCash(User $user, Shift $shift, array $data): Order
    {
        try {
            return DB::transaction(function () use ($user, $shift, $data) {
                if ($existing = Order::where('client_token', $data['client_token'])->first()) {
                    return $existing;
                }

                $priced = $this->price($data['items'], $data['voucher_code'] ?? null, lockVoucher: true);
                $rounding = $this->cashRounding($priced['total']);
                $grandTotal = $priced['total'] + $rounding;
                $received = (int) round((float) $data['cash_received']);

                if ($received < $grandTotal) {
                    throw new CheckoutException('Uang diterima kurang dari total '.Format::rupiah($grandTotal).'.', 'cash_received');
                }

                // Inserting the order first means a concurrent duplicate request
                // waits on the unique client_token and then fails cleanly.
                $order = Order::create([
                    'client_token' => $data['client_token'],
                    'shift_id' => $shift->id,
                    'user_id' => $user->id,
                    'status' => OrderStatus::Paid,
                    'order_type' => OrderType::from($data['order_type']),
                    'customer_name' => filled($data['customer_name'] ?? null) ? trim($data['customer_name']) : null,
                    'voucher_id' => $priced['voucher']?->id,
                    'voucher_code' => $priced['voucher']?->code,
                    'subtotal' => $priced['subtotal'],
                    'discount' => $priced['discount'],
                    'service_charge' => $priced['service_charge'],
                    'tax' => $priced['tax'],
                    'total' => $priced['total'],
                    'rounding' => $rounding,
                    'grand_total' => $grandTotal,
                    'service_charge_rate' => $priced['service_rate'],
                    'tax_rate' => $priced['tax_rate'],
                    'paid_at' => now(),
                ]);

                $order->forceFill(['code' => $this->orderCode($order)])->save();

                $this->writeItems($order, $priced['lines'], withHpp: true);

                if ($priced['voucher']) {
                    $this->vouchers->redeem($priced['voucher'], $order, $priced['discount']);
                }

                $this->deductStock($order, $priced['lines']);

                Payment::create([
                    'order_id' => $order->id,
                    'method' => PaymentMethod::Cash,
                    'status' => PaymentStatus::Paid,
                    'amount' => $grandTotal,
                    'cash_received' => $received,
                    'change' => $received - $grandTotal,
                    'paid_at' => now(),
                ]);

                if (! empty($data['draft_id'])) {
                    Order::whereKey($data['draft_id'])
                        ->where('user_id', $user->id)
                        ->where('status', OrderStatus::Draft)
                        ->delete();
                }

                return $order;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Lost the race against an identical request that already committed.
            return Order::where('client_token', $data['client_token'])->first() ?? throw $e;
        }
    }

    /**
     * Hold a cart as a draft. Drafts never touch stock or voucher quota.
     *
     * @param  array{items: array, order_type: string, customer_name?: ?string, voucher_code?: ?string}  $data
     */
    public function saveDraft(User $user, ?Shift $shift, array $data, ?Order $draft = null): Order
    {
        $priced = $this->price($data['items']);

        return DB::transaction(function () use ($user, $shift, $data, $draft, $priced) {
            $attributes = [
                'shift_id' => $shift?->id,
                'user_id' => $user->id,
                'status' => OrderStatus::Draft,
                'order_type' => OrderType::from($data['order_type']),
                'customer_name' => filled($data['customer_name'] ?? null) ? trim($data['customer_name']) : null,
                'voucher_code' => filled($data['voucher_code'] ?? null) ? strtoupper(trim($data['voucher_code'])) : null,
                'subtotal' => $priced['subtotal'],
                'total' => $priced['subtotal'],
                'grand_total' => $priced['subtotal'],
            ];

            if ($draft) {
                $draft->update($attributes);
                $draft->items()->delete();
            } else {
                $draft = Order::create($attributes);
            }

            $this->writeItems($draft, $priced['lines'], withHpp: false);

            return $draft;
        });
    }

    /**
     * @param  list<array{product: Product, quantity: int, note: ?string, unit_price: int, subtotal: int}>  $lines
     */
    private function writeItems(Order $order, array $lines, bool $withHpp): void
    {
        $hppTotal = 0.0;

        foreach ($lines as $line) {
            $unitHpp = $withHpp ? $this->recipes->hpp($line['product']) : 0.0;
            $hppTotal += $unitHpp * $line['quantity'];

            $order->items()->create([
                'product_id' => $line['product']->id,
                'product_name' => $line['product']->name,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'unit_hpp' => $unitHpp,
                'subtotal' => $line['subtotal'],
                'note' => $line['note'],
            ]);
        }

        if ($withHpp) {
            $order->forceFill(['hpp_total' => round($hppTotal, 2)])->save();
        }
    }

    /**
     * Take recipe quantities × ordered quantity out of stock.
     *
     * Requirements are summed per ingredient (two drinks sharing milk need
     * the total), then ingredients are locked in id order so concurrent
     * checkouts cannot deadlock. Any shortage aborts the whole order.
     *
     * @param  list<array{product: Product, quantity: int}>  $lines
     *
     * @throws CheckoutException
     */
    private function deductStock(Order $order, array $lines): void
    {
        $needs = [];
        $usedBy = [];

        foreach ($lines as $line) {
            foreach ($line['product']->recipe?->items ?? [] as $recipeItem) {
                $id = $recipeItem->ingredient_id;
                $needs[$id] = ($needs[$id] ?? 0) + (float) $recipeItem->quantity * $line['quantity'];
                $usedBy[$id][$line['product']->name] = true;
            }
        }

        ksort($needs);

        foreach ($needs as $ingredientId => $quantity) {
            try {
                $this->inventory->applyMutation(
                    Ingredient::withTrashed()->findOrFail($ingredientId),
                    StockMutationType::Sale,
                    -round($quantity, 3),
                    'Penjualan '.$order->code,
                    $order,
                );
            } catch (InsufficientStockException $e) {
                throw new CheckoutException(sprintf(
                    'Stok %s tidak cukup (tersisa %s %s, dibutuhkan %s) untuk: %s.',
                    $e->ingredient->name,
                    Format::qty($e->ingredient->stock),
                    $e->ingredient->unit->label(),
                    Format::qty($quantity),
                    implode(', ', array_keys($usedBy[$ingredientId])),
                ), 'items');
            }
        }
    }

    private function orderCode(Order $order): string
    {
        return sprintf('TN-%s-%05d', $order->created_at->format('ymd'), $order->id);
    }
}
