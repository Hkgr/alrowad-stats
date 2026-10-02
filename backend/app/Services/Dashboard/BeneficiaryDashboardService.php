<?php

namespace App\Services\Dashboard;

use App\Models\BeneficiaryRecord;
use App\Models\DataSource;
use App\Models\Measure;
use App\Models\Period;
use App\Models\Project;
use App\Models\ProjectSectorAssignment;
use App\Models\Sector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

/**
 * Single source of truth for the dashboard numbers.
 *
 * Every query is pinned to one institution and one measure; filters narrow it further.
 * A record belongs to a sector through the project's assignment for the year of the record's
 * own period, so a 2026 classification never re-labels 2025 records.
 *
 * Aggregation follows the measure definition (sum). Offices and projects are counted DISTINCT.
 * Figures of different measures are never combined.
 */
class BeneficiaryDashboardService
{
    private const AGGREGATES = 'SUM(beneficiary_records.male_count) AS male, SUM(beneficiary_records.female_count) AS female, '
        .'COUNT(DISTINCT beneficiary_records.project_id) AS projects, COUNT(DISTINCT beneficiary_records.office_id) AS offices';

    public function build(DashboardFilters $filters): DashboardData
    {
        $measure = $this->measure();
        $year = $filters->classificationYear;
        $activeSector = $filters->sector ?? ($filters->project && $year ? $filters->project->sectorIn($year) : null);

        // Offices: all filters except the office one, which is applied in memory so that
        // "comparison" (for charts) and "offices" (for cards/table) share the same rows.
        $all = $this->officeRows($filters, $measure);
        $selected = $filters->office?->slug;
        $comparison = array_map(fn (OfficeFigures $row) => $row->withSelected($row->slug === $selected), $all);
        $offices = $selected === null ? $all : array_values(array_filter($all, fn (OfficeFigures $row) => $row->slug === $selected));

        $totals = $this->figures($this->records($filters, $measure));

        return new DashboardData(
            filters: $filters,
            measure: $measure,
            offices: $offices,
            comparison: $comparison,
            sectors: $this->sectorSummaries($filters, $measure, $year),
            unclassified: $this->figures(
                $this->records($filters, $measure, sector: false, project: false)->whereNull('psa.sector_id'),
            ),
            projects: $this->projectSummaries($filters, $measure, $year, $activeSector),
            periods: $this->periodRows($filters, $measure),
            activeSector: $activeSector,
            classifiedProjects: $this->classifiedCount($filters, $year, $activeSector),
            totals: $totals,
            sources: $this->sources($filters, $measure),
        );
    }

    public function measure(): Measure
    {
        return Measure::where('code', Measure::REGISTERED_BENEFITS)->firstOrFail();
    }

    /** Latest reference year that has a classification for the institution. */
    public static function latestClassificationYear(int $institutionId): ?int
    {
        $year = ProjectSectorAssignment::where('institution_id', $institutionId)->max('reference_year');

        return $year === null ? null : (int) $year;
    }

    /**
     * Records of the institution + measure with their period and year-matched sector assignment.
     * Each filter can be switched off so sibling views (all sectors, all projects...) reuse it.
     *
     * @return Builder<BeneficiaryRecord>
     */
    public function records(DashboardFilters $f, Measure $measure, bool $sector = true, bool $project = true, bool $office = true): Builder
    {
        return BeneficiaryRecord::query()
            ->join('periods', function (JoinClause $join) {
                $join->on('periods.id', '=', 'beneficiary_records.period_id')
                    ->on('periods.institution_id', '=', 'beneficiary_records.institution_id');
            })
            ->leftJoin('project_sector_assignments as psa', function (JoinClause $join) {
                $join->on('psa.project_id', '=', 'beneficiary_records.project_id')
                    ->on('psa.institution_id', '=', 'beneficiary_records.institution_id')
                    ->on('psa.reference_year', '=', 'periods.year');
            })
            ->where('beneficiary_records.institution_id', $f->institution->id)
            ->where('beneficiary_records.measure_id', $measure->id)
            ->when($f->period, fn (Builder $q) => $q->where('beneficiary_records.period_id', $f->period->id))
            ->when($sector && $f->sector, fn (Builder $q) => $q->where('psa.sector_id', $f->sector->id))
            ->when($project && $f->project, fn (Builder $q) => $q->where('beneficiary_records.project_id', $f->project->id))
            ->when($office && $f->office, fn (Builder $q) => $q->where('beneficiary_records.office_id', $f->office->id));
    }

    /** Totals of a query, or null when it matches no record (absence is not zero). */
    private function figures(Builder $query): ?Figures
    {
        $row = $query->selectRaw('COUNT(*) AS records, '.self::AGGREGATES)->toBase()->first();

        return $row === null || (int) $row->records === 0 ? null : Figures::fromRow($row);
    }

    /** @return list<OfficeFigures> ranked by total, highest first. */
    private function officeRows(DashboardFilters $f, Measure $measure): array
    {
        return $this->records($f, $measure, office: false)
            ->join('offices', function (JoinClause $join) {
                $join->on('offices.id', '=', 'beneficiary_records.office_id')
                    ->on('offices.institution_id', '=', 'beneficiary_records.institution_id');
            })
            ->groupBy('offices.id', 'offices.slug', 'offices.name')
            ->selectRaw('offices.slug, offices.name, SUM(beneficiary_records.male_count) AS male, SUM(beneficiary_records.female_count) AS female')
            ->toBase()
            ->get()
            ->map(fn ($row) => new OfficeFigures($row->slug, $row->name, (int) $row->male, (int) $row->female))
            ->sort(fn (OfficeFigures $a, OfficeFigures $b) => [$b->total(), $a->name] <=> [$a->total(), $b->name])
            ->values()
            ->all();
    }

    /**
     * Every sector of the institution with its classified-project count and its figures
     * (period and office filters apply; sector and project filters do not, so cards can compare).
     *
     * @return list<array{sector: Sector, classified: int, figures: ?Figures}>
     */
    private function sectorSummaries(DashboardFilters $f, Measure $measure, ?int $year): array
    {
        $figures = $this->records($f, $measure, sector: false, project: false)
            ->whereNotNull('psa.sector_id')
            ->groupBy('psa.sector_id')
            ->selectRaw('psa.sector_id, '.self::AGGREGATES)
            ->toBase()
            ->get()
            ->keyBy('sector_id');

        $classified = $year === null ? collect() : ProjectSectorAssignment::query()
            ->where('institution_id', $f->institution->id)
            ->where('reference_year', $year)
            ->groupBy('sector_id')
            ->selectRaw('sector_id, COUNT(*) AS projects')
            ->pluck('projects', 'sector_id');

        return Sector::where('institution_id', $f->institution->id)
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->map(fn (Sector $sector) => [
                'sector' => $sector,
                'classified' => (int) ($classified[$sector->id] ?? 0),
                'figures' => isset($figures[$sector->id]) ? Figures::fromRow($figures[$sector->id]) : null,
            ])
            ->all();
    }

    /**
     * Projects of the active sector (or every classified project, plus unclassified projects
     * that have data) with their figures. Period, office and sector filters apply.
     *
     * @return list<array{project: Project, sector: ?Sector, figures: ?Figures}>
     */
    private function projectSummaries(DashboardFilters $f, Measure $measure, ?int $year, ?Sector $activeSector): array
    {
        $scoped = new DashboardFilters($f->institution, $activeSector, null, $f->period, $f->office, $year);
        $figures = $this->records($scoped, $measure, project: false)
            ->groupBy('beneficiary_records.project_id')
            ->selectRaw('beneficiary_records.project_id, '.self::AGGREGATES)
            ->toBase()
            ->get()
            ->keyBy('project_id');

        $assignments = $year === null ? collect() : ProjectSectorAssignment::with('sector')
            ->where('institution_id', $f->institution->id)
            ->where('reference_year', $year)
            ->when($activeSector, fn ($q) => $q->where('sector_id', $activeSector->id))
            ->get()
            ->keyBy('project_id');

        $ids = $assignments->keys()->merge($figures->keys())->unique()->all();

        return Project::where('institution_id', $f->institution->id)
            ->whereIn('id', $ids)
            ->get()
            ->map(fn (Project $project) => [
                'project' => $project,
                'sector' => $assignments[$project->id]->sector ?? null,
                'figures' => isset($figures[$project->id]) ? Figures::fromRow($figures[$project->id]) : null,
            ])
            ->sort(fn ($a, $b) => [$b['figures']?->total() ?? -1, $a['project']->name] <=> [$a['figures']?->total() ?? -1, $b['project']->name])
            ->values()
            ->all();
    }

    /** @return list<array{period: Period, figures: Figures}> */
    private function periodRows(DashboardFilters $f, Measure $measure): array
    {
        $rows = $this->records($f, $measure)
            ->groupBy('beneficiary_records.period_id')
            ->selectRaw('beneficiary_records.period_id, '.self::AGGREGATES)
            ->toBase()
            ->get()
            ->keyBy('period_id');

        return Period::whereIn('id', $rows->keys())
            ->orderBy('year')->orderBy('month')
            ->get()
            ->map(fn (Period $period) => ['period' => $period, 'figures' => Figures::fromRow($rows[$period->id])])
            ->all();
    }

    private function classifiedCount(DashboardFilters $f, ?int $year, ?Sector $activeSector): int
    {
        if ($year === null) {
            return 0;
        }

        return ProjectSectorAssignment::where('institution_id', $f->institution->id)
            ->where('reference_year', $year)
            ->when($activeSector, fn ($q) => $q->where('sector_id', $activeSector->id))
            ->count();
    }

    /** @return Collection<int, DataSource> */
    private function sources(DashboardFilters $f, Measure $measure): Collection
    {
        $ids = $this->records($f, $measure)
            ->whereNotNull('beneficiary_records.data_source_id')
            ->distinct()
            ->pluck('beneficiary_records.data_source_id');

        return DataSource::where('institution_id', $f->institution->id)->whereIn('id', $ids)->get();
    }
}
