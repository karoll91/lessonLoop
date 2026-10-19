<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Enums\LessonStatus;
use App\Models\Answer;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Student;
use App\Models\Topic;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_shows_todays_lessons_the_running_lesson_and_debtors_of_own_courses(): void
    {
        $course = Course::factory()->create();
        [$debtor, $fresh] = Student::factory()->count(2)->create(['study_group_id' => $course->study_group_id]);
        $topic = Topic::factory()->create(['subject_id' => $course->subject_id]);
        Question::factory()->for($topic)->create(['answer_text' => null]);
        Question::factory()->for($topic)->create();

        $yesterday = Lesson::factory()->finished()->for($course)->create(['sequence' => 1, 'started_at' => now()->subDay()]);
        Answer::factory()->graded(35)->for($yesterday)->for($debtor)->create();
        $today = Lesson::factory()->for($course)->create(['sequence' => 2, 'status' => LessonStatus::Active, 'started_at' => now()]);

        Course::factory()->create();
        Sanctum::actingAs($course->teacher);

        $this->getJson(route('dashboard'))
            ->assertOk()
            ->assertJsonPath('data.counts.groups', 1)
            ->assertJsonPath('data.counts.students', 2)
            ->assertJsonPath('data.counts.questions', 2)
            ->assertJsonPath('data.counts.questions_without_answer', 1)
            ->assertJsonPath('data.counts.never_asked', 1)
            ->assertJsonPath('data.running_lesson.id', $today->id)
            ->assertJsonCount(1, 'data.today_lessons')
            ->assertJsonPath('data.debtors.0.student_id', $debtor->id)
            ->assertJsonPath('data.debtors.0.last_score', 35);
    }

    public function test_returns_401_without_a_token(): void
    {
        $this->getJson(route('dashboard'))->assertUnauthorized();
    }
}
