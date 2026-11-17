<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Add/Edit User screens, which replaced the invite flow: an administrator
 * now fills in the whole account, including its password.
 */
class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    /** The migration seeds these two, so they exist in a fresh test database. */
    private function role(string $name): Role
    {
        return Role::where('name', $name)->where('guard_name', 'web')->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Nina',
            'last_name'  => 'Rao',
            'email'      => 'nina@adtsports.in',
            'phone'      => '+91 98765 43210',
            'gender'     => 'female',
            'status'     => 'active',
            'role_id'    => $this->role('editor')->id,
            'password'   => 'secret-pass-99',
            'password_confirmation' => 'secret-pass-99',
        ], $overrides);
    }

    public function test_add_and_edit_are_their_own_pages(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.users.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.edit', $other))->assertOk();
    }

    public function test_admin_can_add_a_user_with_every_field(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->payload())
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $user = User::where('email', 'nina@adtsports.in')->firstOrFail();

        $this->assertSame('Nina', $user->first_name);
        $this->assertSame('Rao', $user->last_name);
        $this->assertSame('+91 98765 43210', $user->phone);
        $this->assertSame('female', $user->gender);
        $this->assertSame('active', $user->status);
        $this->assertTrue(Hash::check('secret-pass-99', $user->password));
    }

    public function test_name_column_is_kept_in_step_with_first_and_last(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), $this->payload());

        // ~15 places still read $user->name, so it must stay populated.
        $user = User::where('email', 'nina@adtsports.in')->firstOrFail();
        $this->assertSame('Nina Rao', $user->name);
        $this->assertSame('NR', $user->initials);
    }

    public function test_chosen_role_is_both_assigned_and_mirrored_into_the_column(): void
    {
        $admin = User::factory()->admin()->create();
        $role  = $this->role('editor');

        $this->actingAs($admin)->post(route('admin.users.store'), $this->payload(['role_id' => $role->id]));

        $user = User::where('email', 'nina@adtsports.in')->firstOrFail();

        // The assignment, which the Roles screen counts.
        $this->assertTrue($user->hasRole($role->name));
        // And the column, which the admin gate reads.
        $this->assertSame($role->name, $user->role);
    }

    public function test_password_is_required_and_must_be_confirmed_when_adding(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->payload(['password' => '', 'password_confirmation' => '']))
            ->assertSessionHasErrors('password');

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->payload(['password_confirmation' => 'something-else']))
            ->assertSessionHasErrors('password');
    }

    public function test_required_fields_and_unique_email_are_enforced(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->payload(['first_name' => '', 'role_id' => '']))
            ->assertSessionHasErrors(['first_name', 'role_id']);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->payload(['email' => $admin->email]))
            ->assertSessionHasErrors('email');
    }

    public function test_editing_leaves_the_password_alone_when_both_boxes_are_blank(): void
    {
        $admin = User::factory()->admin()->create();
        $user  = User::factory()->create(); // factory password is 'password'

        $this->actingAs($admin)->put(route('admin.users.update', $user), $this->payload([
            'email'    => $user->email,
            'password' => '',
            'password_confirmation' => '',
        ]))->assertRedirect(route('admin.users.index'));

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->assertSame('Nina Rao', $user->fresh()->name);
    }

    public function test_editing_changes_the_password_when_one_is_typed(): void
    {
        $admin = User::factory()->admin()->create();
        $user  = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $user), $this->payload([
            'email'    => $user->email,
            'password' => 'a-brand-new-one',
            'password_confirmation' => 'a-brand-new-one',
        ]));

        $this->assertTrue(Hash::check('a-brand-new-one', $user->fresh()->password));
    }

    public function test_an_inactive_account_cannot_log_in(): void
    {
        $user = User::factory()->create(['status' => 'inactive']);

        $this->post(route('admin.login.post'), [
            'email' => $user->email, 'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        // A refused attempt must not look like a successful sign-in.
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_an_active_account_can_still_log_in(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->post(route('admin.login.post'), [
            'email' => $user->email, 'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_deactivating_someone_ends_their_session_on_the_next_request(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();

        $user->update(['status' => 'inactive']);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_you_cannot_deactivate_or_demote_your_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $admin), $this->payload([
            'email'   => $admin->email,
            'role_id' => $this->role('admin')->id,
            'status'  => 'inactive',
            'password' => '', 'password_confirmation' => '',
        ]))->assertSessionHas('error');

        $this->assertSame('active', $admin->fresh()->status);

        $this->actingAs($admin)->put(route('admin.users.update', $admin), $this->payload([
            'email'   => $admin->email,
            'role_id' => $this->role('editor')->id,
            'status'  => 'active',
            'password' => '', 'password_confirmation' => '',
        ]))->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_the_admin_gate_tolerates_a_capitalised_role_name(): void
    {
        // Role names are typed on the Roles screen, so "Admin" is as likely as
        // "admin"; the gate must not care.
        $admin = User::factory()->create(['role' => 'Admin']);

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->hasPanelAccess());
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
    }

    public function test_a_role_outside_the_panel_list_has_no_panel_access(): void
    {
        $user = User::factory()->create(['role' => 'Scoreboard']);

        $this->assertFalse($user->hasPanelAccess());
        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_editors_cannot_add_or_edit_users(): void
    {
        $editor = User::factory()->editor()->create();
        $other  = User::factory()->create();

        $this->actingAs($editor)->get(route('admin.users.create'))->assertForbidden();
        $this->actingAs($editor)->post(route('admin.users.store'), $this->payload())->assertForbidden();
        $this->actingAs($editor)->get(route('admin.users.edit', $other))->assertForbidden();
        $this->actingAs($editor)->put(route('admin.users.update', $other), $this->payload())->assertForbidden();
    }

    public function test_deleting_a_user_reassigns_their_articles(): void
    {
        $admin = User::factory()->admin()->create();
        $user  = User::factory()->create();
        $article = \App\Models\Article::factory()->create(['author_id' => $user->id]);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $user))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertNull(User::find($user->id));
        $this->assertSame($admin->id, $article->fresh()->author_id);
    }
}
