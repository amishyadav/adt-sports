<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticlePublishingTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishing_sets_published_at(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/articles', [
            'title'           => 'Breaking Kabaddi News',
            'status'          => 'draft',
            'status_override' => 'published',
        ])->assertRedirect();

        $article = Article::where('title', 'Breaking Kabaddi News')->first();
        $this->assertSame('published', $article->status);
        $this->assertNotNull($article->published_at);
    }

    public function test_saving_as_draft_leaves_published_at_null(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/articles', [
            'title'  => 'Work In Progress',
            'status' => 'draft',
        ])->assertRedirect();

        $article = Article::where('title', 'Work In Progress')->first();
        $this->assertSame('draft', $article->status);
        $this->assertNull($article->published_at);
    }

    public function test_duplicate_titles_get_unique_slugs(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([1, 2] as $_) {
            $this->actingAs($admin)->post('/admin/articles', [
                'title'  => 'Same Headline',
                'status' => 'draft',
            ])->assertRedirect();
        }

        $slugs = Article::where('title', 'Same Headline')->pluck('slug');
        $this->assertCount(2, $slugs);
        $this->assertSame($slugs->count(), $slugs->unique()->count(), 'Slugs must be unique');
    }

    public function test_read_time_is_calculated_on_store(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/articles', [
            'title'  => 'With A Body',
            'status' => 'draft',
            'body'   => '<p>' . str_repeat('word ', 400) . '</p>',
        ])->assertRedirect();

        // 400 words / 200 wpm = 2 min
        $this->assertSame('2 min', Article::where('title', 'With A Body')->value('read_time'));
    }
}
