<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Enums\AnswerStatus;
use App\Models\Answer;
use App\Models\Attendance;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsLessons;
use Tests\TestCase;

class AnswerControllerTest extends TestCase
{
    use BuildsLessons, LazilyRefreshDatabase;

    public function test_grading_saves_the_score_and_reports_a_pass(): void
    {
        [$teacher, $answer] = $this->pendingAnswer();
        Sanctum::actingAs($teacher);

        $this->postJson(route('answers.grade', $answer), ['score' => 72, 'comment' => 'PCB aytilmadi'])
            ->assertOk()
            ->assertJsonPath('data.status', AnswerStatus::Graded->value)
            ->assertJsonPath('data.score', 72)
            ->assertJsonPath('data.passed', true);
    }

    public function test_test_question_is_scored_from_the_option_the_student_named(): void
    {
        [$teacher, $answer] = $this->pendingAnswer();
        $answer->update(['question_id' => Question::factory()->test('C')->create()->id]);
        Sanctum::actingAs($teacher);

        $this->postJson(route('answers.grade', $answer), ['chosen_option' => 'B'])
            ->assertOk()
            ->assertJsonPath('data.score', 0)
            ->assertJsonPath('data.passed', false);
    }

    public function test_returns_422_for_a_score_above_100(): void
    {
        [$teacher, $answer] = $this->pendingAnswer();
        Sanctum::actingAs($teacher);

        $this->postJson(route('answers.grade', $answer), ['score' => 120])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('score');
    }

    public function test_returns_422_when_grading_an_already_graded_answer(): void
    {
        [$teacher, $answer] = $this->pendingAnswer();
        $answer->update(['status' => AnswerStatus::Graded, 'score' => 50]);
        Sanctum::actingAs($teacher);

        $this->postJson(route('answers.grade', $answer), ['score' => 90])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'answer_closed');
    }

    public function test_returns_403_when_another_teacher_grades(): void
    {
        [, $answer] = $this->pendingAnswer();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('answers.grade', $answer), ['score' => 90])->assertForbidden();
    }

    public function test_no_answer_is_stored_as_zero(): void
    {
        [$teacher, $answer] = $this->pendingAnswer();
        Sanctum::actingAs($teacher);

        $this->postJson(route('answers.skip', $answer), ['reason' => 'no_answer'])
            ->assertOk()
            ->assertJsonPath('data.status', AnswerStatus::NoAnswer->value)
            ->assertJsonPath('data.score', 0);
    }

    public function test_absent_student_gets_no_score_and_is_marked_absent(): void
    {
        [$teacher, $answer] = $this->pendingAnswer();
        Sanctum::actingAs($teacher);

        $this->postJson(route('answers.skip', $answer), ['reason' => 'absent'])
            ->assertOk()
            ->assertJsonPath('data.status', AnswerStatus::Absent->value)
            ->assertJsonPath('data.score', null);

        $this->assertFalse(Attendance::query()->where('student_id', $answer->student_id)->value('is_present'));
    }

    public function test_redraw_picks_another_student_for_the_same_question(): void
    {
        [$teacher, $answer] = $this->pendingAnswer();
        Sanctum::actingAs($teacher);

        $response = $this->postJson(route('answers.redraw', $answer))->assertCreated();

        $this->assertNotSame($answer->student_id, $response->json('data.student.id'));
        $this->assertSame($answer->question_id, $response->json('data.question.id'));
        $this->assertModelMissing($answer);
    }

    /**
     * @return array{0: User, 1: Answer}
     */
    private function pendingAnswer(): array
    {
        ['teacher' => $teacher, 'course' => $course, 'topic' => $topic, 'students' => $students] = $this->courseSetup();
        $lesson = Lesson::factory()->for($course)->create(['teacher_id' => $teacher->id]);
        $lesson->topics()->sync([$topic->id]);
        foreach ($students as $student) {
            Attendance::factory()->for($lesson)->for($student)->create();
        }

        $answer = Answer::factory()->for($lesson)->for($students[0])->create([
            'question_id' => $topic->questions()->first()->id,
        ]);

        return [$teacher, $answer];
    }
}
