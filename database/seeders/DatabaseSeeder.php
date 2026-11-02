<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Accounts are always seeded. Sample menu/inventory data is only seeded
     * outside production so a live shop starts with clean master data.
     */
    public function run(): void
    {
        $this->call(UserSeeder::class);

        if (! app()->isProduction()) {
            $this->call(MasterDataSeeder::class);
        }
    }
}
