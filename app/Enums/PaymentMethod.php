<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Qris = 'qris';             // DOKU payment gateway (confirmed automatically); value kept for existing orders
    case QrisKedai = 'qris_kedai';  // shop's own QRIS with amount, confirmed by the cashier

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Qris => 'DOKU',
            self::QrisKedai => 'QRIS Kedai',
        };
    }
}
