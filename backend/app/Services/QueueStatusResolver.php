<?php

namespace App\Services;

use App\Enums\AnswerStatus;
use App\Enums\LessonStatus;
use App\Models\Answer;
use App\Models\Course;
use Illuminate\Support\Collection;

/**
 * Applies the queue rules R2-R4 to the scored history of a course:
 * a passing score rests the student for cooldown_lessons - 1 lessons,
 * a latest score below the pass score makes the student a debtor.
 */
class QueueStatusResolver
{
    /**
     * Status of each student before the lesson with the given sequence.
     *
     * @param  array<int, int>  $studentIds
     * @return Collection<int, QueueStatus> keyed by student id
     */
    public function forStudents(Course $course, int $sequence, array $studentIds): Collection
    {
        $history = Answer::query()
            ->with('lesson:id,sequence')
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', [AnswerStatus::Graded, AnswerStatus::NoAnswer])
            ->whereHas('lesson', fn ($query) => $query
                ->where('course_id', $course->id)
                ->where('sequence', '<', $sequence))
            ->get()
            ->sortByDesc(fn (Answer $answer): array => [$answer->lesson->sequence, $answer->id])
            ->groupBy('student_id');

        return collect($studentIds)->mapWithKeys(fn (int $studentId): array => [
            $studentId => $this->resolve($history->get($studentId, collect()), $sequence),
        ]);
    }

    /**
     * The lesson the statuses are about: the running one, otherwise the next one.
     */
    public function currentSequence(Course $course): int
    {
        $running = $course->lessons()->where('status', '!=', LessonStatus::Finished)->max('sequence');

        return $running !== null ? (int) $running : $course->nextLessonSequence();
    }

    /**
     * @param  Collection<int, Answer>  $answers  newest first
     */
    private function resolve(Collection $answers, int $sequence): QueueStatus
    {
        $passScore = (int) config('lessonloop.pass_score');
        $cooldown = (int) config('lessonloop.cooldown_lessons');

        $latest = $answers->first();
        $lastPassSequence = $answers
            ->filter(fn (Answer $answer): bool => $answer->score >= $passScore)
            ->max(fn (Answer $answer): int => $answer->lesson->sequence);

        $isDebtor = $latest !== null && $latest->score < $passScore;
        $isResting = ! $isDebtor && $lastPassSequence !== null && $sequence - $lastPassSequence < $cooldown;

        return new QueueStatus(
            state: match (true) {
                $latest === null => QueueStatus::NEVER_ASKED,
                $isDebtor => QueueStatus::DEBTOR,
                $isResting => QueueStatus::RESTING,
                default => QueueStatus::QUEUE,
            },
            askedCount: $answers->count(),
            lastScore: $latest?->score,
            lastSequence: $latest?->lesson->sequence,
            lastPassSequence: $lastPassSequence,
            restLessonsLeft: $isResting ? $cooldown - ($sequence - $lastPassSequence) : 0,
        );
    }
}
