<?php

namespace Tests\Feature;

use App\Mail\CommenterConfirmation;
use App\Models\Article;
use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CommenterTest extends TestCase
{
    use RefreshDatabase;

    private function subscribeUrl(Article $article): string
    {
        return route('article.commenter.subscribe', $article);
    }

    private function signedConfirm(Article $article, Subscriber $s, ?string $token = null): string
    {
        return URL::temporarySignedRoute('article.commenter.confirm', now()->addHour(), [
            'article'    => $article->id,
            'subscriber' => $s->id,
            't'          => $token ?? $s->confirmation_token,
        ]);
    }

    public function test_subscribe_emails_a_confirmation_and_stores_unverified_without_unlocking(): void
    {
        Mail::fake();
        $article = Article::factory()->published()->create();

        $this->post($this->subscribeUrl($article), ['name' => 'Ayush Raman', 'email' => 'Ayush@Example.com'])
            ->assertRedirect(route('article', $article->slug) . '?comment=check#comments')
            ->assertCookieMissing('adt_commenter');

        $sub = Subscriber::where('email', 'ayush@example.com')->firstOrFail();
        $this->assertNull($sub->verified_at);
        $this->assertNotNull($sub->confirmation_token);
        $this->assertSame('comment', $sub->source);

        Mail::assertQueued(CommenterConfirmation::class, fn ($m) => $m->hasTo('ayush@example.com'));
    }

    public function test_confirm_get_renders_a_page_without_side_effects(): void
    {
        $article = Article::factory()->published()->create();
        $sub = Subscriber::create(['email' => 'g@example.com', 'name' => 'Reader', 'created_at' => now(), 'confirmation_token' => 'TOKEN123456']);

        $this->get($this->signedConfirm($article, $sub))
            ->assertOk()
            ->assertSee('Confirm my email', false);

        // GET must not verify or issue a cookie (scanner-safe).
        $this->assertNull($sub->fresh()->verified_at);
    }

    public function test_confirm_post_verifies_and_unlocks_then_link_is_single_use(): void
    {
        $article = Article::factory()->published()->create();
        $sub = Subscriber::create(['email' => 'p@example.com', 'name' => 'Reader', 'created_at' => now(), 'confirmation_token' => 'TOKEN123456']);
        $url = $this->signedConfirm($article, $sub);

        $this->post($url)
            ->assertRedirect(route('article', $article->slug) . '#comments')
            ->assertCookie('adt_commenter')
            ->assertCookie('adt_commenter_name');

        $sub->refresh();
        $this->assertNotNull($sub->verified_at);
        $this->assertNull($sub->confirmation_token); // single-use: cleared

        // Re-using the same link now fails (token gone).
        $this->post($url)->assertRedirect(route('article', $article->slug) . '?comment=expired#comments');
    }

    public function test_confirm_rejects_an_unsigned_link(): void
    {
        $article = Article::factory()->published()->create();
        $sub = Subscriber::create(['email' => 'x@example.com', 'created_at' => now(), 'confirmation_token' => 'TOK']);

        $this->get(route('article.commenter.confirm', ['article' => $article->id, 'subscriber' => $sub->id, 't' => 'TOK']))
            ->assertForbidden();

        $this->assertNull($sub->fresh()->verified_at);
    }

    public function test_honeypot_blocks_subscribe_silently(): void
    {
        Mail::fake();
        $article = Article::factory()->published()->create();

        $this->post($this->subscribeUrl($article), [
            'name' => 'Bot', 'email' => 'bot@example.com', 'hp_url' => 'http://spam.example',
        ])->assertRedirect();

        $this->assertSame(0, Subscriber::count());
        Mail::assertNothingQueued();
    }

    public function test_confirm_page_is_excluded_from_full_page_cache(): void
    {
        $profile = new \App\Support\PublicResponseCacheProfile();

        $this->assertFalse($profile->shouldCacheRequest(
            \Illuminate\Http\Request::create('/article/5/commenter/confirm/3', 'GET')
        ));
        // sanity: a normal article page is still cacheable
        $this->assertTrue($profile->shouldCacheRequest(
            \Illuminate\Http\Request::create('/article/some-slug', 'GET')
        ));
    }

    public function test_rate_limit_bucket_is_per_inbox_not_per_address_variant(): void
    {
        $c = \App\Support\SubscribeThrottle::class;

        // Gmail ignores +tags and dots — all of these are the same inbox.
        $base = $c::dayKey('victim@gmail.com');
        $this->assertSame($base, $c::dayKey('victim+1@gmail.com'));
        $this->assertSame($base, $c::dayKey('victim+999@gmail.com'));
        $this->assertSame($base, $c::dayKey('vic.tim@gmail.com'));
        $this->assertSame($base, $c::dayKey('VICTIM+spam@Gmail.com'));

        // Other providers: +tags stripped, but dots are significant (kept distinct).
        $this->assertSame($c::dayKey('a@yahoo.com'), $c::dayKey('a+promo@yahoo.com'));
        $this->assertNotSame($c::dayKey('a.b@yahoo.com'), $c::dayKey('ab@yahoo.com'));

        // Genuinely different inboxes stay separate.
        $this->assertNotSame($base, $c::dayKey('someone@gmail.com'));
    }

    public function test_failed_confirmation_clears_the_burst_limiter(): void
    {
        $email = 'fail@example.com';
        $key   = \App\Support\SubscribeThrottle::burstKey($email);

        \Illuminate\Support\Facades\RateLimiter::hit($key, 120);
        $this->assertTrue(\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 1));

        (new \App\Mail\CommenterConfirmation('N', 'http://u', 'Site', $email))
            ->failed(new \RuntimeException('mail down'));

        $this->assertFalse(\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 1));
    }

    public function test_burst_cooldown_prevents_a_second_send(): void
    {
        Mail::fake();
        $article = Article::factory()->published()->create();
        $payload = ['name' => 'Once', 'email' => 'cool@example.com'];

        $this->post($this->subscribeUrl($article), $payload)->assertRedirect();
        $this->post($this->subscribeUrl($article), $payload)->assertRedirect();

        Mail::assertQueued(CommenterConfirmation::class, 1);
    }

    public function test_daily_cap_limits_sends_per_email(): void
    {
        Mail::fake();
        $article = Article::factory()->published()->create();
        $payload = ['name' => 'Capped', 'email' => 'cap@example.com'];

        // 3 allowed (travel past the 2-min cooldown between each), 4th blocked.
        for ($i = 0; $i < 3; $i++) {
            $this->post($this->subscribeUrl($article), $payload)->assertRedirect();
            $this->travel(3)->minutes();
        }
        $this->post($this->subscribeUrl($article), $payload)->assertRedirect();
        $this->travelBack();

        Mail::assertQueued(CommenterConfirmation::class, 3);
    }

    public function test_mail_failure_redirects_gracefully_without_locking_out(): void
    {
        $article = Article::factory()->published()->create();

        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('mail down'));

        $this->post($this->subscribeUrl($article), ['name' => 'A', 'email' => 'a@example.com'])
            ->assertRedirect(route('article', $article->slug) . '?comment=mailfail#comments');

        // Stored (unverified) so a retry can reuse the row; no 500.
        $this->assertDatabaseHas('subscribers', ['email' => 'a@example.com', 'verified_at' => null]);
    }

    public function test_subscribe_requires_name_and_email(): void
    {
        Mail::fake();
        $article = Article::factory()->published()->create();

        $this->post($this->subscribeUrl($article), ['name' => 'No Email'])->assertSessionHasErrors('email');
        $this->assertSame(0, Subscriber::count());
        Mail::assertNothingQueued();
    }

    public function test_does_not_rename_an_already_verified_subscriber(): void
    {
        Mail::fake();
        $article = Article::factory()->published()->create();
        $sub = Subscriber::create([
            'email' => 'verified@example.com', 'name' => 'Real Name',
            'verified_at' => now(), 'created_at' => now(),
        ]);

        $this->post($this->subscribeUrl($article), ['name' => 'Imposter', 'email' => 'verified@example.com'])
            ->assertRedirect();

        $this->assertSame('Real Name', $sub->fresh()->name);
    }

    public function test_returning_subscriber_can_request_a_link_with_email_only(): void
    {
        Mail::fake();
        $article = Article::factory()->published()->create();
        Subscriber::create(['email' => 'back@example.com', 'name' => 'Back', 'verified_at' => now(), 'created_at' => now()]);

        // No name — they're already verified, so the gate must not demand it.
        $this->post($this->subscribeUrl($article), ['email' => 'back@example.com'])
            ->assertRedirect(route('article', $article->slug) . '?comment=check#comments');

        Mail::assertQueued(CommenterConfirmation::class, fn ($m) => $m->hasTo('back@example.com'));
    }

    public function test_new_subscriber_must_provide_a_name(): void
    {
        Mail::fake();
        $article = Article::factory()->published()->create();

        // Unknown email + no name -> rejected (the name shows on their comments).
        $this->post($this->subscribeUrl($article), ['email' => 'fresh@example.com'])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Subscriber::count());
        Mail::assertNothingQueued();
    }

    public function test_new_subscriber_whitespace_only_name_is_rejected(): void
    {
        Mail::fake();
        $article = Article::factory()->published()->create();

        // A spaces-only name must not satisfy 'required' (would be a blank author).
        $this->post($this->subscribeUrl($article), ['name' => '   ', 'email' => 'ws@example.com'])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Subscriber::count());
        Mail::assertNothingQueued();
    }

    public function test_subscribe_returns_json_for_an_ajax_request(): void
    {
        Mail::fake();
        $article = Article::factory()->published()->create();

        $this->postJson($this->subscribeUrl($article), ['name' => 'Ajax', 'email' => 'ajax@example.com'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        Mail::assertQueued(CommenterConfirmation::class);
    }

    public function test_sign_out_forgets_the_identity_cookie(): void
    {
        $article = Article::factory()->published()->create();

        $this->post(route('article.commenter.forget', $article))
            ->assertRedirect(route('article', $article->slug) . '#comments')
            ->assertCookieExpired('adt_commenter');
    }

    public function test_prune_deletes_only_old_abandoned_confirmations(): void
    {
        // Abandoned double opt-in: unverified, still holds a token, old.
        $abandoned   = Subscriber::create(['email' => 'abandoned@example.com', 'confirmation_token' => 'TOK', 'created_at' => now()->subDays(40)]);
        // Recent abandoned — kept (not old enough yet).
        $recent      = Subscriber::create(['email' => 'recent@example.com', 'confirmation_token' => 'TOK', 'created_at' => now()->subDays(2)]);
        // A token-less unverified row (e.g. legacy) must NOT be pruned.
        $tokenless   = Subscriber::create(['email' => 'tokenless@example.com', 'created_at' => now()->subDays(40)]);
        // Confirmed subscriber — kept.
        $confirmed   = Subscriber::create(['email' => 'keep@example.com', 'verified_at' => now(), 'created_at' => now()->subDays(40)]);

        $this->artisan('app:prune-unconfirmed-subscribers')->assertSuccessful();

        $this->assertDatabaseMissing('subscribers', ['id' => $abandoned->id]);
        $this->assertDatabaseHas('subscribers', ['id' => $recent->id]);
        $this->assertDatabaseHas('subscribers', ['id' => $tokenless->id]);
        $this->assertDatabaseHas('subscribers', ['id' => $confirmed->id]);
    }
}
