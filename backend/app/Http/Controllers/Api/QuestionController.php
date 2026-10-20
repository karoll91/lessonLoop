<?php

namespace App\Http\Controllers\Api;

use App\Enums\QuestionType;
use App\Exceptions\ResourceInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveQuestionRequest;
use App\Http\Resources\QuestionResource;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * @group Savollar
 */
class QuestionController extends Controller
{
    public function index(Request $request, Topic $topic): AnonymousResourceCollection
    {
        Gate::authorize('view', $topic);

        return QuestionResource::collection(
            $this->filtered($topic->questions()->getQuery(), $request)->paginate($this->perPage($request)),
        );
    }

    /**
     * Search the whole question bank of a subject.
     */
    public function search(Request $request, Subject $subject): AnonymousResourceCollection
    {
        Gate::authorize('view', $subject);

        $query = Question::query()->whereHas('topic', fn ($topics) => $topics->whereBelongsTo($subject));

        return QuestionResource::collection($this->filtered($query, $request)->paginate($this->perPage($request)));
    }

    public function store(SaveQuestionRequest $request, Topic $topic): JsonResponse
    {
        $question = DB::transaction(function () use ($request, $topic): Question {
            $question = $topic->questions()->create($request->safe()->except('options'));
            $this->syncOptions($question, $request);

            return $question;
        });

        return QuestionResource::make($question->load('options'))->response()->setStatusCode(201);
    }

    public function show(Question $question): QuestionResource
    {
        Gate::authorize('view', $question);

        return QuestionResource::make($question->load('options')->loadCount('answers')->loadAvg('answers', 'score'));
    }

    public function update(SaveQuestionRequest $request, Question $question): QuestionResource
    {
        DB::transaction(function () use ($request, $question): void {
            $question->update($request->safe()->except('options'));
            $this->syncOptions($question, $request);
        });

        return QuestionResource::make($question->load('options'));
    }

    public function destroy(Question $question): Response
    {
        Gate::authorize('delete', $question);

        if ($question->answers()->exists()) {
            throw ResourceInUseException::hasGrades('Savol', 'Uni tahrirlang yoki boshqa mavzuga ko\'chiring.');
        }

        $question->delete();

        return response()->noContent();
    }

    /**
     * Replace the options of a test question; open questions keep none.
     */
    private function syncOptions(Question $question, SaveQuestionRequest $request): void
    {
        if ($request->effectiveType() === QuestionType::Open) {
            $question->options()->delete();

            return;
        }

        if (! $request->has('options')) {
            return;
        }

        $question->options()->delete();
        $question->options()->createMany(collect($request->validated('options'))->map(fn (array $option): array => [
            'label' => $option['label'],
            'text' => $option['text'],
            'is_correct' => (bool) ($option['is_correct'] ?? false),
        ])->all());
    }

    /**
     * @param  Builder<Question>  $query
     * @return Builder<Question>
     */
    private function filtered(Builder $query, Request $request): Builder
    {
        return $query
            ->with('options')
            ->withCount('answers')
            ->withAvg('answers', 'score')
            ->when($request->filled('type'), fn ($inner) => $inner->where('type', $request->string('type')))
            ->when($request->filled('difficulty'), fn ($inner) => $inner->where('difficulty', $request->string('difficulty')))
            ->when($request->boolean('without_answer'), fn ($inner) => $inner
                ->where('type', QuestionType::Open)
                ->where(fn ($empty) => $empty->whereNull('answer_text')->orWhere('answer_text', '')))
            ->when($request->filled('q'), fn ($inner) => $inner->where('text', 'like', '%'.$request->string('q').'%'))
            ->orderBy('id');
    }
}
