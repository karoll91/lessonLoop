<?php

namespace Tests\Feature\Services;

use App\Exceptions\LessonFlowException;
use App\Models\Answer;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Student;
use App\Services\PickCandidate;
use App\Services\QueueStatusResolver;
use App\Services\StudentPicker;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

class StudentPickerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_students_never_asked_in_the_course_get_double_weight(): void
    {
        [$course, $students] = $this->courseWithStudents(2);
        $lesson = Lesson::factory()->for($course)->create(['sequence' => 1]);

        $candidates = $this->picker()->candidates($lesson);

        $this->assertSame($students->pluck('id')->all(), $this->ids($candidates));
        $this->assertSame([2, 2], $candidates->pluck('weight')->all());
        $this->assertSame([PickCandidate::REASON_NEVER_ASKED, PickCandidate::REASON_NEVER_ASKED], $candidates->pluck('reason')->all());
    }

    public function test_student_already_asked_in_this_lesson_is_not_drawn_again(): void
    {
        [$course, $students] = $this->courseWithStudents(2);
        $lesson = Lesson::factory()->for($course)->create(['sequence' => 1]);
        Answer::factory()->for($lesson)->for($students[0])->create();

        $this->assertSame([$students[1]->id], $this->ids($this->picker()->candidates($lesson)));
    }

    public function test_passing_student_rests_for_three_lessons_and_returns_in_the_fourth(): void
    {
        [$course, $students] = $this->courseWithStudents(2);
        $this->scoredIn($course, sequence: 1, student: $students[0], score: 85);

        foreach ([2, 3, 4] as $sequence) {
            $lesson = Lesson::factory()->for($course)->create(['sequence' => $sequence]);
            $this->assertNotContains($students[0]->id, $this->ids($this->picker()->candidates($lesson)), "Lesson {$sequence}");
        }

        $lessonFive = Lesson::factory()->for($course)->create(['sequence' => 5]);
        $returning = $this->picker()->candidates($lessonFive)
            ->first(fn (PickCandidate $candidate): bool => $candidate->student->is($students[0]));

        $this->assertNotNull($returning);
        $this->assertSame(1, $returning->weight);
        $this->assertSame(PickCandidate::REASON_QUEUE, $returning->reason);
    }

    public function test_student_below_pass_score_returns_next_lesson_with_double_weight(): void
    {
        [$course, $students] = $this->courseWithStudents(2);
        $this->scoredIn($course, sequence: 1, student: $students[0], score: 45);
        $this->scoredIn($course, sequence: 1, student: $students[1], score: 60);
        $lesson = Lesson::factory()->for($course)->create(['sequence' => 2]);

        $candidates = $this->picker()->candidates($lesson);

        $this->assertSame([$students[0]->id], $this->ids($candidates));
        $this->assertSame(2, $candidates->first()->weight);
        $this->assertSame(PickCandidate::REASON_DEBTOR, $candidates->first()->reason);
    }

    public function test_no_answer_counts_as_debt(): void
    {
        [$course, $students] = $this->courseWithStudents(1);
        $earlier = Lesson::factory()->for($course)->create(['sequence' => 1]);
        Answer::factory()->noAnswer()->for($earlier)->for($students[0])->create();
        $lesson = Lesson::factory()->for($course)->create(['sequence' => 2]);

        $this->assertSame(PickCandidate::REASON_DEBTOR, $this->picker()->candidates($lesson)->first()->reason);
    }

    public function test_absent_and_archived_students_are_not_drawn(): void
    {
        [$course, $students] = $this->courseWithStudents(3);
        $students[2]->update(['is_active' => false]);
        $lesson = Lesson::factory()->for($course)->create(['sequence' => 1]);
        Attendance::factory()->absent()->for($lesson)->for($students[0])->create();

        $this->assertSame([$students[1]->id], $this->ids($this->picker()->candidates($lesson)));
    }

    public function test_falls_back_to_students_whose_rest_ends_first_when_everybody_rests(): void
    {
        [$course, $students] = $this->courseWithStudents(3);
        $this->scoredIn($course, sequence: 1, student: $students[0], score: 90);
        $this->scoredIn($course, sequence: 2, student: $students[1], score: 90);
        $this->scoredIn($course, sequence: 2, student: $students[2], score: 70);
        $lesson = Lesson::factory()->for($course)->create(['sequence' => 3]);

        $candidates = $this->picker()->candidates($lesson);

        $this->assertSame([$students[0]->id], $this->ids($candidates));
        $this->assertSame(PickCandidate::REASON_COOLDOWN_RELAXED, $candidates->first()->reason);
    }

    public function test_history_of_another_course_does_not_count(): void
    {
        [$course, $students] = $this->courseWithStudents(1);
        $otherCourse = Course::factory()->create(['study_group_id' => $course->study_group_id]);
        $this->scoredIn($otherCourse, sequence: 1, student: $students[0], score: 95);
        $lesson = Lesson::factory()->for($course)->create(['sequence' => 2]);

        $this->assertSame(PickCandidate::REASON_NEVER_ASKED, $this->picker()->candidates($lesson)->first()->reason);
    }

    public function test_pick_draws_double_weight_students_about_twice_as_often(): void
    {
        [$course, $students] = $this->courseWithStudents(2);
        $this->scoredIn($course, sequence: 1, student: $students[0], score: 30);
        $this->scoredIn($course, sequence: 1, student: $students[1], score: 70);
        $lesson = Lesson::factory()->for($course)->create(['sequence' => 9]);
        $picker = $this->picker();

        $debtorPicks = 0;
        for ($i = 0; $i < 600; $i++) {
            $debtorPicks += $picker->pick($lesson)->student->is($students[0]) ? 1 : 0;
        }

        $this->assertEqualsWithDelta(400, $debtorPicks, 45);
    }

    public function test_pick_throws_when_nobody_is_left(): void
    {
        [$course, $students] = $this->courseWithStudents(1);
        $lesson = Lesson::factory()->for($course)->create(['sequence' => 1]);
        Answer::factory()->for($lesson)->for($students[0])->create();

        $this->expectException(LessonFlowException::class);

        $this->picker()->pick($lesson);
    }

    private function picker(): StudentPicker
    {
        return new StudentPicker(new QueueStatusResolver, new Randomizer(new Mt19937(2026)));
    }

    /**
     * @return array{0: Course, 1: Collection<int, Student>}
     */
    private function courseWithStudents(int $count): array
    {
        $course = Course::factory()->create();
        $students = Student::factory()->count($count)->create(['study_group_id' => $course->study_group_id]);

        return [$course, $students];
    }

    private function scoredIn(Course $course, int $sequence, Student $student, int $score): void
    {
        $lesson = Lesson::query()->where('course_id', $course->id)->where('sequence', $sequence)->first()
            ?? Lesson::factory()->finished()->for($course)->create(['sequence' => $sequence]);

        Answer::factory()->graded($score)->for($lesson)->for($student)->create();
    }

    /**
     * @param  Collection<int, PickCandidate>  $candidates
     * @return array<int, int>
     */
    private function ids(Collection $candidates): array
    {
        return $candidates->map(fn (PickCandidate $candidate): int => $candidate->student->id)->all();
    }
}
