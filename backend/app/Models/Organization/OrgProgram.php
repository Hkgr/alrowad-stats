<?php

namespace App\Models\Organization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgProgram extends Model
{
    protected $fillable = ['slug', 'code', 'name', 'sort_order'];

    public function projects(): HasMany
    {
        return $this->hasMany(OrgProject::class, 'program_id');
    }

    public function years(): HasMany
    {
        return $this->hasMany(OrgProgramYear::class, 'program_id');
    }
}
