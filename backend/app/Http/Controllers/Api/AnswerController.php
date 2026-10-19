<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GradeAnswerRequest;
use App\Http\Requests\SkipAnswerRequest;
use App\Http\Resources\AnswerResource;
use App\Models\Answer;
use App\Services\LessonRunner;
use Illuminate\Support\Facades\Gate;

class AnswerController extends Controller
{
    public function __construct(private LessonRunner $runner) {}

    public function grade(GradeAnswerRequest $request, Answer $answer): AnswerResource
    {
        $answer = $this->runner->grade(
            $answer,
            $request->filled('score') ? $request->integer('score') : null,
            $request->input('comment'),
            $request->input('chosen_option'),
        );

        return AnswerResource::make($answer->load(['student', 'question.options']));
    }

    public function skip(SkipAnswerRequest $request, Answer $answer): AnswerResource
    {
        $answer = $request->input('reason') === 'absent'
            ? $this->runner->markAbsent($answer)
            : $this->runner->markNoAnswer($answer, $request->input('comment'));

        return AnswerResource::make($answer->load(['student', 'question.options']));
    }

    public function redraw(Answer $answer): AnswerResource
    {
        Gate::authorize('manage', $answer->lesson);

        return AnswerResource::make($this->runner->redraw($answer)->load(['student', 'question.options']));
    }
}
