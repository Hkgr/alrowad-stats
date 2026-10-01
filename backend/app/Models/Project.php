<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = ['institution_id', 'sector_id', 'slug', 'name', 'source_category'];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** Null while the project's sector is not documented. */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function beneficiaryRecords(): HasMany
    {
        return $this->hasMany(BeneficiaryRecord::class);
    }
}
