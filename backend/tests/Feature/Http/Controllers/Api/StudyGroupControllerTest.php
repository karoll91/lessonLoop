<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudyGroupControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_creates_a_group_and_gets_201(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(route('groups.store'), ['name' => 'KI-21', 'course_year' => 3, 'academic_year' => '2025-2026', 'semester' => 1])
            ->assertCreated()
            ->assertJsonPath('data.name', 'KI-21')
            ->assertJsonPath('data.archived', false);
    }

    public function test_returns_422_for_a_duplicate_name_in_the_same_academic_year(): void
    {
        StudyGroup::factory()->create(['name' => 'KI-21', 'academic_year' => '2025-2026']);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(route('groups.store'), ['name' => 'KI-21', 'academic_year' => '2025-2026'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_returns_403_when_a_teacher_creates_a_group(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('groups.store'), ['name' => 'KI-21'])->assertForbidden();
    }

    public function test_teacher_lists_only_the_groups_of_their_courses(): void
    {
        $teacher = User::factory()->create();
        $own = Course::factory()->for($teacher, 'teacher')->create()->studyGroup;
        StudyGroup::factory()->create();
        Sanctum::actingAs($teacher);

        $this->getJson(route('groups.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_archived_groups_are_listed_only_on_request(): void
    {
        $admin = User::factory()->admin()->create();
        $group = StudyGroup::factory()->create();
        Sanctum::actingAs($admin);

        $this->patchJson(route('groups.update', $group), ['archived' => true])->assertOk()->assertJsonPath('data.archived', true);

        $this->getJson(route('groups.index'))->assertJsonCount(0, 'data');
        $this->getJson(route('groups.index', ['archived' => 1]))->assertJsonCount(1, 'data');
    }

    public function test_returns_409_when_deleting_a_group_with_lessons(): void
    {
        $course = Course::factory()->create();
        Lesson::factory()->for($course)->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->deleteJson(route('groups.destroy', $course->study_group_id))
            ->assertConflict()
            ->assertJsonPath('code', 'resource_in_use');
    }

    public function test_deletes_a_group_without_lessons(): void
    {
        $group = StudyGroup::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->deleteJson(route('groups.destroy', $group))->assertNoContent();

        $this->assertModelMissing($group);
    }
}
