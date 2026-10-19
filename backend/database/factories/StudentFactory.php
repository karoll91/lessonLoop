<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudyGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'study_group_id' => StudyGroup::factory(),
            'full_name' => fake()->firstName().' '.fake()->lastName(),
            'phone' => null,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the student is archived.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
