<?php

namespace App\Http\Resources;

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

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'institution' => new InstitutionResource($this->filters->institution),
            'classification_year' => $this->options['classification_year'],
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
