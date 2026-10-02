<?php

namespace App\Services\Dashboard;

use App\Models\Institution;
use App\Models\MainActivity;
use App\Models\Office;
use App\Models\Period;
use App\Models\Project;
use App\Models\Sector;
use App\Models\SubActivity;

/**
 * Validated, already-resolved filter state. Every model has been checked to belong to the
 * institution, the main activity to the project and the sub activity to the main activity.
 *
 * - classificationYear: reference year used to list projects per sector (the selected period's
 *   year, else the latest classified year). Figures are always classified by the year of each
 *   record's own period.
 * - unclassified: the pseudo-sector of records whose project has no sector for that year.
 * - From the project level down, the sector is navigation context only: the figures cover all of
 *   the project's records.
 */
final readonly class DashboardFilters
{
    public const UNCLASSIFIED = 'unclassified';

    public function __construct(
        public Institution $institution,
        public ?Sector $sector = null,
        public ?Project $project = null,
        public ?Period $period = null,
        public ?Office $office = null,
        public ?int $classificationYear = null,
        public bool $unclassified = false,
        public ?MainActivity $mainActivity = null,
        public ?SubActivity $subActivity = null,
    ) {}

    public function level(): string
    {
        return match (true) {
            $this->subActivity !== null => 'sub_activity',
            $this->mainActivity !== null => 'main_activity',
            $this->project !== null => 'project',
            $this->sector !== null || $this->unclassified => 'sector',
            default => 'overview',
        };
    }

    /** Same filters with some of them dropped (for sibling views such as "all sectors"). */
    public function without(string ...$names): self
    {
        $drop = array_flip($names);

        return new self(
            institution: $this->institution,
            sector: isset($drop['sector']) ? null : $this->sector,
            project: isset($drop['project']) ? null : $this->project,
            period: isset($drop['period']) ? null : $this->period,
            office: isset($drop['office']) ? null : $this->office,
            classificationYear: $this->classificationYear,
            unclassified: isset($drop['sector']) ? false : $this->unclassified,
            mainActivity: isset($drop['main']) || isset($drop['project']) ? null : $this->mainActivity,
            subActivity: isset($drop['sub']) || isset($drop['main']) || isset($drop['project']) ? null : $this->subActivity,
        );
    }
}
