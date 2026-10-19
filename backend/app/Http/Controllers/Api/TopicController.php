<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ResourceInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderTopicsRequest;
use App\Http\Requests\SaveTopicRequest;
use App\Http\Resources\TopicResource;
use App\Models\Answer;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TopicController extends Controller
{
    public function index(Subject $subject): AnonymousResourceCollection
    {
        Gate::authorize('view', $subject);

        return TopicResource::collection(
            $subject->topics()->withCount(['questions', 'questionsWithoutAnswer'])->get(),
        );
    }

    public function store(SaveTopicRequest $request, Subject $subject): JsonResponse
    {
        $topic = $subject->topics()->create([
            'title' => $request->string('title'),
            'position' => $request->integer('position', (int) $subject->topics()->max('position') + 1),
        ]);

        return TopicResource::make($topic)->response()->setStatusCode(201);
    }

    public function show(Topic $topic): TopicResource
    {
        Gate::authorize('view', $topic);

        return TopicResource::make($topic->loadCount(['questions', 'questionsWithoutAnswer']));
    }

    public function update(SaveTopicRequest $request, Topic $topic): TopicResource
    {
        $topic->update($request->validated());

        return TopicResource::make($topic);
    }

    /**
     * Save a new order of the subject topics; positions follow the given ids.
     */
    public function reorder(ReorderTopicsRequest $request, Subject $subject): AnonymousResourceCollection
    {
        DB::transaction(function () use ($request, $subject): void {
            foreach ($request->array('topic_ids') as $index => $topicId) {
                $subject->topics()->whereKey($topicId)->update(['position' => $index + 1]);
            }
        });

        return TopicResource::collection($subject->topics()->get());
    }

    public function destroy(Topic $topic): Response
    {
        Gate::authorize('delete', $topic);

        if (Answer::query()->whereHas('question', fn ($query) => $query->whereBelongsTo($topic))->exists()) {
            throw ResourceInUseException::hasGrades('Mavzu', 'Savollarini boshqa mavzuga ko\'chiring.');
        }

        $topic->delete();

        return response()->noContent();
    }
}
