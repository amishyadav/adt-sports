<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\TeamInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class UserInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_invite_a_team_member(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Editor', 'email' => 'editor@adtsports.in', 'role' => 'editor',
        ])->assertRedirect()->assertSessionHas('success');

        $user = User::where('email', 'editor@adtsports.in')->first();
        $this->assertNotNull($user);
        $this->assertSame('editor', $user->role);
        Notification::assertSentTo($user, TeamInvitation::class);
    }

    public function test_invite_does_not_set_a_password_the_admin_knows(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'No Pw', 'email' => 'nopw@adtsports.in', 'role' => 'editor',
        ]);

        $user = User::where('email', 'nopw@adtsports.in')->firstOrFail();
        // A random, unusable hash exists — nothing the admin chose or could guess.
        $this->assertNotEmpty($user->password);
        $this->assertFalse(Hash::check('', $user->password));
        $this->assertFalse(Hash::check('password', $user->password));
    }

    public function test_invited_member_sets_their_own_password_and_can_log_in(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Set Pw', 'email' => 'setpw@adtsports.in', 'role' => 'editor',
        ]);
        $user = User::where('email', 'setpw@adtsports.in')->firstOrFail();

        // Recipient follows the invite link (same reset form) and sets a password.
        $token = Password::broker()->createToken($user);
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'setpw@adtsports.in',
            'password' => 'my-new-password',
            'password_confirmation' => 'my-new-password',
        ])->assertRedirect(route('admin.login'));

        $this->assertTrue(Hash::check('my-new-password', $user->fresh()->password));
    }

    public function test_invite_requires_name_email_role_and_unique_email(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Y', 'email' => 'y@adtsports.in', // no role
        ])->assertSessionHasErrors('role');

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Dup', 'email' => $admin->email, 'role' => 'editor',
        ])->assertSessionHasErrors('email');
    }

    public function test_editors_cannot_invite(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->post(route('admin.users.store'), [
            'name' => 'Z', 'email' => 'z@adtsports.in', 'role' => 'editor',
        ])->assertForbidden();
    }
}
