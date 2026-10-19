<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ResourceInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Models\StudyGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class StudentController extends Controller
{
    public function index(Request $request, StudyGroup $group): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Student::class, $group]);

        $students = $group->students()
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->active())
            ->when($request->filled('q'), fn ($query) => $query->where('full_name', 'like', '%'.$request->string('q').'%'))
            ->orderBy('full_name')
            ->paginate($this->perPage($request, 50));

        return StudentResource::collection($students);
    }

    public function store(SaveStudentRequest $request, StudyGroup $group): JsonResponse
    {
        $student = $group->students()->create($request->validated());

        return StudentResource::make($student)->response()->setStatusCode(201);
    }

    public function show(Student $student): StudentResource
    {
        Gate::authorize('view', $student);

        return StudentResource::make($student);
    }

    public function update(SaveStudentRequest $request, Student $student): StudentResource
    {
        $student->update($request->validated());

        return StudentResource::make($student);
    }

    /**
     * A student with grades can only be deactivated (is_active = false).
     */
    public function destroy(Student $student): Response
    {
        Gate::authorize('delete', $student);

        if ($student->answers()->exists()) {
            throw ResourceInUseException::hasGrades('Talaba', 'Uni nofaol qiling.');
        }

        $student->delete();

        return response()->noContent();
    }
}
