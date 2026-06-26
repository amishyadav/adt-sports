<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\Comment;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'article_id'   => Article::factory(),
            'author_name'  => fake()->name(),
            'author_email' => fake()->safeEmail(),
            'body'         => '<p>' . fake()->sentence() . '</p>',
            'approved'     => false,
            'ip'           => fake()->ipv4(),
        ];
    }

    public function approved(): static
    {
        return $this->state(['approved' => true]);
    }
}
