<?php

namespace App\Services\Organization;

use App\Models\Organization\OrgCenter;
use App\Models\Organization\OrgProgram;
use App\Models\Organization\OrgProject;
use App\Models\Organization\OrgRegion;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Organization figures for 2017–2024. Beneficiary counts always come from the center rows
 * (the same grain the importer checks against the project and region sheets). Special needs,
 * staff and offices are a 2024 snapshot and do not follow the year filter.
 */
class OrganizationDashboardService
{
    /** @param  array{program: ?string, project: ?string, region: ?string, center: ?string, year: ?int, from: ?int, to: ?int}  $filters */
    public function build(array $filters): array
    {
        $facts = $this->apply($this->base(), $filters);
        $total = (int) (clone $facts)->sum('facts.beneficiaries');

        $byYear = (clone $facts)
            ->selectRaw('facts.year as year, SUM(facts.beneficiaries) as beneficiaries')
            ->groupBy('facts.year')
            ->orderBy('facts.year')
            ->get()
            ->map(fn ($row) => [
                'year' => (int) $row->year,
                'beneficiaries' => (int) $row->beneficiaries,
                'selected' => $filters['year'] !== null && (int) $row->year === $filters['year'],
            ])
            ->all();

        $programs = $this->grouped($facts, 'programs.id, programs.slug, programs.name, programs.code, programs.sort_order', 'programs.sort_order', [
            'programs.slug as slug', 'programs.name as name', 'programs.code as code',
            'SUM(facts.beneficiaries) as beneficiaries', 'COUNT(DISTINCT projects.id) as projects',
        ], $total, $filters['program']);

        $projects = (clone $facts)
            ->selectRaw('projects.slug as slug, projects.name as name, programs.slug as program_slug, programs.name as program_name, projects.disabled_count as disabled, projects.staff_count as staff, SUM(facts.beneficiaries) as beneficiaries')
            ->groupBy('projects.id', 'projects.slug', 'projects.name', 'programs.slug', 'programs.name', 'projects.disabled_count', 'projects.staff_count', 'projects.sort_order')
            ->orderByDesc('beneficiaries')
            ->orderBy('projects.sort_order')
            ->get()
            ->map(fn ($row) => [
                'slug' => $row->slug,
                'name' => $row->name,
                'program_slug' => $row->program_slug,
                'program_name' => $row->program_name,
                'beneficiaries' => (int) $row->beneficiaries,
                'disabled' => (int) $row->disabled,
                'staff' => (int) $row->staff,
                'selected' => $filters['project'] === $row->slug,
            ])
            ->all();

        $regions = (clone $facts)
            ->selectRaw('regions.slug as slug, regions.name as name, regions.disabled_count as disabled, regions.staff_count as staff, regions.offices_count as offices, SUM(facts.beneficiaries) as beneficiaries, COUNT(DISTINCT centers.id) as centers')
            ->whereNotNull('regions.id')
            ->groupBy('regions.id', 'regions.slug', 'regions.name', 'regions.disabled_count', 'regions.staff_count', 'regions.offices_count', 'regions.sort_order')
            ->orderBy('regions.sort_order')
            ->get()
            ->map(fn ($row) => [
                'slug' => $row->slug,
                'name' => $row->name,
                'beneficiaries' => (int) $row->beneficiaries,
                'centers' => (int) $row->centers,
                'disabled' => (int) $row->disabled,
                'staff' => (int) $row->staff,
                'offices' => (int) $row->offices,
                'share' => $this->share((int) $row->beneficiaries, $total),
                'selected' => $filters['region'] === $row->slug,
            ])
            ->all();

        $centers = (clone $facts)
            ->selectRaw('centers.slug as slug, centers.name as name, regions.slug as region_slug, regions.name as region_name, SUM(facts.beneficiaries) as beneficiaries')
            ->groupBy('centers.id', 'centers.slug', 'centers.name', 'regions.slug', 'regions.name', 'centers.sort_order')
            ->orderByDesc('beneficiaries')
            ->orderBy('centers.sort_order')
            ->get()
            ->map(fn ($row) => [
                'slug' => $row->slug,
                'name' => $row->name,
                'region_slug' => $row->region_slug,
                'region_name' => $row->region_name,
                'beneficiaries' => (int) $row->beneficiaries,
                'selected' => $filters['center'] === $row->slug,
            ])
            ->all();

        $years = array_column($byYear, 'year');
        $span = $years === [] ? null : ($years[0] === $years[array_key_last($years)] ? (string) $years[0] : $years[0].' – '.$years[array_key_last($years)]);

        return [
            'scope' => [
                'program' => $this->ref(OrgProgram::query()->where('slug', $filters['program'])->first(['slug', 'name', 'code'])),
                'project' => $this->ref(OrgProject::query()->where('slug', $filters['project'])->first(['slug', 'name'])),
                'region' => $this->ref(OrgRegion::query()->where('slug', $filters['region'])->first(['slug', 'name'])),
                'center' => $this->ref(OrgCenter::query()->where('slug', $filters['center'])->first(['slug', 'name'])),
                'year' => $filters['year'],
                'from' => $filters['from'],
                'to' => $filters['to'],
            ],
            'summary' => [
                'beneficiaries' => $total,
                'programs' => $this->distinct($facts, 'programs.id'),
                'projects' => $this->distinct($facts, 'projects.id'),
                'regions' => $this->distinct($facts, 'regions.id'),
                'centers' => $this->distinct($facts, 'centers.id'),
                'span' => $span,
            ],
            'snapshot' => $this->snapshot($filters),
            'by_year' => $byYear,
            'programs' => $programs,
            'projects' => $projects,
            'regions' => $regions,
            'centers' => $centers,
            'options' => $this->options($filters),
        ];
    }

    /** @param  array{program: ?string, project: ?string, region: ?string, center: ?string, year: ?int, from: ?int, to: ?int}  $filters */
    private function snapshot(array $filters): ?array
    {
        $programSide = $filters['program'] !== null || $filters['project'] !== null;
        $placeSide = $filters['region'] !== null || $filters['center'] !== null;
        if ($programSide && $placeSide) {
            return null;
        }

        if ($placeSide) {
            $row = OrgRegion::query()
                ->when($filters['region'], fn ($query) => $query->where('slug', $filters['region']))
                ->when($filters['center'], fn ($query) => $query->whereHas('centers', fn ($centers) => $centers->where('slug', $filters['center'])))
                ->selectRaw('COALESCE(SUM(disabled_count), 0) as disabled, COALESCE(SUM(staff_count), 0) as staff, COALESCE(SUM(offices_count), 0) as offices')
                ->first();

            return [
                'basis' => 'region',
                'disabled' => (int) $row->disabled,
                'staff' => (int) $row->staff,
                'offices' => (int) $row->offices,
            ];
        }

        $row = OrgProject::query()
            ->when($filters['program'], fn ($query) => $query->whereHas('program', fn ($program) => $program->where('slug', $filters['program'])))
            ->when($filters['project'], fn ($query) => $query->where('slug', $filters['project']))
            ->selectRaw('COALESCE(SUM(disabled_count), 0) as disabled, COALESCE(SUM(staff_count), 0) as staff')
            ->first();

        $offices = null;
        if (! $programSide) {
            $offices = (int) OrgRegion::query()->sum('offices_count');
        }

        return [
            'basis' => $programSide ? 'program' : 'overview',
            'disabled' => (int) $row->disabled,
            'staff' => (int) $row->staff,
            'offices' => $offices,
        ];
    }

    /** @param  array{program: ?string, project: ?string, region: ?string, center: ?string, year: ?int, from: ?int, to: ?int}  $filters */
    private function options(array $filters): array
    {
        return [
            'programs' => OrgProgram::query()->orderBy('sort_order')->get(['slug', 'name', 'code'])
                ->map(fn ($row) => ['slug' => $row->slug, 'name' => $row->name, 'code' => $row->code])->all(),
            'projects' => OrgProject::query()
                ->with('program:id,slug')
                ->when($filters['program'], fn ($query) => $query->whereHas('program', fn ($program) => $program->where('slug', $filters['program'])))
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($row) => ['slug' => $row->slug, 'name' => $row->name, 'program_slug' => $row->program->slug])->all(),
            'regions' => OrgRegion::query()->orderBy('sort_order')->get(['slug', 'name'])
                ->map(fn ($row) => ['slug' => $row->slug, 'name' => $row->name])->all(),
            'centers' => OrgCenter::query()
                ->with('region:id,slug')
                ->when($filters['region'], fn ($query) => $query->whereHas('region', fn ($region) => $region->where('slug', $filters['region'])))
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($row) => ['slug' => $row->slug, 'name' => $row->name, 'region_slug' => $row->region?->slug])->all(),
            'years' => DB::table('org_center_years')->distinct()->orderBy('year')->pluck('year')->map(fn ($year) => (int) $year)->all(),
        ];
    }

    private function base(): Builder
    {
        return DB::table('org_center_years as facts')
            ->join('org_projects as projects', 'projects.id', '=', 'facts.project_id')
            ->join('org_programs as programs', 'programs.id', '=', 'projects.program_id')
            ->join('org_centers as centers', 'centers.id', '=', 'facts.center_id')
            ->leftJoin('org_regions as regions', 'regions.id', '=', 'centers.region_id');
    }

    /** @param  array{program: ?string, project: ?string, region: ?string, center: ?string, year: ?int, from: ?int, to: ?int}  $filters */
    private function apply(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['program'], fn (Builder $q) => $q->where('programs.slug', $filters['program']))
            ->when($filters['project'], fn (Builder $q) => $q->where('projects.slug', $filters['project']))
            ->when($filters['region'], fn (Builder $q) => $q->where('regions.slug', $filters['region']))
            ->when($filters['center'], fn (Builder $q) => $q->where('centers.slug', $filters['center']))
            ->when($filters['year'], fn (Builder $q) => $q->where('facts.year', $filters['year']))
            ->when($filters['from'], fn (Builder $q) => $q->where('facts.year', '>=', $filters['from']))
            ->when($filters['to'], fn (Builder $q) => $q->where('facts.year', '<=', $filters['to']));
    }

    private function distinct(Builder $query, string $column): int
    {
        return (int) (clone $query)->selectRaw("count(distinct {$column}) as c")->value('c');
    }

    /**
     * @param  list<string>  $select
     * @return list<array<string, mixed>>
     */
    private function grouped(Builder $query, string $group, string $order, array $select, int $total, ?string $selected): array
    {
        return (clone $query)
            ->selectRaw(implode(', ', $select))
            ->groupByRaw($group)
            ->orderByRaw($order)
            ->get()
            ->map(fn ($row) => [
                'slug' => $row->slug,
                'code' => $row->code,
                'name' => $row->name,
                'beneficiaries' => (int) $row->beneficiaries,
                'projects' => (int) $row->projects,
                'share' => $this->share((int) $row->beneficiaries, $total),
                'selected' => $selected === $row->slug,
            ])
            ->all();
    }

    private function share(int $part, int $total): ?float
    {
        return $total === 0 ? null : round($part / $total * 100, 1);
    }

    private function ref(?object $row): ?array
    {
        if ($row === null) {
            return null;
        }

        $out = ['slug' => $row->slug, 'name' => $row->name];
        if (isset($row->code)) {
            $out['code'] = $row->code;
        }

        return $out;
    }
}
