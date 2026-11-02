<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';   // waiting for QRIS payment (Tahap 5)
    case Paid = 'paid';
    case Expired = 'expired';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Menunggu bayar',
            self::Paid => 'Lunas',
            self::Expired => 'Kedaluwarsa',
            self::Void => 'Dibatalkan',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::Pending => 'warning',
            self::Draft => 'neutral',
            self::Expired, self::Void => 'danger',
        };
    }
}
