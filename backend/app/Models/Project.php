<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = ['institution_id', 'slug', 'name', 'source_category'];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** Sector links, one per reference year. */
    public function sectorAssignments(): HasMany
    {
        return $this->hasMany(ProjectSectorAssignment::class);
    }

    public function beneficiaryRecords(): HasMany
    {
        return $this->hasMany(BeneficiaryRecord::class);
    }

    /** The sector this project belongs to in the given reference year, or null when unclassified. */
    public function sectorIn(int $year): ?Sector
    {
        return $this->sectorAssignments()->where('reference_year', $year)->first()?->sector;
    }
}
