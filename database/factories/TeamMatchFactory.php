<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamMatchFactory extends Factory
{
    protected $model = TeamMatch::class;

    public function definition(): array
    {
        return [
            'team_1'       => Team::factory(),
            'team_2'       => Team::factory(),
            'status'       => 'pending',
            'team_1_score' => 0,
            'team_2_score' => 0,
            'scheduled_at' => fake()->dateTimeBetween('now', '+2 months'),
            'venue'        => fake()->city() . ' Stadium',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status'       => 'completed',
            'team_1_score' => fake()->numberBetween(20, 45),
            'team_2_score' => fake()->numberBetween(20, 45),
            'scheduled_at' => fake()->dateTimeBetween('-2 months', '-1 day'),
        ]);
    }

    public function live(): static
    {
        return $this->state(fn () => ['status' => 'live', 'scheduled_at' => now()]);
    }
}
