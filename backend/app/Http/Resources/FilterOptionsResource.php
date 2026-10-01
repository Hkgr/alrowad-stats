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
            'projects' => $this->options['projects']->map(fn ($p) => [
                'slug' => $p->slug,
                'name' => $p->name,
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
