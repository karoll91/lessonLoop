<?php

namespace Tests\Feature\Services;

use App\Exceptions\LessonFlowException;
use App\Models\Answer;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Topic;
use App\Services\QuestionPicker;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

class QuestionPickerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_picks_only_from_the_lesson_topics(): void
    {
        $course = Course::factory()->create();
        $chosenTopic = Topic::factory()->create(['subject_id' => $course->subject_id]);
        $otherTopic = Topic::factory()->create(['subject_id' => $course->subject_id]);
        $expected = Question::factory()->for($chosenTopic)->create();
        Question::factory()->count(3)->for($otherTopic)->create();
        $lesson = $this->lessonWithTopics($course, 1, [$chosenTopic->id]);

        $this->assertTrue($this->picker()->pick($lesson)->is($expected));
    }

    public function test_does_not_repeat_a_question_within_the_lesson(): void
    {
        $course = Course::factory()->create();
        $topic = Topic::factory()->create(['subject_id' => $course->subject_id]);
        [$used, $unused] = Question::factory()->count(2)->for($topic)->create();
        $lesson = $this->lessonWithTopics($course, 1, [$topic->id]);
        Answer::factory()->for($lesson)->for($used)->create();

        $this->assertTrue($this->picker()->pick($lesson)->is($unused));
    }

    public function test_prefers_questions_not_given_in_the_last_four_lessons(): void
    {
        $course = Course::factory()->create();
        $topic = Topic::factory()->create(['subject_id' => $course->subject_id]);
        [$recent, $fresh] = Question::factory()->count(2)->for($topic)->create();
        $earlier = $this->lessonWithTopics($course, 3, [$topic->id]);
        Answer::factory()->graded(80)->for($earlier)->for($recent)->create();
        $lesson = $this->lessonWithTopics($course, 5, [$topic->id]);

        for ($i = 0; $i < 20; $i++) {
            $this->assertTrue($this->picker($i)->pick($lesson)->is($fresh));
        }
    }

    public function test_falls_back_to_recent_questions_when_nothing_fresh_is_left(): void
    {
        $course = Course::factory()->create();
        $topic = Topic::factory()->create(['subject_id' => $course->subject_id]);
        $recent = Question::factory()->for($topic)->create();
        $earlier = $this->lessonWithTopics($course, 4, [$topic->id]);
        Answer::factory()->graded(80)->for($earlier)->for($recent)->create();
        $lesson = $this->lessonWithTopics($course, 5, [$topic->id]);

        $this->assertTrue($this->picker()->pick($lesson)->is($recent));
    }

    public function test_throws_when_the_topics_have_no_unused_questions(): void
    {
        $course = Course::factory()->create();
        $topic = Topic::factory()->create(['subject_id' => $course->subject_id]);
        $question = Question::factory()->for($topic)->create();
        $lesson = $this->lessonWithTopics($course, 1, [$topic->id]);
        Answer::factory()->for($lesson)->for($question)->create();

        $this->expectException(LessonFlowException::class);

        $this->picker()->pick($lesson);
    }

    private function picker(int $seed = 2026): QuestionPicker
    {
        return new QuestionPicker(new Randomizer(new Mt19937($seed)));
    }

    /**
     * @param  array<int, int>  $topicIds
     */
    private function lessonWithTopics(Course $course, int $sequence, array $topicIds): Lesson
    {
        $lesson = Lesson::factory()->for($course)->create(['sequence' => $sequence]);
        $lesson->topics()->sync($topicIds);

        return $lesson;
    }
}
