<?php

namespace App\Http\Resources;

use App\Models\DataSource;
use App\Services\Dashboard\DashboardData;
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
        $total = $data->total();

        return [
            'institution' => new InstitutionResource($filters->institution),
            'filters' => [
                'project' => $filters->project ? ['slug' => $filters->project->slug, 'name' => $filters->project->name] : null,
                'period' => $filters->period ? ['key' => $filters->period->key(), 'label' => $filters->period->label()] : null,
                'office' => $filters->office ? ['slug' => $filters->office->slug, 'name' => $filters->office->name] : null,
            ],
            'measure' => new MeasureResource($data->measure),
            'has_data' => $data->hasData(),
            // Absence is not zero: with no rows in scope every figure is null.
            'summary' => [
                'total' => $data->hasData() ? $total : null,
                'male' => $data->hasData() ? $data->male() : null,
                'female' => $data->hasData() ? $data->female() : null,
                'male_share' => $data->hasData() ? $this->share($data->male(), $total) : null,
                'female_share' => $data->hasData() ? $this->share($data->female(), $total) : null,
                'offices_count' => $data->hasData() ? count($data->offices) : null,
            ],
            'offices' => array_map(fn (OfficeFigures $o) => $this->office($o, $total), $data->offices),
            'comparison' => array_map(fn (OfficeFigures $o) => $this->office($o, null) + ['selected' => $o->selected], $data->comparison),
            'scope' => [
                'projects' => $data->projects->map(fn ($p) => [
                    'slug' => $p->slug,
                    'name' => $p->name,
                    'sector' => $p->sector?->name,
                    'source_category' => $p->source_category,
                ])->values(),
                'periods' => $data->periods->map(fn ($p) => ['key' => $p->key(), 'label' => $p->label()])->values(),
                'sources' => $data->sources->map(fn (DataSource $s) => [
                    'label' => $s->label,
                    'file_name' => $s->file_name,
                    'reference_url' => $s->reference_url,
                    'coverage' => $s->coverage,
                    'notes' => $s->notes,
                ])->values(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function office(OfficeFigures $o, ?int $scopeTotal): array
    {
        $row = [
            'slug' => $o->slug,
            'name' => $o->name,
            'male' => $o->male,
            'female' => $o->female,
            'total' => $o->total(),
        ];

        if ($scopeTotal !== null) {
            $row['share'] = $this->share($o->total(), $scopeTotal);
        }

        return $row;
    }

    /** Percentage with one decimal, derived from the same filtered numbers. */
    private function share(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }
}
