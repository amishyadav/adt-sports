<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_log_in_with_valid_credentials(): void
    {
        $user = User::factory()->admin()->create();

        $this->post('/admin/login', [
            'email'    => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->post('/admin/login', [
            'email'    => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        $email = 'target@example.com';
        User::factory()->create(['email' => $email]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => $email, 'password' => 'nope'])
                ->assertStatus(302); // invalid credentials, not yet throttled
        }

        $this->post('/admin/login', ['email' => $email, 'password' => 'nope'])
            ->assertStatus(429); // 6th attempt is throttled
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/admin/logout')
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }
}
