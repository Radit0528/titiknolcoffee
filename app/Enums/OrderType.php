<?php

namespace App\Enums;

enum OrderType: string
{
    case DineIn = 'dine_in';
    case TakeAway = 'take_away';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'Dine In',
            self::TakeAway => 'Take Away',
        };
    }
}
