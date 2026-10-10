<?php

namespace App\Services\Dashboard;

use App\Models\Measure;
use App\Models\Period;
use App\Models\Project;
use App\Models\Sector;
use Illuminate\Database\Query\JoinClause;

/**
 * Deeper readings of the same filtered records the dashboard uses (served by /analytics so the
 * main dashboard response stays small).
 *
 * Every figure follows the gender / age / special-needs slice of the filters, and the same rule
 * as the dashboard: a blank cell is "not reported", never zero, and a slice with no records is
 * null rather than an empty chart.
 */
class AnalyticsService
{
    /** Projects drawn separately in the monthly stack of a sector; the rest are grouped. */
    private const STACK_PROJECTS = 8;

    /** Specialties listed individually; the rest are grouped. */
    private const TOP_SPECIALTIES = 12;

    public function __construct(private readonly BeneficiaryDashboardService $dashboard) {}

    /** @return array<string, mixed> */
    public function build(DashboardFilters $f): array
    {
        $measure = $this->dashboard->measure();
        $slice = $this->dashboard->sliceFor($f);
        $listing = ! $f->project;

        return [
            'heatmap' => $listing ? $this->heatmap($f, $measure, $slice) : null,
            'monthly_stack' => $listing ? $this->monthlyStack($f, $measure, $slice) : null,
            'pyramid' => $this->pyramid($f, $measure, $slice),
            'courses' => $this->courses($f, $measure, $slice),
            'knowledge' => $this->knowledge($f, $measure, $slice),
        ];
    }

    /**
     * Project × office matrix of the scope (the office filter is ignored so the whole picture shows).
     * Cells that have no record are absent — not zero.
     *
     * @param  array{total: string}  $slice
     * @return array{offices: list<array<string, mixed>>, rows: list<array<string, mixed>>}|null
     */
    private function heatmap(DashboardFilters $f, Measure $measure, array $slice): ?array
    {
        $rows = $this->dashboard->records($f, $measure, office: false)
            ->join('projects', fn (JoinClause $j) => $j->on('projects.id', '=', 'activity_records.project_id'))
            ->join('offices', fn (JoinClause $j) => $j->on('offices.id', '=', 'activity_records.office_id'))
            ->groupBy('projects.id', 'projects.slug', 'projects.name', 'offices.id', 'offices.slug', 'offices.name', 'offices.sort_order')
            ->selectRaw("projects.id AS project_id, projects.slug AS project_slug, projects.name AS project_name, offices.slug AS office_slug, offices.name AS office_name, offices.sort_order, SUM({$slice['total']}) AS total")
            ->toBase()->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $offices = $rows->groupBy('office_slug')->map(fn ($g) => [
            'slug' => $g[0]->office_slug, 'name' => $g[0]->office_name, 'total' => (int) $g->sum('total'), 'order' => (int) $g[0]->sort_order,
        ])->sort(fn ($a, $b) => [$b['total'], $a['order'], $a['name']] <=> [$a['total'], $b['order'], $b['name']])->values();

        $projects = $rows->groupBy('project_id')->map(function ($g) {
            $total = (int) $g->sum('total');

            return [
                'slug' => $g[0]->project_slug,
                'name' => $g[0]->project_name,
                'total' => $total,
                'cells' => $g->mapWithKeys(fn ($r) => [$r->office_slug => [
                    'value' => (int) $r->total,
                    'share' => $total > 0 ? round($r->total / $total * 100, 1) : null,
                ]])->all(),
            ];
        })->sort(fn ($a, $b) => [$b['total'], $a['name']] <=> [$a['total'], $b['name']])->values();

        return [
            'offices' => $offices->map(fn ($o) => ['slug' => $o['slug'], 'name' => $o['name'], 'total' => $o['total']])->all(),
            'rows' => $projects->all(),
        ];
    }

    /**
     * Months of the scope split by track (overview) or by project (a track). The period filter is
     * ignored so every month stays visible; a month with no record of a series is null.
     *
     * @param  array{total: string}  $slice
     * @return array<string, mixed>|null
     */
    private function monthlyStack(DashboardFilters $f, Measure $measure, array $slice): ?array
    {
        $bySector = ! $f->sector && ! $f->unclassified;
        $column = $bySector ? 'psa.sector_id' : 'activity_records.project_id';

        $rows = $this->dashboard->records($f, $measure, period: false)
            ->groupBy('activity_records.period_id', $column)
            ->selectRaw("activity_records.period_id AS period_id, {$column} AS group_key, SUM({$slice['total']}) AS total")
            ->toBase()->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $periods = Period::whereIn('id', $rows->pluck('period_id')->unique())->orderBy('year')->orderBy('month')->get();

        if ($bySector) {
            $names = Sector::where('institution_id', $f->institution->id)->get()->keyBy('id');
            $meta = fn ($key) => $key === null || ! isset($names[(int) $key])
                ? ['key' => 'unclassified', 'name' => 'غير مصنف', 'code' => null]
                : ['key' => $names[(int) $key]->slug, 'name' => $names[(int) $key]->name, 'code' => $names[(int) $key]->code];
        } else {
            $names = Project::whereIn('id', $rows->pluck('group_key')->unique())->get()->keyBy('id');
            $meta = fn ($key) => ['key' => $names[(int) $key]->slug, 'name' => $names[(int) $key]->name, 'code' => null];
        }

        $series = $rows->groupBy(fn ($r) => $r->group_key === null ? 'null' : (string) $r->group_key)->map(function ($g, $key) use ($meta, $periods) {
            $byPeriod = $g->pluck('total', 'period_id');

            return $meta($key === 'null' ? null : $key) + [
                'total' => (int) $g->sum('total'),
                'values' => $periods->map(fn (Period $p) => isset($byPeriod[$p->id]) ? (int) $byPeriod[$p->id] : null)->all(),
            ];
        })->sortByDesc('total')->values();

        if (! $bySector && $series->count() > self::STACK_PROJECTS + 1) {
            $rest = $series->slice(self::STACK_PROJECTS);
            $series = $series->take(self::STACK_PROJECTS)->push([
                'key' => 'other', 'name' => 'مشاريع أخرى', 'code' => null, 'total' => $rest->sum('total'),
                'values' => $periods->keys()->map(function ($i) use ($rest) {
                    $present = $rest->pluck("values.$i")->filter(fn ($v) => $v !== null);

                    return $present->isEmpty() ? null : (int) $present->sum();
                })->all(),
            ]);
        }

        return [
            'group' => $bySector ? 'sector' : 'project',
            'periods' => $periods->map(fn (Period $p) => ['key' => $p->key(), 'label' => $p->label()])->all(),
            'series' => $series->all(),
        ];
    }

    /**
     * Under-18 / adult by gender. What the source leaves out stays "not reported" (never spread out);
     * not offered when the age or special-needs filter already picks one slice.
     *
     * @param  array{total: string}  $slice
     * @return array<string, mixed>|null
     */
    private function pyramid(DashboardFilters $f, Measure $measure, array $slice): ?array
    {
        if ($f->age !== null || $f->disabled) {
            return null;
        }

        $cells = ['male_under_18', 'female_under_18', 'male_adult', 'female_adult'];
        $reported = implode(' OR ', array_map(fn ($c) => "activity_records.{$c} IS NOT NULL", $cells));
        $sums = implode(', ', array_map(fn ($c) => "SUM(COALESCE(activity_records.{$c}, 0)) AS {$c}", $cells));

        $row = $this->dashboard->records($f, $measure)
            ->selectRaw("{$sums}, SUM({$slice['total']}) AS total, SUM(CASE WHEN {$reported} THEN 1 ELSE 0 END) AS with_age")
            ->toBase()->first();

        if ($row === null || (int) $row->with_age === 0) {
            return null;
        }

        $genders = $f->gender === null ? ['male', 'female'] : [$f->gender];
        $groups = [];
        $known = 0;
        foreach (['under_18', 'adult'] as $age) {
            foreach (['male', 'female'] as $gender) {
                $value = in_array($gender, $genders, true) ? (int) $row->{"{$gender}_{$age}"} : 0;
                $groups[$age][$gender] = $value;
                $known += $value;
            }
        }
        $total = (int) $row->total;

        return [
            'under_18' => $groups['under_18'],
            'adult' => $groups['adult'],
            'genders' => $genders,
            'total' => $total,
            'unreported' => max(0, $total - $known),
            'coverage' => $total > 0 ? round($known / $total * 100, 1) : null,
        ];
    }

    /**
     * Courses of the scope. The course number is a series within a project (the same number runs
     * in several offices and months), so a course is one distinct (project, number). Beneficiaries
     * are registered benefits — a person attending in several months is counted in each — so every
     * average here is "registered benefits per course", never "people per course".
     *
     * @param  array{total: string}  $slice
     * @return array<string, mixed>|null
     */
    private function courses(DashboardFilters $f, Measure $measure, array $slice): ?array
    {
        $sectioned = fn (string $expr) => "SUM(CASE WHEN activity_records.sections_count > 0 THEN {$expr} ELSE 0 END)";

        $rows = $this->dashboard->records($f, $measure)
            ->whereNotNull('activity_records.course_number')
            ->join('projects', fn (JoinClause $j) => $j->on('projects.id', '=', 'activity_records.project_id'))
            ->groupBy('projects.id', 'projects.slug', 'projects.name', 'activity_records.course_number')
            ->selectRaw('projects.slug AS project_slug, projects.name AS project_name, activity_records.course_number AS course_number, '
                .'COUNT(DISTINCT activity_records.office_id) AS offices, COUNT(DISTINCT activity_records.period_id) AS months, COUNT(*) AS records, '
                ."SUM({$slice['total']}) AS total, {$sectioned('activity_records.sections_count')} AS sections, "
                ."{$sectioned($slice['total'])} AS sectioned_total, {$sectioned('1')} AS sectioned_records")
            ->toBase()->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $total = (int) $rows->sum('total');
        $count = $rows->count();
        $sections = (int) $rows->sum('sections');
        $sectionedRecords = (int) $rows->sum('sectioned_records');
        $scopeTotal = (int) ($this->dashboard->records($f, $measure)->selectRaw("SUM({$slice['total']}) AS total")->toBase()->value('total') ?? 0);

        return [
            'courses' => $count,
            'records' => (int) $rows->sum('records'),
            'total' => $total,
            'average_per_course' => $count > 0 ? (int) round($total / $count) : null,
            // Share of the scope's registered benefits that carry a course number.
            'coverage' => $scopeTotal > 0 ? round($total / $scopeTotal * 100, 1) : null,
            'sections' => $sections > 0 ? [
                'total' => $sections,
                'records' => $sectionedRecords,
                'average_per_record' => round($sections / $sectionedRecords, 1),
                'benefits_per_section' => round((int) $rows->sum('sectioned_total') / $sections, 1),
            ] : null,
            'by_project' => $f->project ? [] : $rows->groupBy('project_slug')->map(function ($g) {
                $t = (int) $g->sum('total');

                return [
                    'slug' => $g[0]->project_slug, 'name' => $g[0]->project_name, 'courses' => $g->count(),
                    'total' => $t, 'average_per_course' => (int) round($t / $g->count()),
                ];
            })->sortByDesc('total')->values()->all(),
            'list' => ! $f->project ? [] : $rows->sort(function ($a, $b) {
                return is_numeric($a->course_number) && is_numeric($b->course_number)
                    ? (int) $a->course_number <=> (int) $b->course_number
                    : strcmp((string) $a->course_number, (string) $b->course_number);
            })->map(fn ($r) => [
                'number' => (string) $r->course_number, 'offices' => (int) $r->offices, 'months' => (int) $r->months, 'total' => (int) $r->total,
            ])->values()->all(),
        ];
    }

    /**
     * Specialty and university of the records that carry them (رواد المعرفة). A record without one of
     * the two is counted under "not reported" for it.
     *
     * @param  array{total: string}  $slice
     * @return array<string, mixed>|null
     */
    private function knowledge(DashboardFilters $f, Measure $measure, array $slice): ?array
    {
        $rows = $this->dashboard->records($f, $measure)
            ->whereNotNull('activity_records.details')
            ->selectRaw("activity_records.details AS details, {$slice['total']} AS total")
            ->toBase()->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $tally = function (string $key) use ($rows) {
            $totals = [];
            $unreported = 0;
            foreach ($rows as $row) {
                $value = trim((string) (json_decode($row->details, true)[$key] ?? ''));
                if ($value === '') {
                    $unreported += (int) $row->total;
                } else {
                    $totals[$value] = ($totals[$value] ?? 0) + (int) $row->total;
                }
            }
            arsort($totals);

            return [$totals, $unreported];
        };

        [$specialties, $noSpecialty] = $tally('specialty');
        [$universities, $noUniversity] = $tally('university');
        $list = fn (array $totals) => array_map(fn ($name, $t) => ['name' => (string) $name, 'total' => $t], array_keys($totals), $totals);
        $top = array_slice($list($specialties), 0, self::TOP_SPECIALTIES);
        $rest = array_slice($specialties, self::TOP_SPECIALTIES, null, true);

        return [
            'records' => $rows->count(),
            'total' => (int) $rows->sum('total'),
            'specialties' => $top,
            'specialties_count' => count($specialties),
            'other_specialties' => $rest === [] ? null : ['count' => count($rest), 'total' => array_sum($rest)],
            'specialty_unreported' => $noSpecialty,
            'universities' => $list($universities),
            'university_unreported' => $noUniversity,
        ];
    }
}
