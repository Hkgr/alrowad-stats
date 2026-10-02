<?php

namespace App\Services\Dashboard;

use App\Models\Office;
use App\Models\Period;
use App\Models\Project;
use App\Models\ProjectSectorAssignment;
use App\Models\Sector;
use Illuminate\Support\Collection;

/**
 * Filter choices that exist in the database.
 *
 * - sectors and projects: the classification catalog (every classified project is searchable,
 *   with or without figures), plus unclassified projects that have records.
 * - periods and offices: only values that have records in the chosen sector/project scope.
 */
class FilterOptionsService
{
    public function __construct(private readonly BeneficiaryDashboardService $dashboard) {}

    /**
     * @return array{
     *     sectors: Collection<int, array{sector: Sector, classified: int}>,
     *     projects: Collection<int, array{project: Project, sector: ?Sector, has_data: bool}>,
     *     periods: Collection<int, Period>,
     *     offices: Collection<int, Office>,
     *     classification_year: ?int
     * }
     */
    public function for(DashboardFilters $filters): array
    {
        $measure = $this->dashboard->measure();
        $institutionId = $filters->institution->id;
        $year = $filters->classificationYear;

        $assignments = $year === null ? collect() : ProjectSectorAssignment::with('sector')
            ->where('institution_id', $institutionId)
            ->where('reference_year', $year)
            ->get()
            ->keyBy('project_id');

        // Projects with records inside the period filter only (not narrowed by sector/project/office).
        $withData = $this->dashboard
            ->records(new DashboardFilters($filters->institution, period: $filters->period, classificationYear: $year), $measure)
            ->distinct()
            ->pluck('beneficiary_records.project_id')
            ->flip();

        $projects = Project::where('institution_id', $institutionId)
            ->whereIn('id', $assignments->keys()->merge($withData->keys())->unique())
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project) => [
                'project' => $project,
                'sector' => $assignments[$project->id]->sector ?? null,
                'has_data' => $withData->has($project->id),
            ]);

        $classified = $assignments->countBy(fn ($a) => $a->sector_id);
        $sectors = Sector::where('institution_id', $institutionId)
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->map(fn (Sector $sector) => ['sector' => $sector, 'classified' => (int) ($classified[$sector->id] ?? 0)]);

        // Periods follow the sector/project choice; offices also follow the period.
        $periodScope = new DashboardFilters($filters->institution, $filters->sector, $filters->project, classificationYear: $year);
        $periodIds = $this->dashboard->records($periodScope, $measure)->distinct()->pluck('beneficiary_records.period_id');
        $officeIds = $this->dashboard->records($filters, $measure, office: false)->distinct()->pluck('beneficiary_records.office_id');

        return [
            'sectors' => $sectors,
            'projects' => $projects,
            'periods' => Period::whereIn('id', $periodIds)->orderBy('year')->orderBy('month')->get(),
            'offices' => Office::where('institution_id', $institutionId)->whereIn('id', $officeIds)->orderBy('sort_order')->orderBy('name')->get(),
            'classification_year' => $year,
        ];
    }
}
