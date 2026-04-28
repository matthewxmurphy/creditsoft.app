<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\OfficeImpactStatsService;
use Illuminate\Http\JsonResponse;

class OfficeImpactStatsController extends Controller
{
    public function __invoke(OfficeImpactStatsService $impactStats): JsonResponse
    {
        return response()->json([
            'data' => $impactStats->summary(),
        ]);
    }
}
