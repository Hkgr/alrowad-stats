<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A track ("مسار") of the institution, e.g. مسار الصحة. */
class Sector extends Model
{
    protected $fillable = ['institution_id', 'slug', 'code', 'name', 'sort_order'];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function projectAssignments(): HasMany
    {
        return $this->hasMany(ProjectSectorAssignment::class);
    }
}
