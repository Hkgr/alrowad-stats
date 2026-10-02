<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Project → sector link valid for one reference year (e.g. the 2026 project list).
 * Records are classified by the assignment of their own period's year.
 */
class ProjectSectorAssignment extends Model
{
    protected $fillable = [
        'institution_id', 'project_id', 'sector_id', 'reference_year', 'source_name', 'data_source_id',
    ];

    protected function casts(): array
    {
        return ['reference_year' => 'integer'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }
}
