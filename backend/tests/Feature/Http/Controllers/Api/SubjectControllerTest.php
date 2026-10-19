<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Answer;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubjectControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_creates_a_subject(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(route('subjects.store'), ['name' => 'Operatsion tizimlar', 'code' => 'OTX2506'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'OTX2506');
    }

    public function test_returns_422_for_a_duplicate_code(): void
    {
        Subject::factory()->create(['code' => 'OTX2506']);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(route('subjects.store'), ['name' => 'Boshqa', 'code' => 'OTX2506'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_show_lists_topics_with_question_and_missing_answer_counts(): void
    {
        $subject = Subject::factory()->create();
        $topic = Topic::factory()->for($subject)->create();
        Question::factory()->for($topic)->create();
        Question::factory()->for($topic)->create(['answer_text' => null]);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('subjects.show', $subject))
            ->assertOk()
            ->assertJsonPath('data.topics.0.questions_count', 2)
            ->assertJsonPath('data.topics.0.questions_without_answer_count', 1);
    }

    public function test_returns_403_when_a_teacher_opens_a_subject_they_do_not_teach(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('subjects.show', Subject::factory()->create()))->assertForbidden();
    }

    public function test_returns_409_when_deleting_a_subject_with_grades(): void
    {
        $answer = Answer::factory()->graded(80)->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->deleteJson(route('subjects.destroy', $answer->question->topic->subject_id))->assertConflict();
    }
}
