<?php

namespace App\Enums;

enum IngredientUnit: string
{
    case Gram = 'gram';
    case Ml = 'ml';
    case Pcs = 'pcs';

    public function label(): string
    {
        return match ($this) {
            self::Gram => 'gram',
            self::Ml => 'ml',
            self::Pcs => 'pcs',
        };
    }
}
