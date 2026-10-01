<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InstitutionResource;
use App\Models\Institution;

class InstitutionController extends Controller
{
    public function index()
    {
        return InstitutionResource::collection(
            Institution::where('is_active', true)->orderBy('name')->get(),
        );
    }
}
