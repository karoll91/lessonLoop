<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ResourceInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * A course binds a subject to a study group and a teacher.
 *
 * @group Kurslar
 */
class CourseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $courses = Course::query()
            ->with(['studyGroup', 'subject', 'teacher'])
            ->withCount('lessons')
            ->when(! $user->isAdmin(), fn ($query) => $query->where('teacher_id', $user->id))
            ->when($request->filled('study_group_id'), fn ($query) => $query->where('study_group_id', $request->integer('study_group_id')))
            ->when($request->filled('subject_id'), fn ($query) => $query->where('subject_id', $request->integer('subject_id')))
            ->orderBy('id')
            ->paginate($this->perPage($request, 50));

        return CourseResource::collection($courses);
    }

    public function store(SaveCourseRequest $request): JsonResponse
    {
        $course = Course::query()->create($request->validated());

        return CourseResource::make($course->load(['studyGroup', 'subject', 'teacher']))->response()->setStatusCode(201);
    }

    public function show(Course $course): CourseResource
    {
        Gate::authorize('view', $course);

        return CourseResource::make($course->load(['studyGroup', 'subject', 'teacher'])->loadCount('lessons'));
    }

    public function update(SaveCourseRequest $request, Course $course): CourseResource
    {
        $course->update($request->validated());

        return CourseResource::make($course->load(['studyGroup', 'subject', 'teacher']));
    }

    public function destroy(Course $course): Response
    {
        Gate::authorize('delete', $course);

        if ($course->lessons()->exists()) {
            throw ResourceInUseException::hasGrades('Kurs', 'O\'qituvchini almashtiring yoki guruhni arxivlang.');
        }

        $course->delete();

        return response()->noContent();
    }
}
