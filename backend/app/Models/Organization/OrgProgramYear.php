<?php

namespace App\Models\Organization;

use Illuminate\Database\Eloquent\Model;

class OrgProgramYear extends Model
{
    public $timestamps = false;

    protected $fillable = ['program_id', 'year', 'projects_count'];
}
