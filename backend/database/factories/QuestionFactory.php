<?php

namespace Database\Factories;

use App\Enums\Difficulty;
use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'topic_id' => Topic::factory(),
            'type' => QuestionType::Open,
            'text' => fake()->sentence(8).'?',
            'answer_text' => fake()->paragraph(),
            'difficulty' => Difficulty::Medium,
            'image_path' => null,
        ];
    }

    /**
     * A test question with options A-D where the given label is correct.
     */
    public function test(string $correctLabel = 'C'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => QuestionType::Test,
            'answer_text' => null,
        ])->afterCreating(function (Question $question) use ($correctLabel): void {
            foreach (['A', 'B', 'C', 'D'] as $label) {
                QuestionOption::factory()->for($question)->create([
                    'label' => $label,
                    'is_correct' => $label === $correctLabel,
                ]);
            }
        });
    }
}
