<?php

namespace App\Exceptions;

use App\Models\Ingredient;
use App\Support\Format;
use RuntimeException;

/**
 * Thrown when a stock change would take an ingredient below zero.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(public readonly Ingredient $ingredient, public readonly float $requested)
    {
        parent::__construct(sprintf(
            'Stok %s tidak cukup: tersedia %s %s, dibutuhkan %s %s.',
            $ingredient->name,
            Format::qty($ingredient->stock),
            $ingredient->unit->label(),
            Format::qty($requested),
            $ingredient->unit->label(),
        ));
    }
}
