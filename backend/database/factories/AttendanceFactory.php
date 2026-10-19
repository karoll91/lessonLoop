<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Lesson;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
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
            'is_present' => true,
        ];
    }

    /**
     * Indicate that the student was absent.
     */
    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_present' => false,
        ]);
    }
}
