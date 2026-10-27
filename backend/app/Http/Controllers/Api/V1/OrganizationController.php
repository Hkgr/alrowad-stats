<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OrganizationDashboardRequest;
use App\Services\Organization\OrganizationDashboardService;

class OrganizationController extends Controller
{
    public function show(OrganizationDashboardRequest $request, OrganizationDashboardService $service)
    {
        return response()->json(['data' => $service->build($request->filters())]);
    }
}
