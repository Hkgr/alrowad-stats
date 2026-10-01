<?php

namespace App\Services\Dashboard;

use App\Models\DataSource;
use App\Models\Measure;
use App\Models\Period;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Everything the dashboard shows, computed once. Cards, charts and table are all views of
 * the same $offices rows, so they can never disagree.
 */
final readonly class DashboardData
{
    /**
     * @param  list<OfficeFigures>  $offices  Rows inside the filters (cards, gender split, table).
     * @param  list<OfficeFigures>  $comparison  Same scope but ignoring the office filter, selected one flagged (bar charts).
     * @param  Collection<int, Project>  $projects  Projects that contribute to the figures.
     * @param  Collection<int, Period>  $periods  Periods that contribute to the figures.
     * @param  Collection<int, DataSource>  $sources
     */
    public function __construct(
        public DashboardFilters $filters,
        public Measure $measure,
        public array $offices,
        public array $comparison,
        public Collection $projects,
        public Collection $periods,
        public Collection $sources,
    ) {}

    public function hasData(): bool
    {
        return $this->offices !== [];
    }

    public function male(): int
    {
        return array_sum(array_map(fn (OfficeFigures $o) => $o->male, $this->offices));
    }

    public function female(): int
    {
        return array_sum(array_map(fn (OfficeFigures $o) => $o->female, $this->offices));
    }

    public function total(): int
    {
        return $this->male() + $this->female();
    }
}
