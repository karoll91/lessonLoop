<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Enums\AnswerStatus;
use App\Enums\LessonStatus;
use App\Models\Answer;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsLessons;
use Tests\TestCase;

class LessonControllerTest extends TestCase
{
    use BuildsLessons, LazilyRefreshDatabase;

    public function test_teacher_starts_a_lesson_with_the_next_sequence_and_attendance(): void
    {
        ['teacher' => $teacher, 'course' => $course, 'topic' => $topic, 'students' => $students] = $this->courseSetup();
        Lesson::factory()->finished()->for($course)->create(['sequence' => 4]);
        Sanctum::actingAs($teacher);

        $response = $this->postJson(route('lessons.store', $course), [
            'topic_ids' => [$topic->id],
            'question_count' => 3,
            'absent_student_ids' => [$students[0]->id],
        ]);

        $response->assertCreated()->assertJsonPath('data.sequence', 5)->assertJsonPath('data.topic_ids', [$topic->id]);
        $lesson = Lesson::query()->findOrFail($response->json('data.id'));
        $this->assertSame([false, true, true], $lesson->attendances()->orderBy('student_id')->pluck('is_present')->all());
    }

    public function test_returns_422_when_topics_hold_fewer_questions_than_requested(): void
    {
        ['teacher' => $teacher, 'course' => $course, 'topic' => $topic] = $this->courseSetup(questionCount: 2);
        Sanctum::actingAs($teacher);

        $this->postJson(route('lessons.store', $course), ['topic_ids' => [$topic->id], 'question_count' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('question_count');
    }

    public function test_returns_403_when_another_teacher_starts_a_lesson(): void
    {
        ['course' => $course, 'topic' => $topic] = $this->courseSetup();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('lessons.store', $course), ['topic_ids' => [$topic->id], 'question_count' => 1])
            ->assertForbidden();
    }

    public function test_returns_401_without_a_token(): void
    {
        ['course' => $course, 'topic' => $topic] = $this->courseSetup();

        $this->postJson(route('lessons.store', $course), ['topic_ids' => [$topic->id], 'question_count' => 1])
            ->assertUnauthorized();
    }

    public function test_draw_creates_a_pending_answer_with_the_correct_answer_for_the_teacher(): void
    {
        ['teacher' => $teacher, 'course' => $course] = $this->courseSetup();
        $lesson = $this->startedLesson($teacher, $course);

        $response = $this->postJson(route('lessons.draw', $lesson));

        $response->assertCreated()
            ->assertJsonPath('data.status', AnswerStatus::Pending->value)
            ->assertJsonPath('data.pick_reason', 'never_asked');
        $this->assertNotNull($response->json('data.question.answer_text'));
    }

    public function test_draw_returns_422_while_the_current_answer_is_not_graded(): void
    {
        ['teacher' => $teacher, 'course' => $course] = $this->courseSetup();
        $lesson = $this->startedLesson($teacher, $course);
        $this->postJson(route('lessons.draw', $lesson))->assertCreated();

        $this->postJson(route('lessons.draw', $lesson))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'answer_pending');
    }

    public function test_draw_returns_422_after_the_question_count_is_reached(): void
    {
        ['teacher' => $teacher, 'course' => $course] = $this->courseSetup();
        $lesson = $this->startedLesson($teacher, $course, questionCount: 1);
        $answerId = $this->postJson(route('lessons.draw', $lesson))->json('data.id');
        $this->postJson(route('answers.grade', $answerId), ['score' => 80])->assertOk();

        $this->postJson(route('lessons.draw', $lesson))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'question_limit_reached');
    }

    public function test_finish_closes_the_lesson_and_drops_the_ungraded_answer(): void
    {
        ['teacher' => $teacher, 'course' => $course] = $this->courseSetup();
        $lesson = $this->startedLesson($teacher, $course);
        $this->postJson(route('lessons.draw', $lesson))->assertCreated();

        $this->postJson(route('lessons.finish', $lesson))->assertOk()->assertJsonPath('data.status', LessonStatus::Finished->value);

        $this->assertSame(0, Answer::query()->count());
    }

    public function test_history_lists_own_lessons_newest_first_with_averages(): void
    {
        ['teacher' => $teacher, 'course' => $course, 'students' => $students] = $this->courseSetup();
        $older = Lesson::factory()->finished()->for($course)->create(['sequence' => 1, 'started_at' => now()->subWeek()]);
        $newer = Lesson::factory()->finished()->for($course)->create(['sequence' => 2, 'started_at' => now()->subDay()]);
        Answer::factory()->graded(90)->for($newer)->for($students[0])->create();
        Answer::factory()->graded(40)->for($newer)->for($students[1])->create();
        Answer::factory()->absent()->for($newer)->for($students[2])->create();
        Lesson::factory()->create();
        Sanctum::actingAs($teacher);

        $this->getJson(route('lessons.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.0.asked_count', 2)
            ->assertJsonPath('data.0.retry_count', 1)
            ->assertJsonPath('data.0.average_score', 65)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_history_filters_by_date_range(): void
    {
        ['teacher' => $teacher, 'course' => $course] = $this->courseSetup();
        Lesson::factory()->finished()->for($course)->create(['sequence' => 1, 'started_at' => '2026-10-01 09:00']);
        $inRange = Lesson::factory()->finished()->for($course)->create(['sequence' => 2, 'started_at' => '2026-10-15 09:00']);
        Sanctum::actingAs($teacher);

        $this->getJson(route('lessons.index', ['from' => '2026-10-10', 'to' => '2026-10-20']))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inRange->id);
    }

    private function startedLesson(User $teacher, $course, int $questionCount = 3): Lesson
    {
        Sanctum::actingAs($teacher);

        $id = $this->postJson(route('lessons.store', $course), [
            'topic_ids' => $course->subject->topics->pluck('id')->all(),
            'question_count' => $questionCount,
        ])->assertCreated()->json('data.id');

        return Lesson::query()->findOrFail($id);
    }
}
