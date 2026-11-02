<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(BaseSeeder::class);
        // demo history is only seeded if explicitly enabled via SILABAR_SEED_DEMO
        if (env('SILABAR_SEED_DEMO', false)) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
