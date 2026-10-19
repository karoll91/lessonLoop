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

class QuestionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_teacher_adds_an_open_question_with_a_model_answer(): void
    {
        [$teacher, $topic] = $this->teacherTopic();
        Sanctum::actingAs($teacher);

        $this->postJson(route('topics.questions.store', $topic), [
            'type' => 'open',
            'text' => 'Jarayon nima?',
            'answer_text' => 'Bajarilayotgan dastur nusxasi.',
            'difficulty' => 'easy',
        ])->assertCreated()
            ->assertJsonPath('data.has_answer', true)
            ->assertJsonPath('data.options', []);
    }

    public function test_test_question_is_saved_with_its_options(): void
    {
        [$teacher, $topic] = $this->teacherTopic();
        Sanctum::actingAs($teacher);

        $this->postJson(route('topics.questions.store', $topic), [
            'type' => 'test',
            'text' => 'Qaysi chaqiruv jarayon yaratadi?',
            'options' => [
                ['label' => 'A', 'text' => 'exec()'],
                ['label' => 'B', 'text' => 'fork()', 'is_correct' => true],
            ],
        ])->assertCreated()
            ->assertJsonCount(2, 'data.options')
            ->assertJsonPath('data.options.1.is_correct', true);
    }

    public function test_returns_422_for_a_test_question_without_options(): void
    {
        [$teacher, $topic] = $this->teacherTopic();
        Sanctum::actingAs($teacher);

        $this->postJson(route('topics.questions.store', $topic), ['type' => 'test', 'text' => 'Savol?'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('options');
    }

    public function test_returns_422_when_two_options_are_marked_correct(): void
    {
        [$teacher, $topic] = $this->teacherTopic();
        Sanctum::actingAs($teacher);

        $this->postJson(route('topics.questions.store', $topic), [
            'type' => 'test',
            'text' => 'Savol?',
            'options' => [
                ['label' => 'A', 'text' => 'Bir', 'is_correct' => true],
                ['label' => 'B', 'text' => 'Ikki', 'is_correct' => true],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('options');
    }

    public function test_changing_a_test_question_to_open_removes_its_options(): void
    {
        [$teacher, $topic] = $this->teacherTopic();
        $question = Question::factory()->test('B')->for($topic)->create();
        Sanctum::actingAs($teacher);

        $this->patchJson(route('questions.update', $question), ['type' => 'open', 'answer_text' => 'Javob'])
            ->assertOk()
            ->assertJsonPath('data.type', 'open');

        $this->assertSame(0, $question->options()->count());
    }

    public function test_returns_403_when_a_teacher_edits_a_question_of_another_subject(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson(route('questions.update', Question::factory()->create()), ['text' => 'Yangi'])->assertForbidden();
    }

    public function test_filter_lists_only_open_questions_without_an_answer(): void
    {
        [$teacher, $topic] = $this->teacherTopic();
        Question::factory()->for($topic)->create();
        $missing = Question::factory()->for($topic)->create(['answer_text' => null]);
        Question::factory()->test()->for($topic)->create();
        Sanctum::actingAs($teacher);

        $this->getJson(route('topics.questions.index', [$topic, 'without_answer' => 1]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $missing->id);
    }

    public function test_search_finds_questions_across_the_subject_topics(): void
    {
        [$teacher, $topic] = $this->teacherTopic();
        $otherTopic = Topic::factory()->create(['subject_id' => $topic->subject_id]);
        Question::factory()->for($topic)->create(['text' => 'Semafor nima?']);
        Question::factory()->for($otherTopic)->create(['text' => 'Mutex va semafor farqi?']);
        Question::factory()->for($otherTopic)->create(['text' => 'Deadlock nima?']);
        Sanctum::actingAs($teacher);

        $this->getJson(route('subjects.questions.search', [$topic->subject_id, 'q' => 'semafor']))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_returns_409_when_deleting_a_question_that_was_graded(): void
    {
        [$teacher, $topic] = $this->teacherTopic();
        $question = Question::factory()->for($topic)->create();
        Answer::factory()->graded(80)->for($question)->create();
        Sanctum::actingAs($teacher);

        $this->deleteJson(route('questions.destroy', $question))->assertConflict();
    }

    /**
     * @return array{0: User, 1: Topic}
     */
    private function teacherTopic(): array
    {
        $course = Course::factory()->create();

        return [$course->teacher, Topic::factory()->create(['subject_id' => $course->subject_id])];
    }
}
