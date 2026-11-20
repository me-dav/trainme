<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with every module file in the content directory.
     * Accounts are created explicitly with `php artisan dojo:user`, never with a default password.
     */
    public function run(): void
    {
        Artisan::call('dojo:import', [], $this->command?->getOutput());
    }
}
