<?php

namespace Tests\Feature;

use App\Mail\CommenterConfirmation;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SubscriberTest extends TestCase
{
    use RefreshDatabase;

    private function signedConfirm(Subscriber $s, ?string $token = null): string
    {
        return URL::temporarySignedRoute('subscribe.confirm', now()->addHour(), [
            'subscriber' => $s->id,
            't'          => $token ?? $s->confirmation_token,
        ]);
    }

    public function test_subscribe_emails_a_confirmation_and_stays_unverified(): void
    {
        Mail::fake();

        $this->postJson('/subscribe', ['name' => 'Fan One', 'email' => 'Fan@Example.com', 'source' => 'home'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $sub = Subscriber::where('email', 'fan@example.com')->firstOrFail();
        $this->assertSame('Fan One', $sub->name);
        $this->assertNull($sub->verified_at);            // not on the list yet
        $this->assertNotNull($sub->confirmation_token);

        Mail::assertQueued(CommenterConfirmation::class, fn ($m) => $m->hasTo('fan@example.com'));
    }

    public function test_subscribe_requires_a_name(): void
    {
        Mail::fake();
        $this->postJson('/subscribe', ['email' => 'noname@example.com'])->assertStatus(422);
        $this->assertSame(0, Subscriber::count());
        Mail::assertNothingQueued();
    }

    public function test_subscribe_accepts_the_modal_source(): void
    {
        Mail::fake();

        // 'modal' is the source the Daily Digest dialog sends — it must validate,
        // or the modal 422s and the user sees a generic "something went wrong".
        $this->postJson('/subscribe', ['name' => 'Modal Fan', 'email' => 'modal@example.com', 'source' => 'modal'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame('modal', Subscriber::where('email', 'modal@example.com')->value('source'));
        Mail::assertQueued(CommenterConfirmation::class);
    }

    public function test_already_verified_subscriber_is_not_re_emailed(): void
    {
        Mail::fake();
        Subscriber::create(['email' => 'done@example.com', 'name' => 'Done', 'verified_at' => now(), 'created_at' => now()]);

        // Re-subscribing (e.g. via the modal after "not you?") gets a friendly
        // message, not a re-send and not a generic error.
        $this->postJson('/subscribe', ['name' => 'Done', 'email' => 'done@example.com', 'source' => 'modal'])
            ->assertOk()
            ->assertJsonFragment(['message' => "✅ You're already subscribed!"]);

        Mail::assertNothingQueued();
    }

    public function test_honeypot_blocks_subscribe_silently(): void
    {
        Mail::fake();
        $this->postJson('/subscribe', ['name' => 'Bot', 'email' => 'bot@example.com', 'hp_url' => 'http://spam'])
            ->assertOk();

        $this->assertSame(0, Subscriber::count());
        Mail::assertNothingQueued();
    }

    public function test_confirm_get_renders_without_side_effects(): void
    {
        $sub = Subscriber::create(['email' => 'g@example.com', 'name' => 'G', 'created_at' => now(), 'confirmation_token' => 'TOKEN123']);

        $this->get($this->signedConfirm($sub))
            ->assertOk()
            ->assertSee('Confirm my email', false);

        $this->assertNull($sub->fresh()->verified_at);
    }

    public function test_confirm_post_verifies_and_issues_identity_cookie(): void
    {
        $sub = Subscriber::create(['email' => 'p@example.com', 'name' => 'P', 'created_at' => now(), 'confirmation_token' => 'TOKEN123']);
        $url = $this->signedConfirm($sub);

        $this->post($url)
            ->assertOk()
            ->assertSee("You're subscribed", false)
            ->assertCookie('adt_commenter');

        $sub->refresh();
        $this->assertNotNull($sub->verified_at);
        $this->assertNull($sub->confirmation_token); // single-use

        // Re-using the link fails.
        $this->post($url)->assertRedirect(route('home'));
    }

    public function test_confirm_rejects_an_unsigned_link(): void
    {
        $sub = Subscriber::create(['email' => 'x@example.com', 'created_at' => now(), 'confirmation_token' => 'TOK']);

        $this->get(route('subscribe.confirm', ['subscriber' => $sub->id, 't' => 'TOK']))->assertForbidden();
        $this->assertNull($sub->fresh()->verified_at);
    }

    public function test_invalid_email_is_rejected(): void
    {
        Mail::fake();
        $this->postJson('/subscribe', ['name' => 'X', 'email' => 'not-an-email'])->assertStatus(422);
        $this->assertSame(0, Subscriber::count());
    }

    public function test_malicious_source_is_rejected(): void
    {
        $this->postJson('/subscribe', [
            'name'   => 'Ok',
            'email'  => 'ok@example.com',
            'source' => '=HYPERLINK("http://evil","x")',
        ])->assertStatus(422);

        $this->assertSame(0, Subscriber::count());
    }

    public function test_csv_export_neutralizes_formula_cells(): void
    {
        Subscriber::create(['email' => '+evil@example.com', 'source' => 'home', 'created_at' => now()]);
        $admin = User::factory()->admin()->create();

        $content = $this->actingAs($admin)->get(route('admin.subscribers.export'))->streamedContent();

        $this->assertStringContainsString("'+evil@example.com", $content);
    }

    public function test_subscriber_list_is_admin_only(): void
    {
        $editor = User::factory()->editor()->create();
        $this->actingAs($editor)->get(route('admin.subscribers.index'))->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.subscribers.index'))->assertOk();
    }

    public function test_admin_can_export_csv(): void
    {
        Subscriber::create(['email' => 'export@example.com', 'created_at' => now()]);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.subscribers.export'));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('export@example.com', $response->streamedContent());
    }
}
