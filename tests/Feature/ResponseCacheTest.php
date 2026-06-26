<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Support\PublicResponseCacheProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\ResponseCache\Facades\ResponseCache;
use Tests\TestCase;

class ResponseCacheTest extends TestCase
{
    use RefreshDatabase;

    /* ── Security: the cache profile gate ──────────────────────────── */

    public function test_profile_never_caches_authenticated_requests(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $profile = new PublicResponseCacheProfile();
        $this->assertFalse($profile->shouldCacheRequest(Request::create('/', 'GET')));
    }

    public function test_profile_never_caches_admin_beacon_search_or_non_get(): void
    {
        $profile = new PublicResponseCacheProfile();

        $this->assertFalse($profile->shouldCacheRequest(Request::create('/admin', 'GET')));
        $this->assertFalse($profile->shouldCacheRequest(Request::create('/admin/articles', 'GET')));
        $this->assertFalse($profile->shouldCacheRequest(Request::create('/article/5/hit', 'GET')));
        $this->assertFalse($profile->shouldCacheRequest(Request::create('/search', 'GET')));
        $this->assertFalse($profile->shouldCacheRequest(Request::create('/', 'POST')));
    }

    public function test_profile_caches_public_get_pages(): void
    {
        $profile = new PublicResponseCacheProfile();

        $this->assertTrue($profile->shouldCacheRequest(Request::create('/', 'GET')));
        $this->assertTrue($profile->shouldCacheRequest(Request::create('/article/some-slug', 'GET')));
        $this->assertTrue($profile->shouldCacheRequest(Request::create('/category/x', 'GET')));
    }

    public function test_profile_never_caches_comment_landing_pages(): void
    {
        // Post-moderation: ?comment=posted must stay live so the commenter sees
        // their own just-posted comment, never a prior commenter's cached copy.
        $profile = new PublicResponseCacheProfile();

        $this->assertFalse($profile->shouldCacheRequest(Request::create('/article/some-slug?comment=posted', 'GET')));
        $this->assertFalse($profile->shouldCacheRequest(Request::create('/article/some-slug?comment=error', 'GET')));
        // ...but the bare article URL is still cacheable.
        $this->assertTrue($profile->shouldCacheRequest(Request::create('/article/some-slug', 'GET')));
    }

    public function test_only_200_responses_are_cacheable(): void
    {
        $profile = new PublicResponseCacheProfile();

        $this->assertTrue($profile->shouldCacheResponse(new \Symfony\Component\HttpFoundation\Response('', 200)));
        $this->assertFalse($profile->shouldCacheResponse(new \Symfony\Component\HttpFoundation\Response('', 204)));
        $this->assertFalse($profile->shouldCacheResponse(new \Symfony\Component\HttpFoundation\Response('', 404)));
        $this->assertFalse($profile->shouldCacheResponse(new \Symfony\Component\HttpFoundation\Response('', 302)));
    }

    /* ── Behaviour: cache hit + bust ───────────────────────────────── */

    public function test_public_page_is_cached_and_busted_on_content_change(): void
    {
        config(['responsecache.enabled' => true]);
        ResponseCache::clear();

        $article = Article::factory()->published()->create(['title' => 'Cached Title AAA']);

        // Warm the cache.
        $this->get("/article/{$article->slug}")->assertOk()->assertSee('Cached Title AAA', false);

        // Change the title WITHOUT firing the observer — the cache should still serve the old page.
        DB::table('articles')->where('id', $article->id)->update(['title' => 'Changed Title BBB']);
        $this->get("/article/{$article->slug}")
            ->assertSee('Cached Title AAA', false)
            ->assertDontSee('Changed Title BBB', false);

        // A real model save fires the observer -> ResponseCache::clear() -> fresh render.
        Article::find($article->id)->update(['excerpt' => 'touch to bust']);
        $this->get("/article/{$article->slug}")->assertSee('Changed Title BBB', false);
    }

    public function test_draft_edits_do_not_evict_cache_but_publishing_does(): void
    {
        config(['responsecache.enabled' => true]);
        ResponseCache::clear();

        // A live article so the home feed has cacheable content.
        $live = Article::factory()->published()->create(['title' => 'Live Home AAA']);
        $this->get('/')->assertOk()->assertSee('Live Home AAA', false);

        // Change what a fresh home render would show, without firing the observer.
        DB::table('articles')->where('id', $live->id)->update(['title' => 'Live Home BBB']);

        // Editing a DRAFT changes nothing a guest can see -> the home cache must
        // survive (this is the whole point of the targeted invalidation).
        $draft = Article::factory()->create(['status' => 'draft', 'published_at' => null]);
        Article::find($draft->id)->update(['excerpt' => 'still just a draft']);
        $this->get('/')
            ->assertSee('Live Home AAA', false)
            ->assertDontSee('Live Home BBB', false);

        // Publishing IS publicly relevant -> home is forgotten -> fresh render.
        Article::find($draft->id)->update(['status' => 'published', 'published_at' => now()]);
        $this->get('/')->assertSee('Live Home BBB', false);
    }

    public function test_unpublishing_evicts_the_cached_article_and_home(): void
    {
        config(['responsecache.enabled' => true]);
        ResponseCache::clear();

        $article = Article::factory()->published()->create(['title' => 'Going Away SOON']);

        // Warm both the article page and the home feed.
        $this->get("/article/{$article->slug}")->assertOk()->assertSee('Going Away SOON', false);
        $this->get('/')->assertOk()->assertSee('Going Away SOON', false);

        // Unpublish (published -> draft). publiclyRelevant() must catch this via
        // getOriginal('status') === 'published', or the stale page lingers.
        Article::find($article->id)->update(['status' => 'draft', 'published_at' => null]);

        // The article page is now a 404 for guests, and home no longer lists it.
        $this->get("/article/{$article->slug}")->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('Going Away SOON', false);
    }

    public function test_beacon_is_never_cached(): void
    {
        config(['responsecache.enabled' => true]);
        ResponseCache::clear();

        $article = Article::factory()->published()->create(['views' => 0]);

        $this->get(route('article.hit', $article))->assertNoContent();
        $this->get(route('article.hit', $article))->assertNoContent();
        $this->artisan('app:flush-article-views')->assertSuccessful();

        // Both beacons counted => the endpoint was not served from cache.
        $this->assertSame(2, $article->fresh()->views);
    }
}
