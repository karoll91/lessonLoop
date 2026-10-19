<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Answer;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_the_default_rules(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('settings.show'))
            ->assertOk()
            ->assertJsonPath('data.pass_score', 60)
            ->assertJsonPath('data.cooldown_lessons', 4)
            ->assertJsonPath('limits.pass_score.max', 100);
    }

    public function test_a_raised_pass_score_turns_a_65_into_a_debt(): void
    {
        $course = Course::factory()->create();
        $student = Student::factory()->create(['study_group_id' => $course->study_group_id]);
        $lesson = Lesson::factory()->finished()->for($course)->create(['sequence' => 1]);
        Answer::factory()->graded(65)->for($lesson)->for($student)->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson(route('settings.update'), ['pass_score' => 70])
            ->assertOk()
            ->assertJsonPath('data.pass_score', 70)
            ->assertJsonPath('defaults.pass_score', 60);

        $this->getJson(route('groups.students.index', [$course->study_group_id, 'course_id' => $course->id]))
            ->assertJsonPath('data.0.queue.state', 'debtor');
    }

    public function test_reset_returns_to_the_defaults(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->patchJson(route('settings.update'), ['cooldown_lessons' => 3])->assertOk();

        $this->deleteJson(route('settings.reset'))->assertOk()->assertJsonPath('data.cooldown_lessons', 4);
    }

    public function test_returns_422_for_a_value_out_of_range(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson(route('settings.update'), ['pass_score' => 150])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pass_score');
    }

    public function test_returns_403_when_a_teacher_changes_the_rules(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson(route('settings.update'), ['pass_score' => 70])->assertForbidden();
    }
}
