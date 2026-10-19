<?php

namespace Database\Factories;

use App\Models\StudyGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudyGroup>
 */
class StudyGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->bothify('??-##'),
            'course_year' => fake()->numberBetween(1, 4),
            'academic_year' => '2025-2026',
            'semester' => 1,
        ];
    }
}
