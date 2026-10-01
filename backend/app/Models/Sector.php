<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    protected $fillable = ['institution_id', 'slug', 'name'];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
