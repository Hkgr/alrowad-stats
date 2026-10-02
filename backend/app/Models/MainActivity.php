<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Level 2 — «النشاط الرئيسي». Belongs to one project (and to its Level 1 category when the source has one). */
class MainActivity extends Model
{
    protected $fillable = ['institution_id', 'project_id', 'category_id', 'category_key', 'slug', 'name', 'name_key'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProjectCategory::class, 'category_id');
    }

    public function subActivities(): HasMany
    {
        return $this->hasMany(SubActivity::class);
    }

    public function activityRecords(): HasMany
    {
        return $this->hasMany(ActivityRecord::class);
    }
}
