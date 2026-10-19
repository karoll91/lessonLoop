<?php

namespace Database\Factories;

use App\Enums\BoardScreen;
use App\Models\BoardSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BoardSession>
 */
class BoardSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token' => Str::random(48),
            'pair_code' => (string) fake()->unique()->numberBetween(100000, 999999),
            'pair_code_expires_at' => now()->addMinutes(5),
            'screen' => BoardScreen::Pairing,
        ];
    }
}
