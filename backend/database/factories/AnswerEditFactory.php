<?php

namespace Database\Factories;

use App\Enums\AnswerStatus;
use App\Models\Answer;
use App\Models\AnswerEdit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnswerEdit>
 */
class AnswerEditFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'answer_id' => Answer::factory()->graded(45),
            'user_id' => User::factory(),
            'old_status' => AnswerStatus::Graded,
            'new_status' => AnswerStatus::Graded,
            'old_score' => 45,
            'new_score' => 55,
            'reason' => 'Qayta tekshirildi',
        ];
    }
}
