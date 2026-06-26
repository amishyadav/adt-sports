<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LikeTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggle_like_counts_once_per_fingerprint(): void
    {
        $article = Article::factory()->published()->create();

        $first = $article->toggleLike('browser-a');
        $this->assertTrue($first['liked']);
        $this->assertSame(1, $first['likes']);

        // Same fingerprint again => unlike.
        $second = $article->toggleLike('browser-a');
        $this->assertFalse($second['liked']);
        $this->assertSame(0, $second['likes']);

        // A different browser adds its own like.
        $article->toggleLike('browser-a');
        $third = $article->toggleLike('browser-b');
        $this->assertTrue($third['liked']);
        $this->assertSame(2, $third['likes']);
    }

    public function test_like_counter_stays_in_lockstep_with_like_rows(): void
    {
        $article = Article::factory()->published()->create();

        $assertConsistent = function () use ($article) {
            $rows = \Illuminate\Support\Facades\DB::table('article_likes')
                ->where('article_id', $article->id)->count();
            $this->assertSame($rows, (int) $article->fresh()->likes, 'likes counter diverged from like rows');
        };

        $article->toggleLike('a');  $assertConsistent(); // 1
        $article->toggleLike('b');  $assertConsistent(); // 2
        $article->toggleLike('c');  $assertConsistent(); // 3
        $article->toggleLike('a');  $assertConsistent(); // 2 (unlike a)
        $article->toggleLike('a');  $assertConsistent(); // 3 (re-like a)

        $this->assertSame(3, (int) $article->fresh()->likes);
    }

    public function test_liking_does_not_bump_updated_at(): void
    {
        $article = Article::factory()->published()->create(['updated_at' => now()->subWeek()]);
        $before  = $article->fresh()->updated_at;

        $article->toggleLike('browser-x');

        $this->assertEquals($before, $article->fresh()->updated_at);
    }

    public function test_like_endpoint_returns_count_for_published(): void
    {
        $article = Article::factory()->published()->create();

        $this->postJson(route('article.like', $article))
            ->assertOk()
            ->assertJson(['liked' => true, 'likes' => 1]);

        $this->assertSame(1, (int) $article->fresh()->likes);
    }

    public function test_like_endpoint_mints_a_fingerprint_cookie(): void
    {
        $article = Article::factory()->published()->create();

        $this->postJson(route('article.like', $article))
            ->assertOk()
            ->assertJson(['liked' => true, 'likes' => 1])
            ->assertCookie('adt_uid');
    }

    public function test_like_endpoint_dedupes_when_the_same_cookie_is_sent(): void
    {
        $article = Article::factory()->published()->create();
        $fp      = str_repeat('a', 32);
        $json    = ['HTTP_ACCEPT' => 'application/json'];

        // Bypass cookie encryption and send the fingerprint raw (CSRF is auto-
        // skipped in tests). Same browser toggling twice ends back at zero.
        $this->withoutMiddleware(\Illuminate\Cookie\Middleware\EncryptCookies::class);

        $this->call('POST', route('article.like', $article), [], ['adt_uid' => $fp], [], $json)
            ->assertOk();
        $this->assertSame(1, (int) $article->fresh()->likes);

        $this->call('POST', route('article.like', $article), [], ['adt_uid' => $fp], [], $json)
            ->assertOk();
        $this->assertSame(0, (int) $article->fresh()->likes);
    }

    public function test_like_endpoint_404_for_unpublished(): void
    {
        $draft = Article::factory()->draft()->create();

        $this->postJson(route('article.like', $draft))->assertNotFound();
        $this->assertSame(0, (int) $draft->fresh()->likes);
    }
}
