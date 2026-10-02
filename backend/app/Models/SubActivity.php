<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Level 3 — «النشاط الفرعي». Always a child of exactly one main activity. */
class SubActivity extends Model
{
    protected $fillable = ['institution_id', 'main_activity_id', 'slug', 'name', 'name_key'];

    public function mainActivity(): BelongsTo
    {
        return $this->belongsTo(MainActivity::class);
    }

    public function activityRecords(): HasMany
    {
        return $this->hasMany(ActivityRecord::class);
    }
}
