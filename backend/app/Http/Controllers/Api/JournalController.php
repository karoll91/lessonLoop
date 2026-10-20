<?php

namespace App\Http\Controllers\Api;

use App\Exports\JournalExport;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\JournalBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @group Jurnal va hisobotlar
 */
class JournalController extends Controller
{
    public function __construct(private JournalBuilder $builder) {}

    public function show(Course $course): JsonResponse
    {
        Gate::authorize('teach', $course);

        return response()->json(['data' => $this->builder->build($course)]);
    }

    /**
     * Download the journal as xlsx (default) or csv.
     */
    public function export(Request $request, Course $course): BinaryFileResponse
    {
        Gate::authorize('teach', $course);
        $request->validate(['format' => ['sometimes', 'in:xlsx,csv']]);

        $journal = $this->builder->build($course);
        $format = $request->input('format', 'xlsx');
        $name = Str::slug($journal['course']['group'].' '.($journal['course']['subject_code'] ?? $journal['course']['subject']).' jurnal');

        return Excel::download(
            new JournalExport($journal),
            "{$name}.{$format}",
            $format === 'csv' ? ExcelType::CSV : ExcelType::XLSX,
        );
    }
}
