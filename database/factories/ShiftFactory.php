<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->cashier(),
            'open_user_id' => fn (array $attributes) => $attributes['user_id'],
            'opening_cash' => 200000,
            'opened_at' => now(),
        ];
    }
}
