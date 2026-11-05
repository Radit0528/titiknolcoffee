<?php

namespace App\Enums;

/**
 * Titik Nol is a container outlet with no seating, so every order is take away.
 * Kept as an enum so a new channel (e.g. online delivery) can be added later.
 */
enum OrderType: string
{
    case TakeAway = 'take_away';

    public function label(): string
    {
        return match ($this) {
            self::TakeAway => 'Take Away',
        };
    }
}
