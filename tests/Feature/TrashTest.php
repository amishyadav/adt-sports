<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrashTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_soft_deletes_and_hides_from_public(): void
    {
        $admin   = User::factory()->admin()->create();
        $article = Article::factory()->published()->create();

        $this->actingAs($admin)->delete("/admin/articles/{$article->id}")->assertRedirect();

        $this->assertSoftDeleted('articles', ['id' => $article->id]);
        $this->get("/article/{$article->slug}")->assertNotFound();      // gone from public
        $this->get('/')->assertDontSee($article->title, false);          // gone from listings
    }

    public function test_admin_can_restore_a_trashed_article(): void
    {
        $admin   = User::factory()->admin()->create();
        $article = Article::factory()->published()->create();
        $article->delete();

        $this->actingAs($admin)->put("/admin/articles/{$article->id}/restore")->assertRedirect();

        $this->assertNotSoftDeleted('articles', ['id' => $article->id]);
        $this->get("/article/{$article->slug}")->assertOk();
    }

    public function test_admin_can_permanently_delete(): void
    {
        $admin   = User::factory()->admin()->create();
        $article = Article::factory()->create();
        $article->delete();

        $this->actingAs($admin)->delete("/admin/articles/{$article->id}/force")->assertRedirect();

        $this->assertDatabaseMissing('articles', ['id' => $article->id]);
    }

    public function test_trash_page_lists_only_trashed_articles(): void
    {
        $admin    = User::factory()->admin()->create();
        $live     = Article::factory()->published()->create(['title' => 'Still Live AAA']);
        $trashed  = Article::factory()->published()->create(['title' => 'In The Bin BBB']);
        $trashed->delete();

        $this->actingAs($admin)->get('/admin/articles/trash')
            ->assertOk()
            ->assertSee('In The Bin BBB', false)
            ->assertDontSee('Still Live AAA', false);
    }

    public function test_editor_cannot_restore_another_authors_article(): void
    {
        $stranger = User::factory()->editor()->create();
        $article  = Article::factory()->create();
        $article->delete();

        $this->actingAs($stranger)
            ->put("/admin/articles/{$article->id}/restore")
            ->assertForbidden();

        $this->assertSoftDeleted('articles', ['id' => $article->id]);
    }

    public function test_trashing_updates_category_count(): void
    {
        $admin    = User::factory()->admin()->create();
        $category = \App\Models\Category::factory()->create();
        $article  = Article::factory()->published()->create(['category_id' => $category->id]);
        $this->assertSame(1, $category->fresh()->article_count);

        $this->actingAs($admin)->delete("/admin/articles/{$article->id}");

        $this->assertSame(0, $category->fresh()->article_count);
    }
}
