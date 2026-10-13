<?php

namespace App\Models;

use App\Services\Regions\RegionCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Office extends Model
{
    protected $fillable = ['institution_id', 'slug', 'name', 'region_id', 'sort_order'];

    protected static function booted(): void
    {
        // A new office joins the region config/regions.php lists it under (e.g. one created by the importer).
        static::created(function (Office $office) {
            if ($office->region_id === null && Schema::hasTable('regions')) {
                app(RegionCatalog::class)->link($office);
            }
        });
    }

    /** Name for the filter and its chips: the configured label, else the office's own name. */
    public function label(): string
    {
        return config('regions.office_labels')[$this->slug] ?? $this->name;
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function activityRecords(): HasMany
    {
        return $this->hasMany(ActivityRecord::class);
    }
}
