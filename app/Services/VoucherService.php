<?php

namespace App\Services;

use App\Enums\VoucherType;
use App\Exceptions\CheckoutException;
use App\Models\Order;
use App\Models\Voucher;
use App\Support\Format;

class VoucherService
{
    /**
     * Find a voucher by code and check it can be used for this subtotal.
     * Pass $lock inside a checkout transaction so quota cannot be overspent.
     *
     * @throws CheckoutException
     */
    public function validate(string $code, int $subtotal, bool $lock = false): Voucher
    {
        $voucher = Voucher::query()
            ->where('code', strtoupper(trim($code)))
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->first();

        $fail = fn (string $message) => throw new CheckoutException($message, 'voucher_code');

        if (! $voucher) {
            $fail('Kode voucher tidak ditemukan.');
        }
        if (! $voucher->is_active) {
            $fail('Voucher sedang tidak aktif.');
        }
        if ($voucher->starts_at && $voucher->starts_at->isFuture()) {
            $fail('Voucher baru berlaku mulai '.$voucher->starts_at->translatedFormat('d M Y H:i').'.');
        }
        if ($voucher->ends_at && $voucher->ends_at->isPast()) {
            $fail('Voucher sudah kedaluwarsa.');
        }
        if ($voucher->quota !== null && $voucher->used_count >= $voucher->quota) {
            $fail('Kuota voucher sudah habis.');
        }
        if ($subtotal < (float) $voucher->min_purchase) {
            $fail('Minimum pembelian untuk voucher ini '.Format::rupiah($voucher->min_purchase).'.');
        }

        return $voucher;
    }

    /**
     * Discount in whole rupiah. Never more than the subtotal.
     */
    public function discountFor(Voucher $voucher, int $subtotal): int
    {
        $discount = $voucher->type === VoucherType::Percent
            ? (int) floor($subtotal * (float) $voucher->value / 100)
            : (int) round((float) $voucher->value);

        if ($voucher->max_discount !== null) {
            $discount = min($discount, (int) round((float) $voucher->max_discount));
        }

        return max(0, min($discount, $subtotal));
    }

    /**
     * Count one use of the voucher. Call inside the checkout transaction after
     * validate(..., lock: true).
     */
    public function redeem(Voucher $voucher, Order $order, int $discount): void
    {
        $voucher->increment('used_count');

        $voucher->usages()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'discount' => $discount,
        ]);
    }
}
