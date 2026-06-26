<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name'        => ucfirst($name),
            'slug'        => Str::slug($name) . '-' . fake()->unique()->numberBetween(1, 1_000_000),
            'color'       => '#D4420A',
            'description' => fake()->sentence(),
        ];
    }
}
