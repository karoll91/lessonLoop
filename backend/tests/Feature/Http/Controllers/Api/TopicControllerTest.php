<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Answer;
use App\Models\Course;
use App\Models\Question;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TopicControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_teacher_adds_a_topic_at_the_end_of_their_subject(): void
    {
        $course = Course::factory()->create();
        Topic::factory()->create(['subject_id' => $course->subject_id, 'position' => 4]);
        Sanctum::actingAs($course->teacher);

        $this->postJson(route('subjects.topics.store', $course->subject_id), ['title' => 'Xotira boshqaruvi'])
            ->assertCreated()
            ->assertJsonPath('data.position', 5);
    }

    public function test_returns_403_when_a_teacher_adds_a_topic_to_another_subject(): void
    {
        $course = Course::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('subjects.topics.store', $course->subject_id), ['title' => 'Mavzu'])->assertForbidden();
    }

    public function test_returns_422_for_a_duplicate_title_in_the_subject(): void
    {
        $course = Course::factory()->create();
        Topic::factory()->create(['subject_id' => $course->subject_id, 'title' => 'Oqimlar']);
        Sanctum::actingAs($course->teacher);

        $this->postJson(route('subjects.topics.store', $course->subject_id), ['title' => 'Oqimlar'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_reorder_saves_positions_in_the_given_order(): void
    {
        $course = Course::factory()->create();
        [$first, $second] = Topic::factory()->count(2)->sequence(['position' => 1], ['position' => 2])->create(['subject_id' => $course->subject_id]);
        Sanctum::actingAs($course->teacher);

        $this->patchJson(route('subjects.topics.reorder', $course->subject_id), ['topic_ids' => [$second->id, $first->id]])
            ->assertOk()
            ->assertJsonPath('data.0.id', $second->id);

        $this->assertSame(2, $first->fresh()->position);
    }

    public function test_returns_409_when_deleting_a_topic_with_graded_questions(): void
    {
        $course = Course::factory()->create();
        $topic = Topic::factory()->create(['subject_id' => $course->subject_id]);
        Answer::factory()->graded(70)->for(Question::factory()->for($topic))->create();
        Sanctum::actingAs($course->teacher);

        $this->deleteJson(route('topics.destroy', $topic))->assertConflict();
    }
}
