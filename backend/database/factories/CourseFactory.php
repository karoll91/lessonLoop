<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\StudyGroup;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
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
            'subject_id' => Subject::factory(),
            'teacher_id' => User::factory(),
        ];
    }
}
