<?php

namespace App\Http\Resources;

use App\Models\DataSource;
use App\Models\Sector;
use App\Services\Dashboard\DashboardData;
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
        $filters = $data->filters;
        $totals = $data->totals;
        $total = $totals?->total();

        return [
            'institution' => new InstitutionResource($filters->institution),
            'level' => $filters->level(),
            'classification_year' => $filters->classificationYear,
            'filters' => [
                'sector' => $this->sector($filters->sector),
                'project' => $filters->project ? ['slug' => $filters->project->slug, 'name' => $filters->project->name] : null,
                'period' => $filters->period ? ['key' => $filters->period->key(), 'label' => $filters->period->label()] : null,
                'office' => $filters->office ? ['slug' => $filters->office->slug, 'name' => $filters->office->name] : null,
            ],
            'active_sector' => $this->sector($data->activeSector),
            'measure' => new MeasureResource($data->measure),
            'has_data' => $data->hasData(),
            // Absence is not zero: with no rows in scope every figure is null.
            'summary' => [
                'total' => $total,
                'male' => $totals?->male,
                'female' => $totals?->female,
                'male_share' => $totals ? $this->share($totals->male, $total) : null,
                'female_share' => $totals ? $this->share($totals->female, $total) : null,
                'offices_count' => $totals?->offices,
                'projects_with_data' => $totals?->projects ?? 0,
                'classified_projects' => $data->classifiedProjects,
            ],
            'sectors' => array_map(fn ($row) => [
                ...$this->sector($row['sector']),
                'selected' => $data->activeSector?->id === $row['sector']->id,
                'classified_projects' => $row['classified'],
                ...$this->figures($row['figures']),
            ], $data->sectors),
            'unclassified' => $data->unclassified ? $this->figures($data->unclassified) : null,
            'projects' => array_map(fn ($row) => [
                'slug' => $row['project']->slug,
                'name' => $row['project']->name,
                'sector' => $row['sector'] ? ['slug' => $row['sector']->slug, 'name' => $row['sector']->name] : null,
                'selected' => $filters->project?->id === $row['project']->id,
                ...$this->figures($row['figures']),
            ], $data->projects),
            'periods' => array_map(fn ($row) => [
                'key' => $row['period']->key(),
                'label' => $row['period']->label(),
                ...$this->figures($row['figures']),
            ], $data->periods),
            'offices' => array_map(fn (OfficeFigures $o) => $this->office($o) + ['share' => $this->share($o->total(), (int) $total)], $data->offices),
            'comparison' => array_map(fn (OfficeFigures $o) => $this->office($o) + ['selected' => $o->selected], $data->comparison),
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

    /** Figures of a slice; every value is null (and has_data false) when the slice has no records. */
    private function figures(?Figures $f): array
    {
        return [
            'has_data' => $f !== null,
            'total' => $f?->total(),
            'male' => $f?->male,
            'female' => $f?->female,
            'projects_with_data' => $f?->projects,
            'offices_count' => $f?->offices,
        ];
    }

    /** @return array<string, mixed> */
    private function office(OfficeFigures $o): array
    {
        return ['slug' => $o->slug, 'name' => $o->name, 'male' => $o->male, 'female' => $o->female, 'total' => $o->total()];
    }

    /** Percentage with one decimal, derived from the same filtered numbers. */
    private function share(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }
}
