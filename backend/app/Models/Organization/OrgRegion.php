<?php

namespace App\Models\Organization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgRegion extends Model
{
    protected $fillable = ['slug', 'name', 'disabled_count', 'staff_count', 'offices_count', 'sort_order'];

    public function years(): HasMany
    {
        return $this->hasMany(OrgRegionYear::class, 'region_id');
    }

    public function centers(): HasMany
    {
        return $this->hasMany(OrgCenter::class, 'region_id');
    }
}
