<?php

namespace App\Services;

use App\Exceptions\LessonFlowException;
use App\Models\Answer;
use App\Models\Lesson;
use App\Models\Question;
use Random\Randomizer;

/**
 * Chooses the next question (R7): only from the lesson topics, never twice in
 * the same lesson, and questions given to this course in the last few lessons
 * are used only when nothing fresher is left.
 */
class QuestionPicker
{
    public function __construct(private Randomizer $randomizer) {}

    /**
     * @param  array<int, int>  $excludeQuestionIds
     *
     * @throws LessonFlowException
     */
    public function pick(Lesson $lesson, array $excludeQuestionIds = []): Question
    {
        $availableIds = $this->availableQuestionIds($lesson, $excludeQuestionIds);

        if ($availableIds === []) {
            throw LessonFlowException::noQuestionsLeft();
        }

        $recentIds = Answer::query()
            ->whereHas('lesson', fn ($query) => $query
                ->where('course_id', $lesson->course_id)
                ->where('sequence', '<', $lesson->sequence)
                ->where('sequence', '>=', $lesson->sequence - (int) config('lessonloop.recent_question_lessons')))
            ->pluck('question_id')
            ->all();

        $freshIds = array_values(array_diff($availableIds, $recentIds));
        $pool = $freshIds !== [] ? $freshIds : $availableIds;

        return Question::query()->with('options')->findOrFail(
            $pool[$this->randomizer->getInt(0, count($pool) - 1)],
        );
    }

    /**
     * How many questions the given topics hold in total.
     *
     * @param  array<int, int>  $topicIds
     */
    public function countForTopics(array $topicIds): int
    {
        return Question::query()->whereIn('topic_id', $topicIds)->count();
    }

    /**
     * @param  array<int, int>  $excludeQuestionIds
     * @return array<int, int>
     */
    private function availableQuestionIds(Lesson $lesson, array $excludeQuestionIds): array
    {
        $usedInLesson = $lesson->answers()->pluck('question_id')->all();

        return Question::query()
            ->whereIn('topic_id', $lesson->topics()->pluck('topics.id'))
            ->whereNotIn('id', array_merge($usedInLesson, $excludeQuestionIds))
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }
}
