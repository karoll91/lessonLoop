<?php

namespace App\Services;

use App\Enums\AnswerStatus;
use App\Exceptions\LessonFlowException;
use App\Models\Answer;
use App\Models\Lesson;
use App\Models\Student;
use Illuminate\Support\Collection;
use Random\Randomizer;

/**
 * Chooses which student answers next, following the queue rules from the PRD:
 *
 * R1 a student is asked at most once per lesson;
 * R2 a student who scored at least the pass score rests for the next
 *    cooldown_lessons - 1 lessons of the same course;
 * R3 a student whose latest score is below the pass score is eligible again
 *    and gets a higher weight;
 * R4 a student never asked in the course gets a higher weight;
 * R5 absent students are not drawn;
 * R6 when everybody present is resting, the students whose rest ends first
 *    are put back into the pool.
 */
class StudentPicker
{
    public function __construct(private Randomizer $randomizer) {}

    /**
     * Draw one student for the lesson.
     *
     * @param  array<int, int>  $excludeStudentIds
     *
     * @throws LessonFlowException
     */
    public function pick(Lesson $lesson, array $excludeStudentIds = []): PickCandidate
    {
        $candidates = $this->candidates($lesson, $excludeStudentIds);

        if ($candidates->isEmpty()) {
            throw LessonFlowException::noStudentsLeft();
        }

        $roll = $this->randomizer->getInt(1, $candidates->sum('weight'));

        foreach ($candidates as $candidate) {
            $roll -= $candidate->weight;

            if ($roll <= 0) {
                return $candidate;
            }
        }

        return $candidates->last();
    }

    /**
     * Everybody who may be drawn right now, with their weights.
     *
     * @param  array<int, int>  $excludeStudentIds
     * @return Collection<int, PickCandidate>
     */
    public function candidates(Lesson $lesson, array $excludeStudentIds = []): Collection
    {
        $students = $this->presentStudents($lesson)
            ->reject(fn (Student $student): bool => in_array($student->id, $excludeStudentIds, true))
            ->values();

        if ($students->isEmpty()) {
            return collect();
        }

        $history = $this->courseHistory($lesson, $students->pluck('id')->all());
        $passScore = (int) config('lessonloop.pass_score');
        $cooldown = (int) config('lessonloop.cooldown_lessons');

        $eligible = collect();
        $resting = collect();

        foreach ($students as $student) {
            $answers = $history->get($student->id, collect());
            $latest = $answers->first();
            $isDebtor = $latest !== null && $latest->score < $passScore;
            $lastPassSequence = $answers
                ->filter(fn (Answer $answer): bool => $answer->score >= $passScore)
                ->max(fn (Answer $answer): int => $answer->lesson->sequence);

            $isResting = ! $isDebtor
                && $lastPassSequence !== null
                && $lesson->sequence - $lastPassSequence < $cooldown;

            if ($isResting) {
                $resting->push(['student' => $student, 'last_pass_sequence' => $lastPassSequence]);

                continue;
            }

            $eligible->push(match (true) {
                $latest === null => new PickCandidate($student, (int) config('lessonloop.never_asked_weight'), PickCandidate::REASON_NEVER_ASKED),
                $isDebtor => new PickCandidate($student, (int) config('lessonloop.debtor_weight'), PickCandidate::REASON_DEBTOR),
                default => new PickCandidate($student, 1, PickCandidate::REASON_QUEUE),
            });
        }

        if ($eligible->isNotEmpty()) {
            return $eligible;
        }

        $earliestPass = $resting->min('last_pass_sequence');

        return $resting
            ->where('last_pass_sequence', $earliestPass)
            ->map(fn (array $entry): PickCandidate => new PickCandidate($entry['student'], 1, PickCandidate::REASON_COOLDOWN_RELAXED))
            ->values();
    }

    /**
     * Active students of the group who are present and not asked in this lesson yet.
     *
     * @return Collection<int, Student>
     */
    private function presentStudents(Lesson $lesson): Collection
    {
        $lesson->loadMissing('course');

        $absentIds = $lesson->attendances()->where('is_present', false)->pluck('student_id');
        $alreadyAskedIds = $lesson->answers()->pluck('student_id');

        return Student::query()
            ->active()
            ->where('study_group_id', $lesson->course->study_group_id)
            ->whereNotIn('id', $absentIds->merge($alreadyAskedIds)->unique()->all())
            ->orderBy('id')
            ->get();
    }

    /**
     * Scored answers of the given students in earlier lessons of the same course,
     * newest first, grouped by student.
     *
     * @param  array<int, int>  $studentIds
     * @return Collection<int, Collection<int, Answer>>
     */
    private function courseHistory(Lesson $lesson, array $studentIds): Collection
    {
        return Answer::query()
            ->with('lesson:id,sequence')
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', [AnswerStatus::Graded, AnswerStatus::NoAnswer])
            ->whereHas('lesson', fn ($query) => $query
                ->where('course_id', $lesson->course_id)
                ->where('sequence', '<', $lesson->sequence))
            ->get()
            ->sortByDesc(fn (Answer $answer): array => [$answer->lesson->sequence, $answer->id])
            ->groupBy('student_id');
    }
}
