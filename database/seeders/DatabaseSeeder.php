<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Stammdaten (mehrfach aufrufbar). Nutzer entstehen ueber `php artisan kunst:user`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            EpochSeeder::class,
        ]);
    }
}
