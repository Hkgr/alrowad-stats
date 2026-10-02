<?php

namespace App\Services\Dashboard;

use App\Models\Institution;
use App\Models\Office;
use App\Models\Period;
use App\Models\Project;
use App\Models\Sector;

/**
 * Validated, already-resolved filter state. The institution is mandatory and every other
 * filter has been checked to belong to it, so services can trust these models.
 *
 * classificationYear is the reference year used to list projects per sector: the selected
 * period's year, otherwise the latest year that has a classification. Figures themselves are
 * always classified by the year of each record's own period.
 */
final readonly class DashboardFilters
{
    public function __construct(
        public Institution $institution,
        public ?Sector $sector = null,
        public ?Project $project = null,
        public ?Period $period = null,
        public ?Office $office = null,
        public ?int $classificationYear = null,
    ) {}

    public function level(): string
    {
        return $this->project ? 'project' : ($this->sector ? 'sector' : 'overview');
    }
}
