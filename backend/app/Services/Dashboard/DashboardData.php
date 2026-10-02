<?php

namespace App\Services\Dashboard;

use App\Models\DataSource;
use App\Models\Measure;
use App\Models\Period;
use App\Models\Project;
use App\Models\Sector;
use Illuminate\Support\Collection;

/**
 * Everything the dashboard shows, computed once by BeneficiaryDashboardService.
 * Cards, charts and tables are views of these rows, so they can never disagree.
 */
final readonly class DashboardData
{
    /**
     * @param  list<OfficeFigures>  $offices  Rows inside all filters (cards, gender split, office table).
     * @param  list<OfficeFigures>  $comparison  Same scope ignoring the office filter; the selected office is flagged.
     * @param  list<array{sector: Sector, classified: int, figures: ?Figures}>  $sectors  Every sector; figures ignore sector/project filters.
     * @param  ?Figures  $unclassified  Records whose project has no sector for its period's year (null when none).
     * @param  list<array{project: Project, sector: ?Sector, figures: ?Figures}>  $projects  Projects of the current sector (or all).
     * @param  list<array{period: Period, figures: Figures}>  $periods  Periods with records inside all filters.
     * @param  ?Sector  $activeSector  Selected sector, or the selected project's sector.
     * @param  int  $classifiedProjects  Projects classified in the current scope (sector or all) for the classification year.
     * @param  ?Figures  $totals  All filters applied; null when there are no records.
     * @param  Collection<int, DataSource>  $sources  Sources behind the figures (kept for traceability, not displayed).
     */
    public function __construct(
        public DashboardFilters $filters,
        public Measure $measure,
        public array $offices,
        public array $comparison,
        public array $sectors,
        public ?Figures $unclassified,
        public array $projects,
        public array $periods,
        public ?Sector $activeSector,
        public int $classifiedProjects,
        public ?Figures $totals,
        public Collection $sources,
    ) {}

    public function hasData(): bool
    {
        return $this->totals !== null;
    }
}
