<?php

namespace App\Services;

use App\Enums\LessonStatus;
use App\Enums\QuestionType;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Home screen data (M3, W2): today's lessons, the lesson in progress,
 * debtors and students not asked yet, across the user's courses.
 */
class DashboardBuilder
{
    private const DEBTOR_LIMIT = 10;

    public function __construct(private QueueStatusResolver $queue) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user): array
    {
        $courses = Course::query()
            ->with(['studyGroup', 'subject'])
            ->when(! $user->isAdmin(), fn ($query) => $query->where('teacher_id', $user->id))
            ->whereHas('studyGroup', fn ($groups) => $groups->whereNull('archived_at'))
            ->get();

        $groupIds = $courses->pluck('study_group_id')->unique()->values()->all();
        $subjectIds = $courses->pluck('subject_id')->unique()->values()->all();

        [$debtors, $neverAskedCount] = $this->queueSummary($courses);

        return [
            'counts' => [
                'groups' => count($groupIds),
                'students' => Student::query()->active()->whereIn('study_group_id', $groupIds)->count(),
                'questions' => Question::query()->whereHas('topic', fn ($topics) => $topics->whereIn('subject_id', $subjectIds))->count(),
                'questions_without_answer' => Question::query()
                    ->whereHas('topic', fn ($topics) => $topics->whereIn('subject_id', $subjectIds))
                    ->where('type', QuestionType::Open)
                    ->where(fn ($empty) => $empty->whereNull('answer_text')->orWhere('answer_text', ''))
                    ->count(),
                'never_asked' => $neverAskedCount,
            ],
            'running_lesson' => $this->lessonSummary(
                Lesson::query()->visibleTo($user)->withSummary()->with(['course.studyGroup', 'course.subject'])
                    ->whereIn('status', [LessonStatus::Active, LessonStatus::Paused])
                    ->latest('started_at')
                    ->first(),
            ),
            'today_lessons' => Lesson::query()->visibleTo($user)->withSummary()->with(['course.studyGroup', 'course.subject'])
                ->whereDate('started_at', today())
                ->orderBy('started_at')
                ->get()
                ->map(fn (Lesson $lesson): array => $this->lessonSummary($lesson))
                ->all(),
            'debtors' => $debtors,
        ];
    }

    /**
     * @param  Collection<int, Course>  $courses
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function queueSummary($courses): array
    {
        $debtors = collect();
        $neverAskedCount = 0;

        foreach ($courses as $course) {
            $students = $course->studyGroup->students()->active()->get()->keyBy('id');
            $statuses = $this->queue->forStudents($course, $this->queue->currentSequence($course), $students->keys()->all());

            $neverAskedCount += $statuses->where('state', QueueStatus::NEVER_ASKED)->count();

            foreach ($statuses->where('state', QueueStatus::DEBTOR) as $studentId => $status) {
                $debtors->push([
                    'student_id' => $studentId,
                    'full_name' => $students->get($studentId)->full_name,
                    'course_id' => $course->id,
                    'group' => $course->studyGroup->name,
                    'subject' => $course->subject->name,
                    'last_score' => $status->lastScore,
                ]);
            }
        }

        return [$debtors->sortBy('last_score')->take(self::DEBTOR_LIMIT)->values()->all(), $neverAskedCount];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lessonSummary(?Lesson $lesson): ?array
    {
        if ($lesson === null) {
            return null;
        }

        return [
            'id' => $lesson->id,
            'course_id' => $lesson->course_id,
            'group' => $lesson->course->studyGroup->name,
            'subject' => $lesson->course->subject->name,
            'sequence' => $lesson->sequence,
            'status' => $lesson->status->value,
            'started_at' => $lesson->started_at?->toIso8601String(),
            'question_count' => $lesson->question_count,
            'asked_count' => (int) $lesson->asked_count,
            'average_score' => $lesson->average_score === null ? null : (int) round((float) $lesson->average_score),
        ];
    }
}
