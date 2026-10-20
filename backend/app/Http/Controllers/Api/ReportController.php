<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\ReportBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * @group Jurnal va hisobotlar
 */
class ReportController extends Controller
{
    public function __construct(private ReportBuilder $builder) {}

    /**
     * Course statistics for the reports page.
     */
    public function __invoke(Course $course): JsonResponse
    {
        Gate::authorize('teach', $course);

        return response()->json(['data' => $this->builder->build($course)]);
    }
}
