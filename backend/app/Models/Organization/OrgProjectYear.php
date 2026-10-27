<?php

namespace App\Models\Organization;

use Illuminate\Database\Eloquent\Model;

class OrgProjectYear extends Model
{
    public $timestamps = false;

    protected $fillable = ['project_id', 'year', 'beneficiaries'];
}
