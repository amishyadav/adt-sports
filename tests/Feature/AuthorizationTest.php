<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_admin_dashboard(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_editor_cannot_access_admin_only_users_page(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get('/admin/users')->assertForbidden();
    }

    public function test_admin_can_access_users_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/users')->assertOk();
    }

    public function test_editor_cannot_update_another_authors_article(): void
    {
        $stranger = User::factory()->editor()->create();
        $article  = Article::factory()->create(); // owned by a different author

        $this->actingAs($stranger)
            ->put("/admin/articles/{$article->id}", ['title' => 'Hijacked', 'status' => 'draft'])
            ->assertForbidden();
    }

    public function test_author_can_update_their_own_article(): void
    {
        $author  = User::factory()->editor()->create();
        $article = Article::factory()->create(['author_id' => $author->id]);

        $this->actingAs($author)
            ->put("/admin/articles/{$article->id}", ['title' => 'My Edit', 'status' => 'draft'])
            ->assertRedirect();

        $this->assertSame('My Edit', $article->fresh()->title);
    }

    public function test_admin_can_update_any_article(): void
    {
        $admin   = User::factory()->admin()->create();
        $article = Article::factory()->create();

        $this->actingAs($admin)
            ->put("/admin/articles/{$article->id}", ['title' => 'Admin Edit', 'status' => 'draft'])
            ->assertRedirect();

        $this->assertSame('Admin Edit', $article->fresh()->title);
    }

    public function test_editor_article_index_shows_only_their_own(): void
    {
        $owner = User::factory()->editor()->create();
        $other = User::factory()->editor()->create();
        Article::factory()->create(['author_id' => $owner->id, 'title' => 'OWNER-ARTICLE-MARKER']);
        Article::factory()->create(['author_id' => $other->id, 'title' => 'OTHER-ARTICLE-MARKER']);

        $this->actingAs($owner)
            ->get(route('admin.articles.index'))
            ->assertOk()
            ->assertSee('OWNER-ARTICLE-MARKER', false)
            ->assertDontSee('OTHER-ARTICLE-MARKER', false);
    }

    public function test_admin_article_index_shows_all_articles(): void
    {
        $editor = User::factory()->editor()->create();
        Article::factory()->create(['author_id' => $editor->id, 'title' => 'EDITORS-ARTICLE-MARKER']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.articles.index'))
            ->assertOk()
            ->assertSee('EDITORS-ARTICLE-MARKER', false);
    }

    public function test_editor_trash_shows_only_their_own(): void
    {
        $owner = User::factory()->editor()->create();
        $other = User::factory()->editor()->create();
        Article::factory()->create(['author_id' => $owner->id, 'title' => 'OWNER-TRASH-MARKER'])->delete();
        Article::factory()->create(['author_id' => $other->id, 'title' => 'OTHER-TRASH-MARKER'])->delete();

        $this->actingAs($owner)
            ->get(route('admin.articles.trash'))
            ->assertOk()
            ->assertSee('OWNER-TRASH-MARKER', false)
            ->assertDontSee('OTHER-TRASH-MARKER', false);
    }

    public function test_editor_cannot_delete_users(): void
    {
        $editor = User::factory()->editor()->create();
        $victim = User::factory()->create();

        $this->actingAs($editor)
            ->delete("/admin/users/{$victim->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $victim->id]);
    }

    /* ── Global settings: admin-only mutation ──────────────── */

    public function test_editor_cannot_update_settings(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)
            ->put(route('admin.settings.update'), ['site_name' => 'Hacked'])
            ->assertForbidden();

        $this->assertNotSame('Hacked', Setting::get('site_name'));
    }

    public function test_admin_can_update_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), ['site_name' => 'ADT Sports'])
            ->assertSessionHas('success');

        $this->assertSame('ADT Sports', Setting::get('site_name'));
    }

    public function test_settings_rejects_javascript_url(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), ['facebook_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('facebook_url');

        $this->assertNull(Setting::get('facebook_url'));
    }

    public function test_admin_accepts_valid_https_social_url(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), ['facebook_url' => 'https://facebook.com/adtsports'])
            ->assertSessionHasNoErrors();

        $this->assertSame('https://facebook.com/adtsports', Setting::get('facebook_url'));
    }

    /* ── Taxonomy: admin-only writes ───────────────────────── */

    public function test_editor_cannot_create_categories(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)
            ->post(route('admin.categories.store'), ['name' => 'Sneaky', 'color' => '#fff'])
            ->assertForbidden();

        $this->assertDatabaseMissing('categories', ['name' => 'Sneaky']);
    }

    public function test_admin_can_create_categories(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), ['name' => 'New Cat', 'color' => '#fff'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('categories', ['name' => 'New Cat']);
    }

    /* ── Editorial model preserved ─────────────────────────── */

    public function test_editor_can_still_update_own_profile(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)
            ->put(route('admin.profile.update'), ['name' => 'New Name', 'email' => $editor->email])
            ->assertSessionHas('success');

        $this->assertSame('New Name', $editor->fresh()->name);
    }

    public function test_editor_can_still_open_own_article_editor(): void
    {
        $editor  = User::factory()->editor()->create();
        $article = Article::factory()->draft()->create(['author_id' => $editor->id]);

        $this->actingAs($editor)
            ->get(route('admin.articles.edit', $article))
            ->assertOk();
    }

    public function test_editor_cannot_open_another_authors_draft(): void
    {
        $owner   = User::factory()->editor()->create();
        $other   = User::factory()->editor()->create();
        $article = Article::factory()->draft()->create(['author_id' => $owner->id]);

        $this->actingAs($other)
            ->get(route('admin.articles.edit', $article))
            ->assertForbidden();
    }

    public function test_admin_can_open_any_article_editor(): void
    {
        $admin   = User::factory()->admin()->create();
        $article = Article::factory()->draft()->create();

        $this->actingAs($admin)
            ->get(route('admin.articles.edit', $article))
            ->assertOk();
    }
}
