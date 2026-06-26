<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\ResponseCache\Facades\ResponseCache;
use Tests\TestCase;

class BulkActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_trash_moves_selected_articles(): void
    {
        $admin = User::factory()->admin()->create();
        $a = Article::factory()->create(['author_id' => $admin->id]);
        $b = Article::factory()->create(['author_id' => $admin->id]);

        $this->actingAs($admin)->post(route('admin.articles.bulk'), [
            'action' => 'trash', 'ids' => [$a->id, $b->id],
        ])->assertRedirect();

        $this->assertSoftDeleted('articles', ['id' => $a->id]);
        $this->assertSoftDeleted('articles', ['id' => $b->id]);
    }

    public function test_bulk_publish_sets_status_published(): void
    {
        $admin = User::factory()->admin()->create();
        $draft = Article::factory()->create(['author_id' => $admin->id, 'status' => 'draft', 'published_at' => null]);

        $this->actingAs($admin)->post(route('admin.articles.bulk'), [
            'action' => 'publish', 'ids' => [$draft->id],
        ])->assertRedirect();

        $fresh = $draft->fresh();
        $this->assertSame('published', $fresh->status);
        $this->assertNotNull($fresh->published_at);
    }

    public function test_editor_cannot_bulk_act_on_another_authors_articles(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $other  = User::factory()->create(['role' => 'editor']);
        $mine   = Article::factory()->create(['author_id' => $editor->id]);
        $theirs = Article::factory()->create(['author_id' => $other->id]);

        $this->actingAs($editor)->post(route('admin.articles.bulk'), [
            'action' => 'trash', 'ids' => [$mine->id, $theirs->id],
        ])->assertRedirect();

        // Only the editor's own article is trashed; the other is untouched.
        $this->assertSoftDeleted('articles', ['id' => $mine->id]);
        $this->assertDatabaseHas('articles', ['id' => $theirs->id, 'deleted_at' => null]);
    }

    public function test_bulk_requires_a_valid_action(): void
    {
        $admin = User::factory()->admin()->create();
        $a = Article::factory()->create(['author_id' => $admin->id]);

        $this->actingAs($admin)->post(route('admin.articles.bulk'), [
            'action' => 'nuke', 'ids' => [$a->id],
        ])->assertSessionHasErrors('action');
    }

    public function test_bulk_rejects_an_oversized_id_list(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.articles.bulk'), [
            'action' => 'trash', 'ids' => range(1, 201),
        ])->assertSessionHasErrors('ids');
    }

    public function test_bulk_invalidates_the_cache_once_not_once_per_article(): void
    {
        $admin  = User::factory()->admin()->create();
        $drafts = Article::factory()->count(5)->create([
            'author_id' => $admin->id, 'status' => 'draft', 'published_at' => null,
        ]);

        ResponseCache::spy();

        $this->actingAs($admin)->post(route('admin.articles.bulk'), [
            'action' => 'publish', 'ids' => $drafts->pluck('id')->all(),
        ])->assertRedirect();

        // Per-row forgets are muted during the loop; the batch is cleared exactly
        // once afterwards — not five times.
        ResponseCache::shouldHaveReceived('clear')->once();
    }

    public function test_list_renders_checkboxes_and_bulk_bar(): void
    {
        $admin = User::factory()->admin()->create();
        Article::factory()->create(['author_id' => $admin->id]);

        $this->actingAs($admin)->get(route('admin.articles.index'))
            ->assertOk()
            ->assertSee('id="bulkForm"', false)
            ->assertSee('name="ids[]"', false);
    }
}
