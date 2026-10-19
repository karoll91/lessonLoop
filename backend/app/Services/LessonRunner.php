<?php

namespace App\Services;

use App\Enums\AnswerStatus;
use App\Enums\LessonStatus;
use App\Enums\QuestionType;
use App\Exceptions\LessonFlowException;
use App\Models\Answer;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Runs one lesson: start, draw student and question, grade, skip, finish.
 */
class LessonRunner
{
    public function __construct(
        private StudentPicker $studentPicker,
        private QuestionPicker $questionPicker,
    ) {}

    /**
     * Start a new lesson for the course.
     *
     * @param  array<int, int>  $topicIds
     * @param  array<int, int>  $absentStudentIds
     */
    public function start(Course $course, User $teacher, array $topicIds, int $questionCount, array $absentStudentIds = []): Lesson
    {
        $available = $this->questionPicker->countForTopics($topicIds);

        if ($available < $questionCount) {
            throw ValidationException::withMessages([
                'question_count' => "Tanlangan mavzularda faqat {$available} ta savol bor.",
            ]);
        }

        return DB::transaction(function () use ($course, $teacher, $topicIds, $questionCount, $absentStudentIds): Lesson {
            $lesson = $course->lessons()->create([
                'teacher_id' => $teacher->id,
                'sequence' => $course->nextLessonSequence(),
                'question_count' => $questionCount,
                'status' => LessonStatus::Active,
                'started_at' => now(),
            ]);

            $lesson->topics()->sync($topicIds);

            $studentIds = $course->studyGroup->students()->active()->pluck('id');

            $lesson->attendances()->createMany($studentIds->map(fn (int $studentId): array => [
                'student_id' => $studentId,
                'is_present' => ! in_array($studentId, $absentStudentIds, true),
            ])->all());

            return $lesson;
        });
    }

    /**
     * Draw the next student and question.
     *
     * @throws LessonFlowException
     */
    public function draw(Lesson $lesson): Answer
    {
        $this->ensureActive($lesson);

        if ($this->pendingAnswer($lesson) !== null) {
            throw LessonFlowException::answerPending();
        }

        if ($this->askedCount($lesson) >= $lesson->question_count) {
            throw LessonFlowException::questionLimitReached();
        }

        return $this->createAnswer($lesson);
    }

    /**
     * Put the current student back and draw somebody else for the same question.
     *
     * @throws LessonFlowException
     */
    public function redraw(Answer $answer): Answer
    {
        $this->ensurePending($answer);

        $lesson = $answer->lesson;
        $previousStudentId = $answer->student_id;
        $questionId = $answer->question_id;

        return DB::transaction(function () use ($answer, $lesson, $previousStudentId, $questionId): Answer {
            $answer->delete();

            return $this->createAnswer($lesson, [$previousStudentId], $questionId);
        });
    }

    /**
     * Grade the pending answer. For test questions the score may be derived
     * from the option the student named.
     *
     * @throws LessonFlowException
     */
    public function grade(Answer $answer, ?int $score, ?string $comment = null, ?string $chosenOption = null): Answer
    {
        $this->ensurePending($answer);

        $question = $answer->question()->with('options')->firstOrFail();

        if ($score === null && $question->type === QuestionType::Test && $chosenOption !== null) {
            $score = $question->correctOptionLabel() === $chosenOption ? 100 : 0;
        }

        if ($score === null) {
            throw ValidationException::withMessages(['score' => 'Ball kiritilmagan.']);
        }

        $answer->update([
            'status' => AnswerStatus::Graded,
            'score' => $score,
            'chosen_option' => $chosenOption,
            'comment' => $comment,
            'graded_at' => now(),
        ]);

        return $answer;
    }

    /**
     * Change a grade after the fact, keeping an audit record. Teachers may do
     * so within grade_edit_hours of grading; admins at any time.
     *
     * @throws LessonFlowException
     */
    public function regrade(Answer $answer, User $editor, int $score, string $reason): Answer
    {
        if (! $answer->status->isScored()) {
            throw LessonFlowException::answerNotGraded();
        }

        if (! $editor->isAdmin() && $answer->editableUntil()?->isPast() !== false) {
            throw LessonFlowException::editWindowClosed();
        }

        DB::transaction(function () use ($answer, $editor, $score, $reason): void {
            $answer->edits()->create([
                'user_id' => $editor->id,
                'old_status' => $answer->status,
                'new_status' => AnswerStatus::Graded,
                'old_score' => $answer->score,
                'new_score' => $score,
                'reason' => $reason,
            ]);

            $answer->update(['status' => AnswerStatus::Graded, 'score' => $score]);
        });

        return $answer;
    }

    /**
     * The student did not answer: scored 0 and stays in the queue (R8).
     */
    public function markNoAnswer(Answer $answer, ?string $comment = null): Answer
    {
        $this->ensurePending($answer);

        $answer->update([
            'status' => AnswerStatus::NoAnswer,
            'score' => 0,
            'comment' => $comment,
            'graded_at' => now(),
        ]);

        return $answer;
    }

    /**
     * The student turned out to be absent: no score, queue position kept (R8).
     */
    public function markAbsent(Answer $answer): Answer
    {
        $this->ensurePending($answer);

        DB::transaction(function () use ($answer): void {
            $answer->update(['status' => AnswerStatus::Absent, 'score' => null]);

            $answer->lesson->attendances()->updateOrCreate(
                ['student_id' => $answer->student_id],
                ['is_present' => false],
            );
        });

        return $answer;
    }

    public function finish(Lesson $lesson): Lesson
    {
        $lesson->answers()->where('status', AnswerStatus::Pending)->delete();

        $lesson->update([
            'status' => LessonStatus::Finished,
            'finished_at' => now(),
        ]);

        return $lesson;
    }

    public function pendingAnswer(Lesson $lesson): ?Answer
    {
        return $lesson->answers()->where('status', AnswerStatus::Pending)->latest('id')->first();
    }

    /**
     * Questions already used in the lesson, not counting absent students.
     */
    public function askedCount(Lesson $lesson): int
    {
        return $lesson->answers()->where('status', '!=', AnswerStatus::Absent)->count();
    }

    /**
     * @param  array<int, int>  $excludeStudentIds
     */
    private function createAnswer(Lesson $lesson, array $excludeStudentIds = [], ?int $questionId = null): Answer
    {
        $candidate = $this->studentPicker->pick($lesson, $excludeStudentIds);
        $questionId ??= $this->questionPicker->pick($lesson)->id;

        return $lesson->answers()->create([
            'student_id' => $candidate->student->id,
            'question_id' => $questionId,
            'status' => AnswerStatus::Pending,
            'pick_weight' => $candidate->weight,
            'pick_reason' => $candidate->reason,
        ]);
    }

    private function ensureActive(Lesson $lesson): void
    {
        if ($lesson->status !== LessonStatus::Active) {
            throw LessonFlowException::lessonNotActive();
        }
    }

    private function ensurePending(Answer $answer): void
    {
        if ($answer->status !== AnswerStatus::Pending) {
            throw LessonFlowException::answerAlreadyClosed();
        }
    }
}
