<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        $name = fake()->unique()->city() . ' ' . fake()->randomElement(['Panthers', 'Titans', 'Warriors', 'Pirates', 'Bulls', 'Steelers']);

        return [
            'name'       => $name,
            'slug'       => Str::slug($name) . '-' . fake()->unique()->numberBetween(1, 1_000_000),
            'short_name' => strtoupper(Str::substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3)),
            'logo'       => null,
            'status'     => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => false]);
    }
}
