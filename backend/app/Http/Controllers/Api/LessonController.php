<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartLessonRequest;
use App\Http\Resources\AnswerResource;
use App\Http\Resources\LessonResource;
use App\Models\Course;
use App\Models\Lesson;
use App\Services\LessonRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class LessonController extends Controller
{
    public function __construct(private LessonRunner $runner) {}

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

        return LessonResource::make($lesson->load(['topics', 'answers.student', 'answers.question.options']));
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
