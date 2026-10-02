<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataSource extends Model
{
    public const COVERAGE_SAMPLE = 'sample';

    public const COVERAGE_FULL = 'full';

    /** A reference list (e.g. project → track), not a source of figures. */
    public const COVERAGE_REFERENCE = 'reference';

    protected $fillable = [
        'institution_id', 'slug', 'label', 'file_name', 'reference_url', 'coverage', 'notes',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function beneficiaryRecords(): HasMany
    {
        return $this->hasMany(BeneficiaryRecord::class);
    }
}
