<?php

namespace App\Services;

use App\Enums\AnswerStatus;
use App\Models\Answer;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Student;

/**
 * The course journal: one row per student, one column per lesson.
 * A cell is the score, "absent", "pending" or "not_asked".
 */
class JournalBuilder
{
    public const CELL_GRADED = 'graded';

    public const CELL_NO_ANSWER = 'no_answer';

    public const CELL_ABSENT = 'absent';

    public const CELL_PENDING = 'pending';

    public const CELL_NOT_ASKED = 'not_asked';

    /**
     * @return array{
     *     course: array{id: int, group: string, subject: string, subject_code: ?string},
     *     lessons: list<array{id: int, sequence: int, date: ?string}>,
     *     rows: list<array{student_id: int, full_name: string, is_active: bool, cells: list<array{lesson_id: int, status: string, score: ?int}>, average: ?int, asked_count: int}>
     * }
     */
    public function build(Course $course): array
    {
        $course->loadMissing(['studyGroup', 'subject']);

        $lessons = $course->lessons()->orderBy('sequence')->get();
        $lessonIds = $lessons->modelKeys();

        $answers = Answer::query()->whereIn('lesson_id', $lessonIds)->get()
            ->groupBy('student_id')
            ->map(fn ($studentAnswers) => $studentAnswers->keyBy('lesson_id'));

        $absent = Attendance::query()->whereIn('lesson_id', $lessonIds)->where('is_present', false)->get()
            ->mapWithKeys(fn (Attendance $attendance): array => [$attendance->student_id.':'.$attendance->lesson_id => true]);

        $students = $course->studyGroup->students()
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $answers->keys()->all()))
            ->orderBy('full_name')
            ->get();

        return [
            'course' => [
                'id' => $course->id,
                'group' => $course->studyGroup->name,
                'subject' => $course->subject->name,
                'subject_code' => $course->subject->code,
            ],
            'lessons' => $lessons->map(fn (Lesson $lesson): array => [
                'id' => $lesson->id,
                'sequence' => $lesson->sequence,
                'date' => $lesson->started_at?->toDateString(),
            ])->values()->all(),
            'rows' => $students->map(function (Student $student) use ($lessons, $answers, $absent): array {
                $studentAnswers = $answers->get($student->id, collect());

                $cells = $lessons->map(fn (Lesson $lesson): array => $this->cell(
                    $lesson,
                    $studentAnswers->get($lesson->id),
                    $absent->has($student->id.':'.$lesson->id),
                ))->values()->all();

                $scores = collect($cells)->whereNotNull('score')->pluck('score');

                return [
                    'student_id' => $student->id,
                    'full_name' => $student->full_name,
                    'is_active' => $student->is_active,
                    'cells' => $cells,
                    'average' => $scores->isEmpty() ? null : (int) round($scores->avg()),
                    'asked_count' => $scores->count(),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @return array{lesson_id: int, status: string, score: ?int}
     */
    private function cell(Lesson $lesson, ?Answer $answer, bool $isAbsent): array
    {
        $status = match (true) {
            $answer?->status === AnswerStatus::Graded => self::CELL_GRADED,
            $answer?->status === AnswerStatus::NoAnswer => self::CELL_NO_ANSWER,
            $answer?->status === AnswerStatus::Absent, $isAbsent => self::CELL_ABSENT,
            $answer?->status === AnswerStatus::Pending => self::CELL_PENDING,
            default => self::CELL_NOT_ASKED,
        };

        return [
            'lesson_id' => $lesson->id,
            'status' => $status,
            'score' => in_array($status, [self::CELL_GRADED, self::CELL_NO_ANSWER], true) ? $answer->score : null,
        ];
    }
}
