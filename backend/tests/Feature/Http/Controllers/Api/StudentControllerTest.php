<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Answer;
use App\Models\Course;
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
}
