<?php

namespace App\Console\Commands;

use App\Models\Institution;
use App\Services\Regions\RegionCatalog;
use Illuminate\Console\Command;

class SyncRegions extends Command
{
    protected $signature = 'rowad:sync-regions';

    protected $description = 'Apply config/regions.php: create the regions and link each office to its region (safe to re-run).';

    public function handle(RegionCatalog $catalog): int
    {
        foreach (Institution::all() as $institution) {
            $result = $catalog->sync($institution);
            $this->info("{$institution->slug}: {$result['regions']} regions, {$result['linked']} offices linked.");
        }

        return self::SUCCESS;
    }
}
