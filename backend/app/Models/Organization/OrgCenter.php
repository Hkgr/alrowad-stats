<?php

namespace App\Models\Organization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrgCenter extends Model
{
    protected $fillable = ['region_id', 'slug', 'name', 'sort_order'];

    public function region(): BelongsTo
    {
        return $this->belongsTo(OrgRegion::class, 'region_id');
    }
}
