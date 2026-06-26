<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name'     => fake()->name(),
            'email'    => fake()->unique()->safeEmail(),
            'password' => 'password', // hashed by the model's 'hashed' cast
            'role'     => 'editor',
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }

    public function editor(): static
    {
        return $this->state(['role' => 'editor']);
    }
}
