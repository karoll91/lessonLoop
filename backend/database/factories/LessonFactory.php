<?php

namespace Database\Factories;

use App\Enums\LessonStatus;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'teacher_id' => null,
            'sequence' => 1,
            'question_count' => 8,
            'status' => LessonStatus::Active,
            'started_at' => now(),
        ];
    }

    /**
     * Indicate that the lesson has finished.
     */
    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LessonStatus::Finished,
            'finished_at' => now(),
        ]);
    }
}
