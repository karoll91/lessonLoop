<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\StudyGroup;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_assigns_a_subject_and_teacher_to_a_group(): void
    {
        $teacher = User::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(route('courses.store'), [
            'study_group_id' => StudyGroup::factory()->create()->id,
            'subject_id' => Subject::factory()->create()->id,
            'teacher_id' => $teacher->id,
        ])->assertCreated()->assertJsonPath('data.teacher.id', $teacher->id);
    }

    public function test_returns_422_when_the_subject_is_already_assigned_to_the_group(): void
    {
        $course = Course::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(route('courses.store'), [
            'study_group_id' => $course->study_group_id,
            'subject_id' => $course->subject_id,
        ])->assertUnprocessable()->assertJsonValidationErrors('subject_id');
    }

    public function test_teacher_lists_only_their_own_courses(): void
    {
        $own = Course::factory()->create();
        Course::factory()->create();
        Sanctum::actingAs($own->teacher);

        $this->getJson(route('courses.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_returns_403_when_a_teacher_creates_a_course(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('courses.store'), [
            'study_group_id' => StudyGroup::factory()->create()->id,
            'subject_id' => Subject::factory()->create()->id,
        ])->assertForbidden();
    }

    public function test_returns_409_when_deleting_a_course_with_lessons(): void
    {
        $course = Course::factory()->create();
        Lesson::factory()->for($course)->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->deleteJson(route('courses.destroy', $course))->assertConflict();
    }
}
