<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Default accounts. CHANGE THESE PASSWORDS before using the app for real.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['username' => 'owner'], [
            'name' => 'Pemilik Titik Nol',
            'password' => 'password',
            'role' => UserRole::Owner,
            'is_active' => true,
        ]);

        User::updateOrCreate(['username' => 'kasir'], [
            'name' => 'Kasir Satu',
            'password' => 'password',
            'role' => UserRole::Cashier,
            'is_active' => true,
        ]);
    }
}
