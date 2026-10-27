<?php

namespace App\Models\Organization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgProject extends Model
{
    protected $fillable = ['program_id', 'slug', 'name', 'disabled_count', 'staff_count', 'sort_order'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(OrgProgram::class, 'program_id');
    }

    public function years(): HasMany
    {
        return $this->hasMany(OrgProjectYear::class, 'project_id');
    }
}
