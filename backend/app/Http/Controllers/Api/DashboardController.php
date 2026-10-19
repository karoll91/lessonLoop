<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardBuilder $builder): JsonResponse
    {
        return response()->json(['data' => $builder->build($request->user())]);
    }
}
