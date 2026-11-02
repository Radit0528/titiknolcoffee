<?php

namespace Database\Factories;

use App\Enums\IngredientUnit;
use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supplier_id' => null,
            'name' => fake()->unique()->words(2, true),
            'unit' => IngredientUnit::Ml,
            'stock' => 1000,
            'min_stock' => 200,
            'cost_per_unit' => 20,
        ];
    }
}
