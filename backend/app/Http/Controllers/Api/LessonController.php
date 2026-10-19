<?php

namespace App\Http\Controllers\Api;

use App\Enums\LessonStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StartLessonRequest;
use App\Http\Resources\AnswerResource;
use App\Http\Resources\LessonResource;
use App\Models\Course;
use App\Models\Lesson;
use App\Services\LessonRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LessonController extends Controller
{
    public function __construct(private LessonRunner $runner) {}

    /**
     * Lesson history of the user's courses, newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'course_id' => ['sometimes', 'integer'],
            'status' => ['sometimes', Rule::enum(LessonStatus::class)],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);

        $lessons = Lesson::query()
            ->visibleTo($request->user())
            ->withSummary()
            ->with(['course.studyGroup', 'course.subject'])
            ->when($request->filled('course_id'), fn ($query) => $query->where('course_id', $request->integer('course_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('started_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('started_at', '<=', $request->date('to')))
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return LessonResource::collection($lessons);
    }

    public function store(StartLessonRequest $request, Course $course): JsonResponse
    {
        $lesson = $this->runner->start(
            $course,
            $request->user(),
            $request->array('topic_ids'),
            $request->integer('question_count'),
            $request->array('absent_student_ids'),
        );

        return LessonResource::make($lesson->load('topics'))->response()->setStatusCode(201);
    }

    public function show(Lesson $lesson): LessonResource
    {
        Gate::authorize('view', $lesson);

        return LessonResource::make($lesson->load(['course.studyGroup', 'course.subject', 'topics', 'answers.student', 'answers.question.options', 'answers.edits.user']));
    }

    public function draw(Lesson $lesson): JsonResponse
    {
        Gate::authorize('manage', $lesson);

        $answer = $this->runner->draw($lesson);

        return AnswerResource::make($answer->load(['student', 'question.options']))->response()->setStatusCode(201);
    }

    public function finish(Lesson $lesson): LessonResource
    {
        Gate::authorize('manage', $lesson);

        return LessonResource::make($this->runner->finish($lesson));
    }
}
