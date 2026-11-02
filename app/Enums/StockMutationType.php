<?php

namespace App\Enums;

/**
 * Every change to an ingredient's stock is recorded as one of these.
 */
enum StockMutationType: string
{
    /**
     * Reasons an owner can pick when removing stock by hand (stok keluar).
     *
     * @return list<self>
     */
    public static function stockOutTypes(): array
    {
        return [self::Damaged, self::Expired, self::Spilled];
    }

    /**
     * Mutations that count as wasted stock in the gross-profit report.
     */
    public function isWaste(): bool
    {
        return in_array($this, self::stockOutTypes(), true);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Initial, self::Purchase => 'success',
            self::Sale => 'neutral',
            self::Adjustment => 'warning',
            default => 'danger',
        };
    }

    case Initial = 'initial';
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Damaged = 'damaged';
    case Expired = 'expired';
    case Spilled = 'spilled';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Stok awal',
            self::Purchase => 'Pembelian',
            self::Sale => 'Penjualan',
            self::Damaged => 'Bahan rusak',
            self::Expired => 'Kedaluwarsa',
            self::Spilled => 'Tumpah/terbuang',
            self::Adjustment => 'Penyesuaian stok',
        };
    }
}
