<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ResourceInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveStudyGroupRequest;
use App\Http\Resources\StudyGroupResource;
use App\Models\Lesson;
use App\Models\StudyGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * @group Guruhlar va talabalar
 */
class StudyGroupController extends Controller
{
    /**
     * Admins see every group, teachers only the groups of their courses.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $groups = StudyGroup::query()
            ->withCount('students')
            ->when(! $user->isAdmin(), fn ($query) => $query->whereHas('courses', fn ($courses) => $courses->where('teacher_id', $user->id)))
            ->when($request->boolean('archived'), fn ($query) => $query->whereNotNull('archived_at'), fn ($query) => $query->whereNull('archived_at'))
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.$request->string('q').'%'))
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return StudyGroupResource::collection($groups);
    }

    public function store(SaveStudyGroupRequest $request): JsonResponse
    {
        $group = StudyGroup::query()->create($request->modelAttributes());

        return StudyGroupResource::make($group)->response()->setStatusCode(201);
    }

    public function show(StudyGroup $group): StudyGroupResource
    {
        Gate::authorize('view', $group);

        return StudyGroupResource::make($group->loadCount('students')->load(['courses.subject', 'courses.teacher']));
    }

    public function update(SaveStudyGroupRequest $request, StudyGroup $group): StudyGroupResource
    {
        $group->update($request->modelAttributes());

        return StudyGroupResource::make($group);
    }

    /**
     * A group with lesson history can only be archived.
     */
    public function destroy(StudyGroup $group): Response
    {
        Gate::authorize('delete', $group);

        if (Lesson::query()->whereHas('course', fn ($query) => $query->whereBelongsTo($group))->exists()) {
            throw ResourceInUseException::hasGrades('Guruh', 'Uni arxivlang.');
        }

        $group->delete();

        return response()->noContent();
    }
}
