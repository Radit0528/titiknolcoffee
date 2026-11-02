<?php

namespace App\Models;

use App\Enums\StockMutationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only ledger of stock changes. Rows are never updated.
 */
class StockMutation extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'ingredient_id', 'user_id', 'type', 'quantity', 'stock_before', 'stock_after',
        'cost_per_unit', 'reference_type', 'reference_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockMutationType::class,
            'quantity' => 'decimal:3',
            'stock_before' => 'decimal:3',
            'stock_after' => 'decimal:3',
            'cost_per_unit' => 'decimal:4',
        ];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
