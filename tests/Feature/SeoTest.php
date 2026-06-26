<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_txt_disallows_admin_and_advertises_sitemap(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('Disallow: /admin', false);
        $response->assertSee('Sitemap:', false);
    }

    public function test_sitemap_lists_published_but_not_draft_articles(): void
    {
        $published = Article::factory()->published()->create();
        $draft     = Article::factory()->draft()->create();

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertSee($published->slug, false);
        $response->assertDontSee($draft->slug, false);
    }

    public function test_future_scheduled_article_is_absent_from_sitemap(): void
    {
        $scheduled = Article::factory()->scheduled()->create();

        $this->get('/sitemap.xml')->assertDontSee($scheduled->slug, false);
    }

    public function test_rss_feed_renders(): void
    {
        $article = Article::factory()->published()->create();

        $response = $this->get('/feed.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
        $response->assertSee($article->title, false);
    }

    public function test_news_sitemap_renders_recent_articles(): void
    {
        $recent = Article::factory()->published()->create(['published_at' => now()->subHour()]);

        $this->get('/news-sitemap.xml')
            ->assertOk()
            ->assertSee($recent->slug, false);
    }

    public function test_logo_references_use_correct_path(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringNotContainsString('/public/uploads/', $html);
        $this->assertStringContainsString('/uploads/logo.png', $html);
    }

    public function test_article_jsonld_publisher_logo_is_correct(): void
    {
        $article = Article::factory()->published()->create();

        $this->get(route('article', $article->slug))
            ->assertOk()
            ->assertSee('"url":"' . url('/uploads/logo.png') . '"', false)
            ->assertDontSee('/public/uploads/', false);
    }
}
