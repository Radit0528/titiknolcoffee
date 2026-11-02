<?php

namespace App\Enums;

enum StockStatus: string
{
    case Safe = 'safe';
    case Low = 'low';
    case Empty = 'empty';

    /**
     * Habis when nothing is left, Menipis at or below the minimum, otherwise Aman.
     */
    public static function evaluate(float $stock, float $minimum): self
    {
        if ($stock <= 0) {
            return self::Empty;
        }

        return $stock <= $minimum ? self::Low : self::Safe;
    }

    public function label(): string
    {
        return match ($this) {
            self::Safe => 'Aman',
            self::Low => 'Menipis',
            self::Empty => 'Habis',
        };
    }

    /**
     * Variant name understood by the <x-ui.badge> component.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Safe => 'success',
            self::Low => 'warning',
            self::Empty => 'danger',
        };
    }
}
