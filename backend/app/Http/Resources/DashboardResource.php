<?php

namespace App\Http\Resources;

use App\Models\DataSource;
use App\Models\Sector;
use App\Services\Dashboard\DashboardData;
use App\Services\Dashboard\DashboardFilters;
use App\Services\Dashboard\Figures;
use App\Services\Dashboard\OfficeFigures;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property DashboardData $resource */
class DashboardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var DashboardData $data */
        $data = $this->resource;
        $f = $data->filters;
        $totals = $data->totals;
        $total = $totals?->total;
        $mains = $data->mainActivities;
        $subs = $data->subActivities;

        return [
            'institution' => new InstitutionResource($f->institution),
            'level' => $f->level(),
            'classification_year' => $f->classificationYear,
            'filters' => [
                'sector' => $f->unclassified ? $this->unclassifiedRef() : $this->sector($f->sector),
                'project' => $f->project ? ['slug' => $f->project->slug, 'name' => $f->project->name] : null,
                'main_activity' => $f->mainActivity ? ['slug' => $f->mainActivity->slug, 'name' => $f->mainActivity->name] : null,
                'sub_activity' => $f->subActivity ? ['slug' => $f->subActivity->slug, 'name' => $f->subActivity->name] : null,
                'period' => $f->period ? ['key' => $f->period->key(), 'label' => $f->period->label()] : null,
                'office' => $f->office ? ['slug' => $f->office->slug, 'name' => $f->office->name] : null,
            ],
            'active_sector' => $f->unclassified && ! $data->activeSector ? $this->unclassifiedRef() : $this->sector($data->activeSector),
            'measure' => new MeasureResource($data->measure),
            'has_data' => $data->hasData(),
            // Absence is not zero: with no rows in scope every figure is null.
            'summary' => [
                'total' => $total,
                'male' => $totals?->male,
                'female' => $totals?->female,
                'gender_unreported' => $totals?->genderUnreported(),
                'male_share' => $totals ? $this->share($totals->male, $total) : null,
                'female_share' => $totals ? $this->share($totals->female, $total) : null,
                'disabled' => $totals?->disabled,
                'offices_count' => $totals?->offices,
                'records' => $totals?->records,
                'projects_with_data' => $totals?->projects ?? 0,
                'classified_projects' => $data->classifiedProjects,
            ],
            'sectors' => array_map(fn ($row) => [
                ...$this->sector($row['sector']),
                'selected' => ! $f->unclassified && $data->activeSector?->id === $row['sector']->id,
                'classified_projects' => $row['classified'],
                ...$this->figures($row['figures']),
            ], $data->sectors),
            'unclassified' => $data->unclassified ? [
                ...$this->unclassifiedRef(),
                'selected' => $f->unclassified && ! $data->activeSector,
                ...$this->figures($data->unclassified),
            ] : null,
            'projects' => array_map(fn ($row) => [
                'slug' => $row['project']->slug,
                'name' => $row['project']->name,
                'sector' => $row['sector'] ? ['slug' => $row['sector']->slug, 'name' => $row['sector']->name] : null,
                'selected' => $f->project?->id === $row['project']->id,
                ...$this->figures($row['figures']),
            ], $data->projects),
            // Level 2 / Level 3 — present only at project / main activity level.
            'activities' => [
                'main_available' => $f->project ? $mains !== [] : null,
                'sub_available' => $f->mainActivity ? $subs !== [] : null,
                'main' => array_map(fn ($row) => [
                    'slug' => $row['activity']->slug,
                    'name' => $row['activity']->name,
                    'category' => $row['activity']->category?->name,
                    'sub_activities_count' => $row['subs'],
                    'selected' => $f->mainActivity?->id === $row['activity']->id,
                    ...$this->figures($row['figures']),
                ], $mains),
                'without_main' => $data->withoutMainActivity ? $this->figures($data->withoutMainActivity) : null,
                'sub' => array_map(fn ($row) => [
                    'slug' => $row['activity']->slug,
                    'name' => $row['activity']->name,
                    'selected' => $f->subActivity?->id === $row['activity']->id,
                    ...$this->figures($row['figures']),
                ], $subs),
                'without_sub' => $data->withoutSubActivity ? $this->figures($data->withoutSubActivity) : null,
                // Level 1 of the project sheet: the source's own grouping, not an activity level.
                'categories' => array_map(fn ($row) => [
                    'name' => $row['category']->name,
                    ...$this->figures($row['figures']),
                ], $data->categories),
            ],
            'periods' => array_map(fn ($row) => [
                'key' => $row['period']->key(),
                'label' => $row['period']->label(),
                'year' => $row['period']->year,
                'month' => $row['period']->month,
                'selected' => $f->period?->id === $row['period']->id,
                ...$this->figures($row['figures']),
            ], $data->periods),
            'offices' => array_map(fn (OfficeFigures $o) => $this->office($o) + ['share' => $this->share($o->total, (int) $total)], $data->offices),
            'comparison' => array_map(fn (OfficeFigures $o) => $this->office($o) + ['selected' => $o->selected], $data->comparison),
            // Other units in the same scope (e.g. families). Never added to registered benefits.
            'other_measures' => array_map(fn ($row) => [
                'code' => $row['measure']->code,
                'name' => $row['measure']->name,
                'unit_label' => $row['measure']->unit_label,
                'total' => $row['total'],
                'items_label' => $row['measure']->items_label,
                'items' => $row['items'],
            ], $data->otherMeasures),
            // Traceability only; the UI does not display it.
            'sources' => $data->sources->map(fn (DataSource $s) => [
                'label' => $s->label,
                'file_name' => $s->file_name,
                'coverage' => $s->coverage,
            ])->values(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function sector(?Sector $sector): ?array
    {
        return $sector ? ['slug' => $sector->slug, 'code' => $sector->code, 'name' => $sector->name] : null;
    }

    /** @return array<string, mixed> */
    private function unclassifiedRef(): array
    {
        return ['slug' => DashboardFilters::UNCLASSIFIED, 'code' => null, 'name' => 'غير مصنف'];
    }

    /** Figures of a slice; every value is null (and has_data false) when the slice has no records. */
    private function figures(?Figures $f): array
    {
        return [
            'has_data' => $f !== null,
            'total' => $f?->total,
            'male' => $f?->male,
            'female' => $f?->female,
            'gender_unreported' => $f?->genderUnreported(),
            'projects_with_data' => $f?->projects,
            'offices_count' => $f?->offices,
        ];
    }

    /** @return array<string, mixed> */
    private function office(OfficeFigures $o): array
    {
        return ['slug' => $o->slug, 'name' => $o->name, 'male' => $o->male, 'female' => $o->female, 'total' => $o->total];
    }

    /** Percentage with one decimal, derived from the same filtered numbers. */
    private function share(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }
}
