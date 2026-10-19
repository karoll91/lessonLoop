<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_creates_a_teacher_and_gets_a_temporary_password_once(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->postJson(route('users.store'), ['name' => 'Olim Toshev', 'login' => 'o.toshev'])
            ->assertCreated()
            ->assertJsonPath('data.role', 'teacher')
            ->assertJsonPath('data.must_change_password', true);

        $teacher = User::query()->where('login', 'o.toshev')->firstOrFail();
        $this->assertTrue(Hash::check($response->json('temporary_password'), $teacher->password));
    }

    public function test_returns_422_for_a_taken_or_invalid_login(): void
    {
        User::factory()->create(['login' => 'n.rahimova']);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(route('users.store'), ['name' => 'X', 'login' => 'n.rahimova'])->assertJsonValidationErrors('login');
        $this->postJson(route('users.store'), ['name' => 'X', 'login' => 'Bo\'sh joy'])->assertJsonValidationErrors('login');
    }

    public function test_returns_403_when_a_teacher_lists_users(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('users.index'))->assertForbidden();
    }

    public function test_blocking_a_user_signs_them_out_and_stops_login(): void
    {
        $teacher = User::factory()->create(['login' => 'b.xolov']);
        $teacher->createToken('mobile');
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson(route('users.update', $teacher), ['blocked' => true])->assertOk()->assertJsonPath('data.blocked', true);

        $this->assertSame(0, $teacher->tokens()->count());
        $this->postJson(route('login'), ['login' => 'b.xolov', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('login');
    }

    public function test_admin_cannot_block_themselves(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->patchJson(route('users.update', $admin), ['blocked' => true])->assertJsonValidationErrors('blocked');
    }

    public function test_reset_password_returns_a_new_temporary_password(): void
    {
        $teacher = User::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $password = $this->postJson(route('users.reset-password', $teacher))->assertOk()->json('temporary_password');

        $teacher->refresh();
        $this->assertTrue(Hash::check($password, $teacher->password));
        $this->assertTrue($teacher->must_change_password);
    }

    public function test_returns_409_when_deleting_a_teacher_who_ran_lessons(): void
    {
        $course = Course::factory()->create();
        Lesson::factory()->for($course)->create(['teacher_id' => $course->teacher_id]);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->deleteJson(route('users.destroy', $course->teacher_id))->assertConflict();
    }
}
