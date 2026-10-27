<?php

namespace App\Models\Organization;

use Illuminate\Database\Eloquent\Model;

class OrgRegionYear extends Model
{
    public $timestamps = false;

    protected $fillable = ['region_id', 'year', 'beneficiaries', 'projects_count'];
}
