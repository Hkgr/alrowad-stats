<?php

namespace App\Services\Dashboard;

use App\Models\Institution;
use App\Models\Office;
use App\Models\Period;
use App\Models\Project;

/**
 * Validated, already-resolved filter state. The institution is mandatory and every other
 * filter has been checked to belong to it, so services can trust these models.
 */
final readonly class DashboardFilters
{
    public function __construct(
        public Institution $institution,
        public ?Project $project = null,
        public ?Period $period = null,
        public ?Office $office = null,
    ) {}
}
