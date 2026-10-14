<?php

namespace App\Http\Resources;

use App\Models\Office;
use App\Services\Dashboard\DashboardFilters;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Wraps the array returned by FilterOptionsService::for() together with the filters it was built for. */
class FilterOptionsResource extends JsonResource
{
    public function __construct(private readonly DashboardFilters $filters, private readonly array $options)
    {
        parent::__construct($options);
    }

    /**
     * @param  list<array{office: Office, has_data: bool}>  $offices
     * @return array<string, mixed>
     */
    private function region(string $slug, string $name, bool $hasOffices, array $offices): array
    {
        return [
            'slug' => $slug,
            'name' => $name,
            'has_offices' => $hasOffices,
            // Offices that belong to it (0 for a region that only stands for itself).
            'offices_count' => $hasOffices ? count($offices) : 0,
            'has_data' => (bool) array_filter($offices, fn ($row) => $row['has_data']),
            'offices' => array_map(fn ($row) => ['slug' => $row['office']->slug, 'name' => $row['office']->label(), 'has_data' => $row['has_data']], $offices),
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'institution' => new InstitutionResource($this->filters->institution),
            'classification_year' => $this->options['classification_year'],
            'years' => $this->options['years'],
            // Pick offices grouped by region. A region with has_offices = false has no office of its own:
            // its single entry stands for the region. Offices outside every region come last as «بدون منطقة».
            // A region none of whose offices exists yet has nothing to pick, so it is left out.
            'regions' => array_values(array_filter([
                ...array_map(fn ($row) => $this->region($row['region']->slug, $row['region']->name, $row['region']->has_offices, $row['offices']), $this->options['regions']),
                $this->region('none', 'بدون منطقة', true, $this->options['unassigned_offices']),
            ], fn ($region) => $region['offices'] !== [])),
            'sectors' => $this->options['sectors']->map(fn ($row) => [
                'slug' => $row['sector']->slug,
                'code' => $row['sector']->code,
                'name' => $row['sector']->name,
                'classified_projects' => $row['classified'],
            ])->values(),
            'projects' => $this->options['projects']->map(fn ($row) => [
                'slug' => $row['project']->slug,
                'name' => $row['project']->name,
                'sector' => $row['sector'] ? ['slug' => $row['sector']->slug, 'name' => $row['sector']->name] : null,
                'has_data' => $row['has_data'],
            ])->values(),
            'main_activities' => $this->options['main_activities']->map(fn ($row) => [
                'slug' => $row['activity']->slug,
                'name' => $row['activity']->name,
                'category' => $row['activity']->category?->name,
                'has_data' => $row['has_data'],
            ])->values(),
            'sub_activities' => $this->options['sub_activities']->map(fn ($row) => [
                'slug' => $row['activity']->slug,
                'name' => $row['activity']->name,
                'has_data' => $row['has_data'],
            ])->values(),
            'periods' => $this->options['periods']->map(fn ($p) => [
                'key' => $p->key(),
                'label' => $p->label(),
                'year' => $p->year,
                'month' => $p->month,
            ])->values(),
            'offices' => $this->options['offices']->map(fn ($o) => [
                'slug' => $o->slug,
                'name' => $o->name,
            ])->values(),
        ];
    }
}
