<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Services\Regions\RegionCatalog;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /** Idempotent: applies config/regions.php to every institution. */
    public function run(): void
    {
        foreach (Institution::all() as $institution) {
            app(RegionCatalog::class)->sync($institution);
        }
    }
}
