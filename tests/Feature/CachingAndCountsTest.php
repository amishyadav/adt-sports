<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CachingAndCountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_observer_maintains_published_article_count(): void
    {
        $category = Category::factory()->create();

        Article::factory()->published()->create(['category_id' => $category->id]);
        $this->assertSame(1, $category->fresh()->article_count);

        // Drafts must not count toward the published total.
        Article::factory()->draft()->create(['category_id' => $category->id]);
        $this->assertSame(1, $category->fresh()->article_count);
    }

    public function test_deleting_an_article_decrements_the_count(): void
    {
        $category = Category::factory()->create();
        $article  = Article::factory()->published()->create(['category_id' => $category->id]);
        $this->assertSame(1, $category->fresh()->article_count);

        $article->delete();
        $this->assertSame(0, $category->fresh()->article_count);
    }

    public function test_settings_cache_is_busted_on_write(): void
    {
        Setting::set('site_name', 'First Name');
        $this->assertSame('First Name', Setting::get('site_name')); // warms cache

        Setting::set('site_name', 'Second Name');
        $this->assertSame('Second Name', Setting::get('site_name')); // not stale
    }

    public function test_category_cache_is_busted_on_change(): void
    {
        Category::factory()->create(['name' => 'Alpha']);
        $this->assertCount(1, Category::ordered());

        Category::factory()->create(['name' => 'Beta']);
        $this->assertCount(2, Category::ordered()); // cache refreshed, not stale at 1
    }

    public function test_publishing_an_article_flushes_the_sitemap_cache(): void
    {
        Cache::put('seo.sitemap', '<stale/>', 600);

        Article::factory()->published()->create();

        $this->assertFalse(Cache::has('seo.sitemap'));
    }
}
