<?php

namespace App\Services;

use App\Enums\AnswerStatus;
use App\Models\Answer;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Support\Collection;

/**
 * Course statistics for the reports page: score trend per lesson, score
 * distribution, how much of the group was asked, topics and hardest questions.
 */
class ReportBuilder
{
    /** Lower bounds of the score distribution buckets. */
    public const BUCKETS = [0, 20, 40, 60, 80];

    public function __construct(private QueueStatusResolver $queue) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Course $course): array
    {
        $course->loadMissing(['studyGroup', 'subject']);
        $passScore = (int) config('lessonloop.pass_score');

        $lessons = $course->lessons()->orderBy('sequence')->get();
        $answers = Answer::query()
            ->whereIn('lesson_id', $lessons->modelKeys())
            ->whereIn('status', [AnswerStatus::Graded, AnswerStatus::NoAnswer])
            ->with('question.topic')
            ->get();

        $students = $course->studyGroup->students()->active()->pluck('id');
        $statuses = $this->queue->forStudents($course, $this->queue->currentSequence($course), $students->all());
        $askedStudents = $answers->pluck('student_id')->unique()->intersect($students);

        return [
            'course' => [
                'id' => $course->id,
                'group' => $course->studyGroup->name,
                'subject' => $course->subject->name,
            ],
            'summary' => [
                'lessons' => $lessons->count(),
                'answers' => $answers->count(),
                'average' => $this->average($answers),
                'pass_rate' => $answers->isEmpty() ? null : (int) round($answers->filter(fn (Answer $answer): bool => $answer->score >= $passScore)->count() * 100 / $answers->count()),
                'debtors' => $statuses->where('state', QueueStatus::DEBTOR)->count(),
            ],
            'coverage' => [
                'students' => $students->count(),
                'asked' => $askedStudents->count(),
                'never_asked' => $students->count() - $askedStudents->count(),
            ],
            'lessons' => $lessons->map(function (Lesson $lesson) use ($answers, $passScore): array {
                $lessonAnswers = $answers->where('lesson_id', $lesson->id);

                return [
                    'id' => $lesson->id,
                    'sequence' => $lesson->sequence,
                    'date' => $lesson->started_at?->toDateString(),
                    'answers' => $lessonAnswers->count(),
                    'average' => $this->average($lessonAnswers),
                    'passed' => $lessonAnswers->filter(fn (Answer $answer): bool => $answer->score >= $passScore)->count(),
                ];
            })->values()->all(),
            'distribution' => collect(self::BUCKETS)->map(fn (int $from, int $index): array => [
                'from' => $from,
                'to' => (self::BUCKETS[$index + 1] ?? 101) - 1,
                'count' => $answers->filter(fn (Answer $answer): bool => $answer->score >= $from && $answer->score < (self::BUCKETS[$index + 1] ?? 101))->count(),
            ])->all(),
            'topics' => $answers->groupBy(fn (Answer $answer): int => $answer->question->topic_id)
                ->map(fn (Collection $topicAnswers): array => [
                    'id' => $topicAnswers->first()->question->topic->id,
                    'title' => $topicAnswers->first()->question->topic->title,
                    'answers' => $topicAnswers->count(),
                    'average' => $this->average($topicAnswers),
                ])
                ->sortBy('average')
                ->values()
                ->all(),
            'hardest_questions' => $answers->groupBy('question_id')
                ->map(fn (Collection $questionAnswers): array => [
                    'id' => $questionAnswers->first()->question->id,
                    'text' => $questionAnswers->first()->question->text,
                    'topic' => $questionAnswers->first()->question->topic->title,
                    'times_asked' => $questionAnswers->count(),
                    'average' => $this->average($questionAnswers),
                ])
                ->sortBy([['average', 'asc'], ['times_asked', 'desc']])
                ->take(5)
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, Answer>  $answers
     */
    private function average(Collection $answers): ?int
    {
        return $answers->isEmpty() ? null : (int) round($answers->avg('score'));
    }
}
