<?php

use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\FilterController;
use App\Http\Controllers\Api\V1\InstitutionController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        DB::connection()->getPdo();

        return response()->json([
            'status' => 'ok',
            'application' => 'Rowad Insights',
            'database' => 'connected',
        ]);
    });

    // Phase 1 is read-only and public. Phase 2 puts these behind auth:sanctum.
    Route::get('/institutions', [InstitutionController::class, 'index']);
    Route::get('/filters', [FilterController::class, 'show']);
    Route::get('/dashboard', [DashboardController::class, 'show']);
});
