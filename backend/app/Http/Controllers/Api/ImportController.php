<?php

namespace App\Http\Controllers\Api;

use App\Exports\ImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportQuestionsRequest;
use App\Http\Requests\ImportStudentsRequest;
use App\Models\StudyGroup;
use App\Models\Topic;
use App\Services\Imports\QuestionImporter;
use App\Services\Imports\SheetReader;
use App\Services\Imports\StudentImporter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Bulk import of students and questions. With dry_run=1 nothing is saved and
 * the response is a preview of what would be created, skipped and rejected.
 */
class ImportController extends Controller
{
    public function __construct(private SheetReader $reader) {}

    public function students(ImportStudentsRequest $request, StudyGroup $group, StudentImporter $importer): JsonResponse
    {
        $report = $importer->import($group, $this->rows($request), $request->boolean('dry_run'));

        return response()->json($report->toArray(), $report->dryRun ? 200 : 201);
    }

    public function questions(ImportQuestionsRequest $request, Topic $topic, QuestionImporter $importer): JsonResponse
    {
        $report = $importer->import($topic, $this->rows($request), $request->boolean('dry_run'));

        return response()->json($report->toArray(), $report->dryRun ? 200 : 201);
    }

    public function template(string $type): BinaryFileResponse
    {
        abort_unless(in_array($type, [ImportTemplateExport::STUDENTS, ImportTemplateExport::QUESTIONS], true), 404);

        return Excel::download(new ImportTemplateExport($type), "lessonloop-{$type}-shablon.xlsx");
    }

    /**
     * @return list<array{row: int, cells: list<string>}>
     */
    private function rows(FormRequest $request): array
    {
        return $request->hasFile('file')
            ? $this->reader->fromFile($request->file('file'))
            : $this->reader->fromText($request->string('text'));
    }
}
