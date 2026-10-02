<?php

namespace App\Services\Dashboard;

use App\Models\ActivityRecord;
use App\Models\DataSource;
use App\Models\MainActivity;
use App\Models\Measure;
use App\Models\Period;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectSectorAssignment;
use App\Models\Sector;
use App\Models\SubActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

/**
 * Single source of truth for the dashboard numbers.
 *
 * Every query reads active activity_records of one institution and one measure. Records are stored
 * at the finest documented level, so the totals of a sub activity, a main activity, a project, a
 * sector and the institution are all plain sums of the same rows — nothing is counted twice.
 *
 * A record belongs to a sector through its project's assignment for the year of the record's own
 * period. Offices and projects are counted DISTINCT. Measures are never combined: people
 * (registered_benefits) and families (households_served) are reported separately.
 */
class BeneficiaryDashboardService
{
    private const AGGREGATES = 'SUM(activity_records.total_count) AS total, '
        .'SUM(COALESCE(activity_records.male_count, 0)) AS male, SUM(COALESCE(activity_records.female_count, 0)) AS female, '
        .'COUNT(DISTINCT activity_records.project_id) AS projects, COUNT(DISTINCT activity_records.office_id) AS offices, '
        .'COUNT(*) AS records, SUM(activity_records.disabled_count) AS disabled';

    public function build(DashboardFilters $f): DashboardData
    {
        $measure = $this->measure();
        $year = $f->classificationYear;
        $activeSector = $f->sector ?? ($f->project && $year && ! $f->unclassified ? $f->project->sectorIn($year) : null);

        $all = $this->officeRows($f, $measure);
        $selected = $f->office?->slug;
        $comparison = array_map(fn (OfficeFigures $row) => $row->withSelected($row->slug === $selected), $all);
        $offices = $selected === null ? $all : array_values(array_filter($all, fn (OfficeFigures $row) => $row->slug === $selected));

        $mainActivities = $f->project ? $this->mainActivities($f, $measure) : [];
        $subActivities = $f->mainActivity ? $this->subActivities($f, $measure) : [];

        return new DashboardData(
            filters: $f,
            measure: $measure,
            offices: $offices,
            comparison: $comparison,
            sectors: $this->sectorSummaries($f, $measure, $year),
            unclassified: $this->figures($this->records($f->without('sector', 'project', 'main', 'sub'), $measure)->whereNull('psa.sector_id')),
            projects: $this->projectSummaries($f, $measure, $year, $activeSector),
            periods: $this->periodRows($f, $measure),
            activeSector: $activeSector,
            classifiedProjects: $this->classifiedCount($f, $year, $activeSector),
            totals: $this->figures($this->records($f, $measure)),
            mainActivities: $mainActivities,
            withoutMainActivity: $f->project && ! $f->mainActivity && $mainActivities !== []
                ? $this->figures($this->records($f, $measure)->whereNull('activity_records.main_activity_id')) : null,
            subActivities: $subActivities,
            withoutSubActivity: $f->mainActivity && ! $f->subActivity && $subActivities !== []
                ? $this->figures($this->records($f, $measure)->whereNull('activity_records.sub_activity_id')) : null,
            categories: $f->project && ! $f->mainActivity ? $this->categories($f, $measure) : [],
            otherMeasures: $this->otherMeasures($f),
            sources: $this->sources($f, $measure),
        );
    }

    public function measure(string $code = Measure::REGISTERED_BENEFITS): Measure
    {
        return Measure::where('code', $code)->firstOrFail();
    }

    /** Latest reference year that has a classification for the institution. */
    public static function latestClassificationYear(int $institutionId): ?int
    {
        $year = ProjectSectorAssignment::where('institution_id', $institutionId)->max('reference_year');

        return $year === null ? null : (int) $year;
    }

    /**
     * Active records of the institution + measure with their period and year-matched sector
     * assignment, narrowed by the filters. From the project level down the sector filter is not
     * applied (the project's own records, all years).
     *
     * @return Builder<ActivityRecord>
     */
    public function records(DashboardFilters $f, Measure $measure, bool $office = true, bool $period = true): Builder
    {
        return ActivityRecord::query()
            ->join('periods', function (JoinClause $join) {
                $join->on('periods.id', '=', 'activity_records.period_id')
                    ->on('periods.institution_id', '=', 'activity_records.institution_id');
            })
            ->leftJoin('project_sector_assignments as psa', function (JoinClause $join) {
                $join->on('psa.project_id', '=', 'activity_records.project_id')
                    ->on('psa.institution_id', '=', 'activity_records.institution_id')
                    ->on('psa.reference_year', '=', 'periods.year');
            })
            ->where('activity_records.institution_id', $f->institution->id)
            ->where('activity_records.measure_id', $measure->id)
            ->where('activity_records.is_active', true)
            ->when($period && $f->period, fn (Builder $q) => $q->where('activity_records.period_id', $f->period->id))
            ->when(! $f->project && $f->sector, fn (Builder $q) => $q->where('psa.sector_id', $f->sector->id))
            ->when(! $f->project && $f->unclassified, fn (Builder $q) => $q->whereNull('psa.sector_id'))
            ->when($f->project, fn (Builder $q) => $q->where('activity_records.project_id', $f->project->id))
            ->when($f->mainActivity, fn (Builder $q) => $q->where('activity_records.main_activity_id', $f->mainActivity->id))
            ->when($f->subActivity, fn (Builder $q) => $q->where('activity_records.sub_activity_id', $f->subActivity->id))
            ->when($office && $f->office, fn (Builder $q) => $q->where('activity_records.office_id', $f->office->id));
    }

    /** Totals of a query, or null when it matches no record (absence is not zero). */
    private function figures(Builder $query): ?Figures
    {
        $row = $query->selectRaw(self::AGGREGATES)->toBase()->first();

        return $row === null || (int) $row->records === 0 ? null : Figures::fromRow($row);
    }

    /** @return Collection<int|string, Figures> grouped figures keyed by $column */
    private function grouped(Builder $query, string $column): Collection
    {
        return $query->groupBy($column)
            ->selectRaw("{$column} AS group_key, ".self::AGGREGATES)
            ->toBase()->get()
            ->mapWithKeys(fn ($row) => [(string) $row->group_key => Figures::fromRow($row)]);
    }

    /** @return list<OfficeFigures> ranked by total, highest first. */
    private function officeRows(DashboardFilters $f, Measure $measure): array
    {
        return $this->records($f, $measure, office: false)
            ->join('offices', function (JoinClause $join) {
                $join->on('offices.id', '=', 'activity_records.office_id')
                    ->on('offices.institution_id', '=', 'activity_records.institution_id');
            })
            ->groupBy('offices.id', 'offices.slug', 'offices.name')
            ->selectRaw('offices.slug, offices.name, SUM(activity_records.total_count) AS total, '
                .'SUM(COALESCE(activity_records.male_count, 0)) AS male, SUM(COALESCE(activity_records.female_count, 0)) AS female')
            ->toBase()->get()
            ->map(fn ($row) => new OfficeFigures($row->slug, $row->name, (int) $row->total, (int) $row->male, (int) $row->female))
            ->sort(fn (OfficeFigures $a, OfficeFigures $b) => [$b->total, $a->name] <=> [$a->total, $b->name])
            ->values()->all();
    }

    /** @return list<array{sector: Sector, classified: int, figures: ?Figures}> */
    private function sectorSummaries(DashboardFilters $f, Measure $measure, ?int $year): array
    {
        $figures = $this->grouped($this->records($f->without('sector', 'project', 'main', 'sub'), $measure)->whereNotNull('psa.sector_id'), 'psa.sector_id');
        $classified = $year === null ? collect() : ProjectSectorAssignment::query()
            ->where('institution_id', $f->institution->id)->where('reference_year', $year)
            ->groupBy('sector_id')->selectRaw('sector_id, COUNT(*) AS projects')->pluck('projects', 'sector_id');

        return Sector::where('institution_id', $f->institution->id)->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (Sector $sector) => [
                'sector' => $sector,
                'classified' => (int) ($classified[$sector->id] ?? 0),
                'figures' => $figures[(string) $sector->id] ?? null,
            ])->all();
    }

    /**
     * Projects of the active sector, of the unclassified bucket, or (overview) every classified
     * project plus unclassified projects with records. Figures follow the sector scope.
     *
     * @return list<array{project: Project, sector: ?Sector, figures: ?Figures}>
     */
    private function projectSummaries(DashboardFilters $f, Measure $measure, ?int $year, ?Sector $activeSector): array
    {
        $scope = new DashboardFilters($f->institution, $activeSector, null, $f->period, $f->office, $year, $f->unclassified && ! $activeSector);
        $figures = $this->grouped($this->records($scope, $measure), 'activity_records.project_id');

        $assignments = $year === null ? collect() : ProjectSectorAssignment::with('sector')
            ->where('institution_id', $f->institution->id)->where('reference_year', $year)
            ->when($activeSector, fn ($q) => $q->where('sector_id', $activeSector->id))
            ->get()->keyBy('project_id');

        $ids = $scope->unclassified
            ? $figures->keys()->all()
            : $assignments->keys()->merge($figures->keys())->unique()->all();

        return Project::where('institution_id', $f->institution->id)->whereIn('id', $ids)->get()
            ->map(fn (Project $project) => [
                'project' => $project,
                'sector' => $scope->unclassified ? null : ($assignments[$project->id]->sector ?? null),
                'figures' => $figures[(string) $project->id] ?? null,
            ])
            ->sort(fn ($a, $b) => [$b['figures']?->total ?? -1, $a['project']->name] <=> [$a['figures']?->total ?? -1, $b['project']->name])
            ->values()->all();
    }

    /** Every period of the scope (period filter ignored, so the selection can be compared). */
    private function periodRows(DashboardFilters $f, Measure $measure): array
    {
        $figures = $this->grouped($this->records($f, $measure, period: false), 'activity_records.period_id');

        return Period::whereIn('id', $figures->keys()->map(fn ($k) => (int) $k))->orderBy('year')->orderBy('month')->get()
            ->map(fn (Period $period) => ['period' => $period, 'figures' => $figures[(string) $period->id]])
            ->all();
    }

    /** Level 2 of the project, each with its figures (null = no records in the current filters). */
    private function mainActivities(DashboardFilters $f, Measure $measure): array
    {
        $figures = $this->grouped($this->records($f->without('main'), $measure)->whereNotNull('activity_records.main_activity_id'), 'activity_records.main_activity_id');

        return MainActivity::with('category')->withCount('subActivities')
            ->where('project_id', $f->project->id)->get()
            ->map(fn (MainActivity $a) => ['activity' => $a, 'figures' => $figures[(string) $a->id] ?? null, 'subs' => (int) $a->sub_activities_count])
            ->sort(fn ($a, $b) => [$b['figures']?->total ?? -1, $a['activity']->name] <=> [$a['figures']?->total ?? -1, $b['activity']->name])
            ->values()->all();
    }

    /** Level 3 of the selected main activity. */
    private function subActivities(DashboardFilters $f, Measure $measure): array
    {
        $figures = $this->grouped($this->records($f->without('sub'), $measure)->whereNotNull('activity_records.sub_activity_id'), 'activity_records.sub_activity_id');

        return SubActivity::where('main_activity_id', $f->mainActivity->id)->get()
            ->map(fn (SubActivity $a) => ['activity' => $a, 'figures' => $figures[(string) $a->id] ?? null])
            ->sort(fn ($a, $b) => [$b['figures']?->total ?? -1, $a['activity']->name] <=> [$a['figures']?->total ?? -1, $b['activity']->name])
            ->values()->all();
    }

    /** Level 1 (the project sheet's own grouping) — shown as source categories, never as activities. */
    private function categories(DashboardFilters $f, Measure $measure): array
    {
        $figures = $this->grouped($this->records($f, $measure)->whereNotNull('activity_records.category_id'), 'activity_records.category_id');

        return ProjectCategory::where('project_id', $f->project->id)->get()
            ->map(fn (ProjectCategory $c) => ['category' => $c, 'figures' => $figures[(string) $c->id] ?? null])
            ->filter(fn ($row) => $row['figures'] !== null)
            ->sort(fn ($a, $b) => $b['figures']->total <=> $a['figures']->total)
            ->values()->all();
    }

    /** Other measures in the same scope (e.g. families served). Never added to registered benefits. */
    private function otherMeasures(DashboardFilters $f): array
    {
        $out = [];
        foreach (Measure::where('code', '!=', Measure::REGISTERED_BENEFITS)->orderBy('id')->get() as $measure) {
            $row = $this->records($f, $measure)
                ->selectRaw('SUM(activity_records.total_count) AS total, SUM(activity_records.items_count) AS items, COUNT(*) AS records')
                ->toBase()->first();
            if ($row && (int) $row->records > 0) {
                $out[] = ['measure' => $measure, 'total' => (int) $row->total, 'items' => $row->items === null ? null : (int) $row->items, 'records' => (int) $row->records];
            }
        }

        return $out;
    }

    private function classifiedCount(DashboardFilters $f, ?int $year, ?Sector $activeSector): int
    {
        if ($year === null || ($f->unclassified && ! $activeSector)) {
            return 0;
        }

        return ProjectSectorAssignment::where('institution_id', $f->institution->id)->where('reference_year', $year)
            ->when($activeSector, fn ($q) => $q->where('sector_id', $activeSector->id))
            ->count();
    }

    /** @return Collection<int, DataSource> */
    private function sources(DashboardFilters $f, Measure $measure): Collection
    {
        $ids = $this->records($f, $measure)->whereNotNull('activity_records.data_source_id')
            ->distinct()->pluck('activity_records.data_source_id');

        return DataSource::where('institution_id', $f->institution->id)->whereIn('id', $ids)->get();
    }
}
