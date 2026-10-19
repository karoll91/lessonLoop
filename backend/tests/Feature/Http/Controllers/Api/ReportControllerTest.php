<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Answer;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Student;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_report_summarises_lessons_distribution_coverage_and_hardest_questions(): void
    {
        $course = Course::factory()->create();
        $easyTopic = Topic::factory()->create(['subject_id' => $course->subject_id, 'title' => 'Jarayonlar']);
        $hardTopic = Topic::factory()->create(['subject_id' => $course->subject_id, 'title' => 'Oqimlar']);
        $easy = Question::factory()->for($easyTopic)->create(['text' => 'Jarayon nima?']);
        $hard = Question::factory()->for($hardTopic)->create(['text' => 'Mutex nima?']);
        [$sora, $bobur] = Student::factory()->count(3)->create(['study_group_id' => $course->study_group_id]);

        $first = Lesson::factory()->finished()->for($course)->create(['sequence' => 1]);
        $second = Lesson::factory()->finished()->for($course)->create(['sequence' => 2]);
        Answer::factory()->graded(90)->for($first)->for($sora)->for($easy)->create();
        Answer::factory()->graded(30)->for($first)->for($bobur)->for($hard)->create();
        Answer::factory()->noAnswer()->for($second)->for($bobur)->for($hard)->create();
        Answer::factory()->for($second)->for($sora)->for($easy)->create();
        Sanctum::actingAs($course->teacher);

        $response = $this->getJson(route('courses.report', $course))->assertOk();

        $response->assertJsonPath('data.summary.lessons', 2)
            ->assertJsonPath('data.summary.answers', 3)
            ->assertJsonPath('data.summary.average', 40)
            ->assertJsonPath('data.summary.pass_rate', 33)
            ->assertJsonPath('data.summary.debtors', 1)
            ->assertJsonPath('data.coverage', ['students' => 3, 'asked' => 2, 'never_asked' => 1])
            ->assertJsonPath('data.lessons.0.average', 60)
            ->assertJsonPath('data.lessons.0.passed', 1)
            ->assertJsonPath('data.lessons.1.answers', 1)
            ->assertJsonPath('data.lessons.1.average', 0)
            ->assertJsonPath('data.distribution.0', ['from' => 0, 'to' => 19, 'count' => 1])
            ->assertJsonPath('data.distribution.1.count', 1)
            ->assertJsonPath('data.distribution.4', ['from' => 80, 'to' => 100, 'count' => 1])
            ->assertJsonPath('data.topics.0.title', 'Oqimlar')
            ->assertJsonPath('data.topics.0.average', 15)
            ->assertJsonPath('data.hardest_questions.0.text', 'Mutex nima?')
            ->assertJsonPath('data.hardest_questions.0.times_asked', 2)
            ->assertJsonCount(2, 'data.hardest_questions');
    }

    public function test_report_of_a_course_without_lessons_has_empty_figures(): void
    {
        $course = Course::factory()->create();
        Sanctum::actingAs($course->teacher);

        $this->getJson(route('courses.report', $course))
            ->assertOk()
            ->assertJsonPath('data.summary.average', null)
            ->assertJsonPath('data.summary.pass_rate', null)
            ->assertJsonPath('data.lessons', [])
            ->assertJsonPath('data.hardest_questions', []);
    }

    public function test_returns_403_when_another_teacher_opens_the_report(): void
    {
        $course = Course::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('courses.report', $course))->assertForbidden();
    }
}
