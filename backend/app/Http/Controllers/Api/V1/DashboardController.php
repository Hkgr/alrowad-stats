<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InstitutionFiltersRequest;
use App\Http\Resources\DashboardResource;
use App\Services\Dashboard\BeneficiaryDashboardService;

class DashboardController extends Controller
{
    public function show(InstitutionFiltersRequest $request, BeneficiaryDashboardService $service)
    {
        return new DashboardResource($service->build($request->filters()));
    }
}
