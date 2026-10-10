<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InstitutionFiltersRequest;
use App\Services\Dashboard\AnalyticsService;
use App\Services\Dashboard\DataQualityService;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalyticsController extends Controller
{
    /** Deeper readings of the filtered scope (matrix, pyramid, courses, specialties, monthly stack). */
    public function show(InstitutionFiltersRequest $request, AnalyticsService $analytics)
    {
        return new JsonResource($analytics->build($request->filters()));
    }

    /** Import problems and data holes of the institution (filters other than `institution` are ignored). */
    public function quality(InstitutionFiltersRequest $request, DataQualityService $quality)
    {
        return new JsonResource($quality->build($request->filters()->institution));
    }
}
