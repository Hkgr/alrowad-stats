<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Level 1 of a project's own sheet: an internal grouping, never a sector or a main activity. */
class ProjectCategory extends Model
{
    protected $fillable = ['institution_id', 'project_id', 'name', 'name_key'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function mainActivities(): HasMany
    {
        return $this->hasMany(MainActivity::class, 'category_id');
    }
}
