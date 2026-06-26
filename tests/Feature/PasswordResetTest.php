<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\QueuedResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_and_reset_pages_render(): void
    {
        $this->get(route('password.request'))->assertOk()->assertSee('Forgot your password', false);
        $this->get(route('password.reset', ['token' => 'abc']))->assertOk()->assertSee('Set a new password', false);
    }

    public function test_login_page_links_to_forgot_password(): void
    {
        $this->get(route('admin.login'))->assertOk()->assertSee(route('password.request'), false);
    }

    public function test_request_sends_reset_notification_to_known_user(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'editor@adtsports.in']);

        $this->post(route('password.email'), ['email' => 'editor@adtsports.in'])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, QueuedResetPassword::class);
    }

    public function test_unknown_email_sends_nothing_but_still_reports_success(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nobody@nowhere.test'])
            ->assertRedirect()
            ->assertSessionHas('status'); // no enumeration: same neutral message

        Notification::assertNothingSent();
    }

    public function test_valid_token_resets_password(): void
    {
        $user = User::factory()->create(['email' => 'reset@adtsports.in', 'password' => Hash::make('oldpassword')]);
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'reset@adtsports.in',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect(route('admin.login'))->assertSessionHas('status');

        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'reset2@adtsports.in', 'password' => Hash::make('oldpassword')]);

        $this->post(route('password.update'), [
            'token' => 'totally-wrong-token',
            'email' => 'reset2@adtsports.in',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('oldpassword', $user->fresh()->password));
    }

    public function test_reset_requires_matching_confirmation(): void
    {
        $user = User::factory()->create(['email' => 'reset3@adtsports.in']);
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'reset3@adtsports.in',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'different-pass',
        ])->assertSessionHasErrors('password');
    }
}
