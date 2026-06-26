<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DraftVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_article_is_publicly_readable(): void
    {
        $article = Article::factory()->published()->create();

        $this->get("/article/{$article->slug}")
            ->assertOk()
            ->assertSee($article->title);
    }

    public function test_draft_returns_404_for_guest(): void
    {
        $article = Article::factory()->draft()->create();

        $this->get("/article/{$article->slug}")->assertNotFound();
    }

    public function test_future_scheduled_article_returns_404_for_guest(): void
    {
        $article = Article::factory()->scheduled()->create();

        $this->get("/article/{$article->slug}")->assertNotFound();
    }

    public function test_admin_can_preview_a_draft(): void
    {
        $admin   = User::factory()->admin()->create();
        $article = Article::factory()->draft()->create();

        $this->actingAs($admin)
            ->get("/article/{$article->slug}")
            ->assertOk();
    }

    public function test_author_can_preview_their_own_draft(): void
    {
        $author  = User::factory()->editor()->create();
        $article = Article::factory()->draft()->create(['author_id' => $author->id]);

        $this->actingAs($author)
            ->get("/article/{$article->slug}")
            ->assertOk();
    }

    public function test_editor_cannot_preview_another_authors_draft(): void
    {
        $stranger = User::factory()->editor()->create();
        $article  = Article::factory()->draft()->create();

        $this->actingAs($stranger)
            ->get("/article/{$article->slug}")
            ->assertNotFound();
    }

    public function test_view_beacon_buffers_then_flushes_a_view(): void
    {
        $article = Article::factory()->published()->create(['views' => 0]);

        // The page itself no longer counts (so it can be cached); the beacon does.
        $this->get("/article/{$article->slug}")->assertOk();
        $this->assertSame(0, $article->fresh()->views);

        $this->get(route('article.hit', $article))->assertNoContent();
        $this->assertDatabaseHas('article_view_buffer', ['article_id' => $article->id, 'count' => 1]);

        $this->artisan('app:flush-article-views')->assertSuccessful();
        $this->assertSame(1, $article->fresh()->views);
        $this->assertDatabaseMissing('article_view_buffer', ['article_id' => $article->id]);
    }

    public function test_flushing_views_does_not_change_updated_at(): void
    {
        $article  = Article::factory()->published()->create(['views' => 0]);
        $original = $article->updated_at;

        $this->get(route('article.hit', $article))->assertNoContent();
        $this->artisan('app:flush-article-views')->assertSuccessful();

        // lastmod (used by the sitemap) must not move just because of a view.
        $this->assertEquals($original->timestamp, $article->fresh()->updated_at->timestamp);
    }

    public function test_beacon_does_not_count_a_draft(): void
    {
        $article = Article::factory()->draft()->create(['views' => 0]);

        $this->get(route('article.hit', $article))->assertNoContent();
        $this->artisan('app:flush-article-views')->assertSuccessful();

        $this->assertSame(0, $article->fresh()->views);
        $this->assertDatabaseMissing('article_view_buffer', ['article_id' => $article->id]);
    }
}
