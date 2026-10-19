<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ResourceInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSubjectRequest;
use App\Http\Resources\SubjectResource;
use App\Models\Answer;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class SubjectController extends Controller
{
    /**
     * Admins see every subject, teachers only the subjects of their courses.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $subjects = Subject::query()
            ->withCount('topics')
            ->when(! $user->isAdmin(), fn ($query) => $query->whereHas('courses', fn ($courses) => $courses->where('teacher_id', $user->id)))
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', '%'.$request->string('q').'%')
                ->orWhere('code', 'like', '%'.$request->string('q').'%')))
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return SubjectResource::collection($subjects);
    }

    public function store(SaveSubjectRequest $request): JsonResponse
    {
        $subject = Subject::query()->create($request->validated());

        return SubjectResource::make($subject)->response()->setStatusCode(201);
    }

    public function show(Subject $subject): SubjectResource
    {
        Gate::authorize('view', $subject);

        return SubjectResource::make($subject->load(['topics' => fn ($query) => $query->withCount(['questions', 'questionsWithoutAnswer'])]));
    }

    public function update(SaveSubjectRequest $request, Subject $subject): SubjectResource
    {
        $subject->update($request->validated());

        return SubjectResource::make($subject);
    }

    public function destroy(Subject $subject): Response
    {
        Gate::authorize('delete', $subject);

        if (Answer::query()->whereHas('question.topic', fn ($query) => $query->whereBelongsTo($subject))->exists()) {
            throw ResourceInUseException::hasGrades('Fan', 'Avval uni guruhlardan ajrating.');
        }

        $subject->delete();

        return response()->noContent();
    }
}
