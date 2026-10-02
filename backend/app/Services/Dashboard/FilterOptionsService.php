<?php

namespace App\Services\Dashboard;

use App\Models\MainActivity;
use App\Models\Measure;
use App\Models\Office;
use App\Models\Period;
use App\Models\Project;
use App\Models\ProjectSectorAssignment;
use App\Models\Sector;
use App\Models\SubActivity;
use Illuminate\Support\Collection;

/**
 * Filter choices that exist in the database.
 *
 * - sectors and projects: the classification catalog (every classified project is searchable,
 *   with or without figures) plus projects that only have unclassified records.
 * - main activities: those of the selected project; sub activities: those of the selected main.
 * - periods and offices: only values that have records in the chosen scope.
 */
class FilterOptionsService
{
    public function __construct(private readonly BeneficiaryDashboardService $dashboard) {}

    /** @return array<string, mixed> */
    public function for(DashboardFilters $filters): array
    {
        $measure = $this->dashboard->measure();
        $institutionId = $filters->institution->id;
        $year = $filters->classificationYear;

        $assignments = $year === null ? collect() : ProjectSectorAssignment::with('sector')
            ->where('institution_id', $institutionId)->where('reference_year', $year)->get()->keyBy('project_id');

        // Projects with records of any measure inside the period filter (not narrowed further).
        $catalogScope = new DashboardFilters($filters->institution, period: $filters->period, classificationYear: $year);
        $withData = collect();
        foreach (Measure::all() as $m) {
            $withData = $withData->merge($this->dashboard->records($catalogScope, $m)->distinct()->pluck('activity_records.project_id'));
        }
        $withData = $withData->map(fn ($id) => (int) $id)->unique()->flip();

        $projects = Project::where('institution_id', $institutionId)
            ->whereIn('id', $assignments->keys()->merge($withData->keys())->unique())
            ->orderBy('name')->get()
            ->map(fn (Project $project) => [
                'project' => $project,
                'sector' => $assignments[$project->id]->sector ?? null,
                'has_data' => $withData->has($project->id),
            ]);

        $classified = $assignments->countBy(fn ($a) => $a->sector_id);
        $sectors = Sector::where('institution_id', $institutionId)->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (Sector $sector) => ['sector' => $sector, 'classified' => (int) ($classified[$sector->id] ?? 0)]);

        $mainActivities = $filters->project ? $this->activityOptions(
            MainActivity::with('category')->where('project_id', $filters->project->id)->orderBy('name')->get(),
            $this->dashboard->records($filters->without('main', 'office'), $measure)->distinct()->pluck('activity_records.main_activity_id'),
        ) : collect();

        $subActivities = $filters->mainActivity ? $this->activityOptions(
            SubActivity::where('main_activity_id', $filters->mainActivity->id)->orderBy('name')->get(),
            $this->dashboard->records($filters->without('sub', 'office'), $measure)->distinct()->pluck('activity_records.sub_activity_id'),
        ) : collect();

        $periodIds = $this->dashboard->records($filters->without('office'), $measure, period: false)->distinct()->pluck('activity_records.period_id');
        $officeIds = $this->dashboard->records($filters, $measure, office: false)->distinct()->pluck('activity_records.office_id');

        return [
            'sectors' => $sectors,
            'projects' => $projects,
            'main_activities' => $mainActivities,
            'sub_activities' => $subActivities,
            'periods' => Period::whereIn('id', $periodIds)->orderBy('year')->orderBy('month')->get(),
            'offices' => Office::where('institution_id', $institutionId)->whereIn('id', $officeIds)->orderBy('sort_order')->orderBy('name')->get(),
            'classification_year' => $year,
        ];
    }

    /** @return Collection<int, array{activity: MainActivity|SubActivity, has_data: bool}> */
    private function activityOptions(Collection $activities, Collection $idsWithData): Collection
    {
        $with = $idsWithData->filter()->map(fn ($id) => (int) $id)->flip();

        return $activities->map(fn ($a) => ['activity' => $a, 'has_data' => $with->has($a->id)]);
    }
}
