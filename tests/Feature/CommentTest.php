<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    private const IDENTITY = ['name' => 'Sub Scriber', 'email' => 'sub@example.com'];

    /**
     * Post a comment as an (optionally) identified visitor. With cookie
     * encryption disabled we can hand the controller a raw identity cookie,
     * exactly what CommenterIdentity::get() reads.
     */
    private function comment(Article $article, array $data, ?array $identity = self::IDENTITY): TestResponse
    {
        $this->withoutMiddleware(EncryptCookies::class);
        $cookies = $identity ? ['adt_commenter' => json_encode($identity)] : [];

        return $this->call('POST', route('article.comments.store', $article), $data, $cookies, [], ['HTTP_ACCEPT' => 'text/html']);
    }

    /** Same as comment() but as a fetch()/XHR request expecting a JSON reply. */
    private function commentAjax(Article $article, array $data, ?array $identity = self::IDENTITY): TestResponse
    {
        $this->withoutMiddleware(EncryptCookies::class);
        $cookies = $identity ? ['adt_commenter' => json_encode($identity)] : [];

        return $this->call('POST', route('article.comments.store', $article), $data, $cookies, [], [
            'HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
    }

    public function test_subscribed_visitor_comment_goes_live_immediately(): void
    {
        $article = Article::factory()->published()->create();

        $this->comment($article, ['body' => 'Great match coverage!'])
            ->assertRedirect(route('article', $article->slug) . '?comment=posted#comments');

        // Post-moderation: stored approved (visible) on creation, no approval step.
        $this->assertDatabaseHas('comments', [
            'article_id'   => $article->id,
            'author_name'  => 'Sub Scriber',
            'author_email' => 'sub@example.com',
            'approved'     => true,
        ]);

        // ...and it renders on the public article page right away.
        $this->get(route('article', $article->slug))->assertSee('Great match coverage', false);
    }

    public function test_ajax_comment_returns_json_with_rendered_html(): void
    {
        $article = Article::factory()->published()->create();

        $response = $this->commentAjax($article, ['body' => 'Inline AJAX comment'])
            ->assertOk()
            ->assertJson(['ok' => true, 'status' => 'posted', 'count' => 1]);

        // The payload carries the rendered comment so JS can inject it inline.
        $this->assertStringContainsString('Inline AJAX comment', $response->json('html'));
        $this->assertStringContainsString('Sub Scriber', $response->json('html'));
    }

    public function test_ajax_invalid_comment_returns_422_json(): void
    {
        $article = Article::factory()->published()->create();

        $this->commentAjax($article, ['body' => str_repeat('a', 5001)])
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'status' => 'error']);

        $this->assertSame(0, $article->comments()->count());
    }

    public function test_visitor_without_identity_is_sent_to_the_gate(): void
    {
        $article = Article::factory()->published()->create();

        $this->comment($article, ['body' => 'Let me in'], null)
            ->assertRedirect(route('article', $article->slug) . '?comment=subscribe#comments');

        $this->assertSame(0, Comment::count());
    }

    public function test_comment_body_is_sanitized(): void
    {
        $article = Article::factory()->published()->create();

        $this->comment($article, [
            'body' => 'Nice<script>alert(1)</script><a href="javascript:alert(2)">x</a>',
        ])->assertRedirect();

        $body = Comment::where('article_id', $article->id)->value('body');
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringContainsString('Nice', $body);
    }

    public function test_comment_links_are_nofollow(): void
    {
        $article = Article::factory()->published()->create();

        $this->comment($article, ['body' => 'See <a href="https://spam.example">this</a>'])->assertRedirect();

        $this->assertStringContainsString('nofollow', Comment::where('article_id', $article->id)->value('body'));
    }

    public function test_honeypot_silently_drops_bot_submissions(): void
    {
        $article = Article::factory()->published()->create();

        $this->comment($article, ['body' => 'spam', 'hp_url' => 'http://spam.example'])->assertRedirect();

        $this->assertSame(0, Comment::count());
    }

    public function test_only_approved_comments_render_on_article_page(): void
    {
        $article = Article::factory()->published()->create();
        Comment::factory()->approved()->create(['article_id' => $article->id, 'body' => '<p>APPROVED-MARKER</p>']);
        Comment::factory()->create(['article_id' => $article->id, 'body' => '<p>PENDING-MARKER</p>']);

        $this->get(route('article', $article->slug))
            ->assertOk()
            ->assertSee('APPROVED-MARKER', false)
            ->assertDontSee('PENDING-MARKER', false);
    }

    public function test_comment_landing_page_is_never_served_stale(): void
    {
        // The ?comment=posted page must never be cached, or a later commenter
        // would land on an earlier commenter's copy and not see their own.
        config(['responsecache.enabled' => true]);
        \Spatie\ResponseCache\Facades\ResponseCache::clear();

        $article = Article::factory()->published()->create();
        Comment::factory()->approved()->create(['article_id' => $article->id, 'body' => '<p>FIRST-COMMENT</p>']);

        // Warm the landing page (would cache it under the old behaviour).
        $this->get(route('article', $article->slug) . '?comment=posted')
            ->assertOk()->assertSee('FIRST-COMMENT', false);

        // A new comment arrives WITHOUT a cache bust, to isolate the profile.
        Comment::factory()->approved()->create(['article_id' => $article->id, 'body' => '<p>SECOND-COMMENT</p>']);

        // Fresh render every time -> the newest comment is visible immediately.
        $this->get(route('article', $article->slug) . '?comment=posted')
            ->assertSee('SECOND-COMMENT', false);
    }

    public function test_invalid_comment_redirects_to_cache_safe_error_url(): void
    {
        $article = Article::factory()->published()->create();

        // Body over the 5000-char limit — only caught server-side.
        $this->comment($article, ['body' => str_repeat('a', 5001)])
            ->assertRedirect(route('article', $article->slug) . '?comment=error#comments');

        $this->assertSame(0, $article->comments()->count());

        $this->get(route('article', $article->slug) . '?comment=error')
            ->assertOk()
            ->assertSee('Please check your comment', false);
    }

    public function test_duplicate_comment_is_suppressed(): void
    {
        $article = Article::factory()->published()->create();

        $this->comment($article, ['body' => 'Same comment twice'])->assertRedirect();
        $this->comment($article, ['body' => 'Same comment twice'])->assertRedirect();

        $this->assertSame(1, $article->comments()->count());
    }

    public function test_cannot_comment_on_unpublished_article(): void
    {
        $draft = Article::factory()->draft()->create();

        $this->comment($draft, ['body' => 'hi'])->assertNotFound();
    }

    public function test_moderation_is_admin_only(): void
    {
        $this->get(route('admin.comments.index'))->assertRedirect(route('admin.login'));

        $editor = User::factory()->editor()->create();
        $this->actingAs($editor)->get(route('admin.comments.index'))->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.comments.index'))->assertOk();
    }

    public function test_editor_cannot_approve_a_comment(): void
    {
        $editor  = User::factory()->editor()->create();
        $comment = Comment::factory()->create();

        $this->actingAs($editor)
            ->put(route('admin.comments.approve', $comment))
            ->assertForbidden();

        $this->assertFalse($comment->fresh()->approved);
    }

    public function test_admin_can_approve_a_comment(): void
    {
        $admin   = User::factory()->admin()->create();
        $comment = Comment::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.comments.approve', $comment))
            ->assertRedirect();

        $this->assertTrue($comment->fresh()->approved);
    }

    public function test_admin_can_hide_a_live_comment(): void
    {
        $admin   = User::factory()->admin()->create();
        $article = Article::factory()->published()->create();
        $comment = Comment::factory()->approved()->create([
            'article_id' => $article->id, 'body' => '<p>HIDE-ME-MARKER</p>',
        ]);

        // Live on the public page first...
        $this->get(route('article', $article->slug))->assertSee('HIDE-ME-MARKER', false);

        $this->actingAs($admin)->put(route('admin.comments.hide', $comment))->assertRedirect();
        $this->assertFalse($comment->fresh()->approved);

        // ...then gone after hiding (cache evicted by the hide action).
        $this->get(route('article', $article->slug))->assertDontSee('HIDE-ME-MARKER', false);
    }

    public function test_editor_cannot_hide_a_comment(): void
    {
        $editor  = User::factory()->editor()->create();
        $comment = Comment::factory()->approved()->create();

        $this->actingAs($editor)->put(route('admin.comments.hide', $comment))->assertForbidden();

        $this->assertTrue($comment->fresh()->approved);
    }
}
