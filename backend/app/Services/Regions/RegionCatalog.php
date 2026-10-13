<?php

namespace App\Services\Regions;

use App\Models\Institution;
use App\Models\Office;
use App\Models\Region;

/**
 * Applies config/regions.php: creates the regions and links each listed office to its region.
 * Idempotent; an office slug the institution does not have yet is skipped here and is linked the
 * moment it is created (see Office::booted), so the importer never leaves a known office without a region.
 */
class RegionCatalog
{
    /** @return array{regions: int, linked: int} */
    public function sync(Institution $institution): array
    {
        $linked = 0;

        foreach (config('regions.regions', []) as $order => $definition) {
            $region = $this->region($institution->id, $order, $definition);

            $linked += Office::where('institution_id', $institution->id)
                ->whereIn('slug', $definition['offices'])
                ->update(['region_id' => $region->id]);
        }

        return ['regions' => count(config('regions.regions', [])), 'linked' => $linked];
    }

    /** Links one office to the region the config lists it under (no-op for an office it does not list). */
    public function link(Office $office): void
    {
        foreach (config('regions.regions', []) as $order => $definition) {
            if (in_array($office->slug, $definition['offices'], true)) {
                $office->forceFill(['region_id' => $this->region($office->institution_id, $order, $definition)->id])->saveQuietly();

                return;
            }
        }
    }

    /** @param array{slug: string, name: string, offices: list<string>, has_offices?: bool} $definition */
    private function region(int $institutionId, int $order, array $definition): Region
    {
        return Region::updateOrCreate(
            ['institution_id' => $institutionId, 'slug' => $definition['slug']],
            ['name' => $definition['name'], 'has_offices' => $definition['has_offices'] ?? true, 'sort_order' => $order + 1],
        );
    }
}
