<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Answer;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_adds_a_student_to_a_group(): void
    {
        $group = StudyGroup::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(route('groups.students.store', $group), ['full_name' => 'Akmal Karimov'])
            ->assertCreated()
            ->assertJsonPath('data.study_group_id', $group->id);
    }

    public function test_teacher_lists_active_students_of_their_group(): void
    {
        $course = Course::factory()->create();
        Student::factory()->create(['study_group_id' => $course->study_group_id, 'full_name' => 'Bobur Rahimov']);
        Student::factory()->inactive()->create(['study_group_id' => $course->study_group_id]);
        Sanctum::actingAs($course->teacher);

        $this->getJson(route('groups.students.index', $course->study_group_id))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Bobur Rahimov');

        $this->getJson(route('groups.students.index', [$course->study_group_id, 'include_inactive' => 1]))->assertJsonCount(2, 'data');
    }

    public function test_returns_403_when_a_teacher_adds_a_student(): void
    {
        $course = Course::factory()->create();
        Sanctum::actingAs($course->teacher);

        $this->postJson(route('groups.students.store', $course->study_group_id), ['full_name' => 'Akmal Karimov'])->assertForbidden();
    }

    public function test_returns_403_when_a_teacher_lists_another_group(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('groups.students.index', StudyGroup::factory()->create()))->assertForbidden();
    }

    public function test_returns_409_when_deleting_a_student_with_grades(): void
    {
        $answer = Answer::factory()->graded(55)->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->deleteJson(route('students.destroy', $answer->student_id))->assertConflict();
    }

    public function test_course_view_shows_each_students_queue_state_and_counts(): void
    {
        $course = Course::factory()->create();
        [$fresh, $debtor, $resting] = Student::factory()->count(3)
            ->sequence(['full_name' => 'A Fresh'], ['full_name' => 'B Debtor'], ['full_name' => 'C Resting'])
            ->create(['study_group_id' => $course->study_group_id]);
        $lessonOne = Lesson::factory()->finished()->for($course)->create(['sequence' => 1]);
        Answer::factory()->graded(40)->for($lessonOne)->for($debtor)->create();
        Answer::factory()->graded(90)->for($lessonOne)->for($resting)->create();
        Sanctum::actingAs($course->teacher);

        $this->getJson(route('groups.students.index', [$course->study_group_id, 'course_id' => $course->id]))
            ->assertOk()
            ->assertJsonPath('meta.sequence', 2)
            ->assertJsonPath('meta.queue_counts', ['never_asked' => 1, 'queue' => 0, 'debtor' => 1, 'resting' => 1])
            ->assertJsonPath('data.0.queue.state', 'never_asked')
            ->assertJsonPath('data.1.queue.state', 'debtor')
            ->assertJsonPath('data.1.queue.last_score', 40)
            ->assertJsonPath('data.2.queue.state', 'resting')
            ->assertJsonPath('data.2.queue.rest_lessons_left', 3);

        $this->getJson(route('groups.students.index', [$course->study_group_id, 'course_id' => $course->id, 'queue_state' => 'debtor']))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $debtor->id);
    }

    public function test_returns_404_for_a_course_of_another_group(): void
    {
        $course = Course::factory()->create();
        $otherCourse = Course::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('groups.students.index', [$course->study_group_id, 'course_id' => $otherCourse->id]))->assertNotFound();
    }
}
