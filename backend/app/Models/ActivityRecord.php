<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One statistics row at the finest level documented by the source: project, month, office,
 * measure and (when present) Level 1 category, main activity (Level 2) and sub activity (Level 3).
 *
 * Totals of a main activity, a project, a sector or the institution are always sums of these
 * rows; no parent total is stored, so nothing can be counted twice.
 */
class ActivityRecord extends Model
{
    /** Figures compared when an import decides whether a record changed. */
    public const VALUE_FIELDS = [
        'category_id', 'main_activity_id', 'sub_activity_id', 'course_number', 'sections_count',
        'male_under_18', 'female_under_18', 'male_adult', 'female_adult', 'male_count', 'female_count',
        'disabled_count', 'total_count', 'items_count', 'details', 'source_sheet', 'source_row',
    ];

    protected $fillable = [
        'institution_id', 'project_id', 'period_id', 'office_id', 'measure_id', 'category_id',
        'main_activity_id', 'sub_activity_id', 'detail_key', 'course_number', 'sections_count',
        'male_under_18', 'female_under_18', 'male_adult', 'female_adult', 'male_count', 'female_count',
        'disabled_count', 'total_count', 'items_count', 'details', 'is_active', 'data_source_id',
        'source_file_id', 'source_sheet', 'source_row', 'import_run_id',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'is_active' => 'boolean',
            'male_count' => 'integer',
            'female_count' => 'integer',
            'total_count' => 'integer',
            'disabled_count' => 'integer',
            'items_count' => 'integer',
        ];
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('activity_records.is_active', true);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function measure(): BelongsTo
    {
        return $this->belongsTo(Measure::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProjectCategory::class, 'category_id');
    }

    public function mainActivity(): BelongsTo
    {
        return $this->belongsTo(MainActivity::class);
    }

    public function subActivity(): BelongsTo
    {
        return $this->belongsTo(SubActivity::class);
    }

    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(SourceFile::class);
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ActivityRecordRevision::class);
    }
}
