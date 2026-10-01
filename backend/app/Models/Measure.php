<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Measure extends Model
{
    /** Registered participations of people, split by gender. Not unique beneficiaries. */
    public const REGISTERED_BENEFITS = 'registered_benefits';

    public const AGGREGATION_LABELS = [
        'sum' => 'مجموع',
    ];

    public const RECORD_LEVEL_LABELS = [
        'project_office_month' => 'مشروع ومكتب وشهر',
    ];

    protected $fillable = [
        'code', 'name', 'unit', 'unit_label', 'record_level', 'aggregation', 'breakdown', 'description',
    ];

    public function beneficiaryRecords(): HasMany
    {
        return $this->hasMany(BeneficiaryRecord::class);
    }
}
