<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Enums\AnswerStatus;
use App\Models\Answer;
use App\Models\Attendance;
use App\Models\Lesson;
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsLessons;
use Tests\TestCase;

class SyncControllerTest extends TestCase
{
    use BuildsLessons, LazilyRefreshDatabase;

    public function test_queued_grades_are_applied_in_order_with_the_time_they_were_made(): void
    {
        [$teacher, $first, $second] = $this->pendingAnswers();
        Sanctum::actingAs($teacher);
        $gradedAt = now()->subMinutes(3)->startOfSecond();

        $this->postJson(route('sync'), ['operations' => [
            $this->operation($first, ['type' => 'grade', 'score' => 80, 'performed_at' => $gradedAt->toIso8601String()]),
            $this->operation($second, ['type' => 'skip', 'reason' => 'no_answer']),
        ]])
            ->assertOk()
            ->assertJsonPath('results.0.status', SyncOperation::APPLIED)
            ->assertJsonPath('results.0.answer.score', 80)
            ->assertJsonPath('results.1.status', SyncOperation::APPLIED)
            ->assertJsonPath('results.1.answer.status', AnswerStatus::NoAnswer->value);

        $this->assertTrue($first->fresh()->graded_at->equalTo($gradedAt));
    }

    public function test_sending_the_same_operation_twice_applies_it_once(): void
    {
        [$teacher, $answer] = $this->pendingAnswers();
        Sanctum::actingAs($teacher);
        $operation = $this->operation($answer, ['type' => 'grade', 'score' => 90]);

        $this->postJson(route('sync'), ['operations' => [$operation]])->assertJsonPath('results.0.duplicate', false);
        $this->postJson(route('sync'), ['operations' => [$operation]])
            ->assertOk()
            ->assertJsonPath('results.0.status', SyncOperation::APPLIED)
            ->assertJsonPath('results.0.duplicate', true)
            ->assertJsonPath('results.0.answer.score', 90);

        $this->assertSame(1, SyncOperation::query()->count());
    }

    public function test_an_answer_that_was_already_closed_is_reported_and_the_rest_still_applies(): void
    {
        [$teacher, $closed, $pending] = $this->pendingAnswers();
        $closed->update(['status' => AnswerStatus::Graded, 'score' => 50]);
        Sanctum::actingAs($teacher);

        $this->postJson(route('sync'), ['operations' => [
            $this->operation($closed, ['type' => 'grade', 'score' => 90]),
            $this->operation($pending, ['type' => 'grade', 'score' => 70]),
        ]])
            ->assertOk()
            ->assertJsonPath('results.0.status', SyncOperation::FAILED)
            ->assertJsonPath('results.0.code', 'answer_closed')
            ->assertJsonPath('results.0.answer.score', 50)
            ->assertJsonPath('results.1.status', SyncOperation::APPLIED);
    }

    public function test_another_teachers_answer_is_not_changed(): void
    {
        [, $answer] = $this->pendingAnswers();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('sync'), ['operations' => [$this->operation($answer, ['type' => 'grade', 'score' => 90])]])
            ->assertOk()
            ->assertJsonPath('results.0.status', SyncOperation::FAILED)
            ->assertJsonPath('results.0.code', 'forbidden');

        $this->assertSame(AnswerStatus::Pending, $answer->fresh()->status);
    }

    public function test_returns_422_for_a_skip_without_a_reason(): void
    {
        [$teacher, $answer] = $this->pendingAnswers();
        Sanctum::actingAs($teacher);

        $this->postJson(route('sync'), ['operations' => [$this->operation($answer, ['type' => 'skip'])]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('operations.0.reason');
    }

    public function test_a_retried_online_grade_with_the_same_client_uuid_is_applied_once(): void
    {
        [$teacher, $answer] = $this->pendingAnswers();
        Sanctum::actingAs($teacher);
        $uuid = (string) Str::uuid();

        $this->postJson(route('answers.grade', $answer), ['score' => 65, 'client_uuid' => $uuid])->assertOk();
        $this->postJson(route('answers.grade', $answer), ['score' => 65, 'client_uuid' => $uuid])
            ->assertOk()
            ->assertJsonPath('data.score', 65);

        // Without the uuid the second call is a real second grade and is refused.
        $this->postJson(route('answers.grade', $answer), ['score' => 65])->assertUnprocessable()->assertJsonPath('code', 'answer_closed');
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function operation(Answer $answer, array $fields): array
    {
        return ['client_uuid' => (string) Str::uuid(), 'answer_id' => $answer->id] + $fields;
    }

    /**
     * @return array{0: User, 1: Answer, 2: Answer}
     */
    private function pendingAnswers(): array
    {
        ['teacher' => $teacher, 'course' => $course, 'topic' => $topic, 'students' => $students] = $this->courseSetup();
        $lesson = Lesson::factory()->for($course)->create(['teacher_id' => $teacher->id]);
        $lesson->topics()->sync([$topic->id]);
        foreach ($students as $student) {
            Attendance::factory()->for($lesson)->for($student)->create();
        }

        $questions = $topic->questions()->get();
        $first = Answer::factory()->for($lesson)->for($students[0])->create(['question_id' => $questions[0]->id, 'created_at' => now()->subMinutes(10)]);
        $second = Answer::factory()->for($lesson)->for($students[1])->create(['question_id' => $questions[1]->id]);

        return [$teacher, $first, $second];
    }
}
