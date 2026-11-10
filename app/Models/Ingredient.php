<?php

namespace App\Models;

use App\Enums\IngredientUnit;
use App\Enums\StockStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingredient extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * `stock` is deliberately not fillable: it only changes through
     * InventoryService so that every change leaves a stock mutation.
     */
    protected $fillable = ['name', 'unit', 'min_stock', 'cost_per_unit'];

    protected function casts(): array
    {
        return [
            'unit' => IngredientUnit::class,
            'stock' => 'decimal:3',
            'min_stock' => 'decimal:3',
            'cost_per_unit' => 'decimal:4',
        ];
    }

    public function mutations(): HasMany
    {
        return $this->hasMany(StockMutation::class);
    }

    public function recipeItems(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }

    public function stockStatus(): StockStatus
    {
        return StockStatus::evaluate((float) $this->stock, (float) $this->min_stock);
    }

    public function stockValue(): float
    {
        return (float) $this->stock * (float) $this->cost_per_unit;
    }

    /**
     * Ingredients at or below their minimum (including those that ran out).
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock', '<=', 'min_stock');
    }
}
