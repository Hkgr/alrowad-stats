<?php

namespace App\Services\Dashboard;

use App\Models\Measure;
use App\Models\Office;
use App\Models\Period;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Filter choices that exist in the data. Periods and offices are narrowed by the project
 * (and period) already chosen, so the UI never offers a combination with nothing behind it.
 */
class FilterOptionsService
{
    public function __construct(private readonly BeneficiaryDashboardService $dashboard) {}

    /**
     * @return array{projects: Collection<int, Project>, periods: Collection<int, Period>, offices: Collection<int, Office>}
     */
    public function for(DashboardFilters $filters): array
    {
        $measureId = $this->dashboard->measure()->id;
        $institutionId = $filters->institution->id;

        $records = fn (Builder $q) => $q
            ->where('measure_id', $measureId)
            ->where('institution_id', $institutionId);

        return [
            'projects' => Project::query()
                ->where('institution_id', $institutionId)
                ->whereHas('beneficiaryRecords', $records)
                ->orderBy('name')
                ->get(),

            'periods' => Period::query()
                ->where('institution_id', $institutionId)
                ->whereHas('beneficiaryRecords', fn (Builder $q) => $records($q)
                    ->when($filters->project, fn (Builder $q, Project $p) => $q->where('project_id', $p->id)))
                ->orderBy('year')->orderBy('month')
                ->get(),

            'offices' => Office::query()
                ->where('institution_id', $institutionId)
                ->whereHas('beneficiaryRecords', fn (Builder $q) => $records($q)
                    ->when($filters->project, fn (Builder $q, Project $p) => $q->where('project_id', $p->id))
                    ->when($filters->period, fn (Builder $q, Period $p) => $q->where('period_id', $p->id)))
                ->orderBy('sort_order')->orderBy('name')
                ->get(),
        ];
    }

    public function measure(): Measure
    {
        return $this->dashboard->measure();
    }
}
