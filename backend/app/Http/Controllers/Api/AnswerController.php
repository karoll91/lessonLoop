<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\LessonFlowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\GradeAnswerRequest;
use App\Http\Requests\SkipAnswerRequest;
use App\Http\Requests\UpdateAnswerRequest;
use App\Http\Resources\AnswerResource;
use App\Models\Answer;
use App\Models\SyncOperation;
use App\Services\LessonRunner;
use App\Services\OfflineSync;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * @group Darslar
 */
class AnswerController extends Controller
{
    public function __construct(private LessonRunner $runner, private OfflineSync $sync) {}

    public function grade(GradeAnswerRequest $request, Answer $answer): AnswerResource
    {
        if ($request->filled('client_uuid')) {
            return AnswerResource::make($this->once($request, $answer, 'grade')->load(['student', 'question.options']));
        }

        $answer = $this->runner->grade(
            $answer,
            $request->filled('score') ? $request->integer('score') : null,
            $request->input('comment'),
            $request->input('chosen_option'),
        );

        return AnswerResource::make($answer->load(['student', 'question.options']));
    }

    /**
     * Change a grade after the lesson, with a reason that is kept in the history.
     */
    public function update(UpdateAnswerRequest $request, Answer $answer): AnswerResource
    {
        $answer = $this->runner->regrade($answer, $request->user(), $request->integer('score'), $request->string('reason'));

        return AnswerResource::make($answer->load(['student', 'question.options', 'edits.user']));
    }

    public function skip(SkipAnswerRequest $request, Answer $answer): AnswerResource
    {
        if ($request->filled('client_uuid')) {
            return AnswerResource::make($this->once($request, $answer, 'skip')->load(['student', 'question.options']));
        }

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

    /**
     * Grade or skip through OfflineSync, so a request sent twice with the
     * same client_uuid is applied once and answered the same way.
     */
    private function once(FormRequest $request, Answer $answer, string $type): Answer
    {
        $operation = $this->sync->find($request->user(), $request->input('client_uuid'))
            ?? $this->sync->apply($request->user(), ['type' => $type] + $request->validated(), $answer);

        if ($operation->status === SyncOperation::FAILED) {
            throw new LessonFlowException((string) $operation->message, (string) $operation->error_code);
        }

        return $answer->refresh();
    }
}
