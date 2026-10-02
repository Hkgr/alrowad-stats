<?php

namespace App\Services\Dashboard;

use App\Models\DataSource;
use App\Models\MainActivity;
use App\Models\Measure;
use App\Models\Period;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Sector;
use App\Models\SubActivity;
use Illuminate\Support\Collection;

/**
 * Everything the dashboard shows, computed once by BeneficiaryDashboardService from the same
 * active records, so cards, charts and tables can never disagree.
 */
final readonly class DashboardData
{
    /**
     * @param  list<OfficeFigures>  $offices  Rows inside all filters.
     * @param  list<OfficeFigures>  $comparison  Same scope ignoring the office filter (selected flagged).
     * @param  list<array{sector: Sector, classified: int, figures: ?Figures}>  $sectors
     * @param  list<array{project: Project, sector: ?Sector, figures: ?Figures}>  $projects
     * @param  list<array{period: Period, figures: Figures}>  $periods  Every period of the scope (period filter ignored, selected flagged by key).
     * @param  list<array{activity: MainActivity, figures: ?Figures, subs: int}>  $mainActivities
     * @param  list<array{activity: SubActivity, figures: ?Figures}>  $subActivities
     * @param  list<array{category: ProjectCategory, figures: ?Figures}>  $categories
     * @param  list<array{measure: Measure, total: int, items: ?int, records: int}>  $otherMeasures
     * @param  Collection<int, DataSource>  $sources
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
        public array $mainActivities,
        public ?Figures $withoutMainActivity,
        public array $subActivities,
        public ?Figures $withoutSubActivity,
        public array $categories,
        public array $otherMeasures,
        public Collection $sources,
    ) {}

    public function hasData(): bool
    {
        return $this->totals !== null;
    }
}
