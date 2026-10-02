<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Measure extends Model
{
    /** Registered participations of people, split by gender. Not unique beneficiaries. */
    public const REGISTERED_BENEFITS = 'registered_benefits';

    /** Families served (e.g. «أضحيتي»). A different unit: never summed with people. */
    public const HOUSEHOLDS_SERVED = 'households_served';

    public const AGGREGATION_LABELS = [
        'sum' => 'مجموع',
    ];

    public const RECORD_LEVEL_LABELS = [
        'project_office_month' => 'مشروع ومكتب وشهر',
        'activity_office_month' => 'نشاط ومكتب وشهر',
    ];

    protected $fillable = [
        'code', 'name', 'unit', 'unit_label', 'record_level', 'aggregation', 'breakdown', 'items_label', 'description',
    ];

    public function activityRecords(): HasMany
    {
        return $this->hasMany(ActivityRecord::class);
    }
}
