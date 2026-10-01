<?php

namespace App\Http\Resources;

use App\Models\Measure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Measure */
class MeasureResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'unit' => $this->unit,
            'unit_label' => $this->unit_label,
            'record_level' => $this->record_level,
            'record_level_label' => Measure::RECORD_LEVEL_LABELS[$this->record_level] ?? $this->record_level,
            'aggregation' => $this->aggregation,
            'aggregation_label' => Measure::AGGREGATION_LABELS[$this->aggregation] ?? $this->aggregation,
            'description' => $this->description,
        ];
    }
}
