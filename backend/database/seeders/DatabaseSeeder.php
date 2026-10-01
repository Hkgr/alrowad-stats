<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. Every seeder called here is idempotent.
     */
    public function run(): void
    {
        $this->call(LobaWaFarhaMay2026SampleSeeder::class);
    }
}
