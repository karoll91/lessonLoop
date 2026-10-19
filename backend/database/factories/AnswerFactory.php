<?php

namespace Database\Factories;

use App\Enums\AnswerStatus;
use App\Models\Answer;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Answer>
 */
class AnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'student_id' => Student::factory(),
            'question_id' => Question::factory(),
            'status' => AnswerStatus::Pending,
            'score' => null,
            'pick_weight' => 1,
        ];
    }

    /**
     * Indicate that the answer was graded with the given score.
     */
    public function graded(int $score): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AnswerStatus::Graded,
            'score' => $score,
            'graded_at' => now(),
        ]);
    }

    /**
     * Indicate that the student did not answer (scored as 0).
     */
    public function noAnswer(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AnswerStatus::NoAnswer,
            'score' => 0,
            'graded_at' => now(),
        ]);
    }

    /**
     * Indicate that the student turned out to be absent.
     */
    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AnswerStatus::Absent,
            'score' => null,
        ]);
    }
}
