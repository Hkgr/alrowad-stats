<?php

namespace App\Models\Organization;

use Illuminate\Database\Eloquent\Model;

class OrgCenterYear extends Model
{
    public $timestamps = false;

    protected $fillable = ['project_id', 'center_id', 'year', 'beneficiaries'];
}
