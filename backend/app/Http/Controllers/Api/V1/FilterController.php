<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InstitutionFiltersRequest;
use App\Http\Resources\FilterOptionsResource;
use App\Services\Dashboard\FilterOptionsService;

class FilterController extends Controller
{
    public function show(InstitutionFiltersRequest $request, FilterOptionsService $options)
    {
        $filters = $request->filters();

        return new FilterOptionsResource($filters, $options->for($filters));
    }
}
