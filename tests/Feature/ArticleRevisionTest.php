<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_content_creates_a_revision_of_the_prior_version(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Article::factory()->create([
            'author_id' => $admin->id, 'title' => 'Original Title', 'body' => '<p>Original body</p>',
        ]);

        $this->actingAs($admin)->put(route('admin.articles.update', $article), [
            'title' => 'Updated Title', 'body' => '<p>New body</p>', 'status_override' => 'draft',
        ])->assertRedirect();

        $rev = ArticleRevision::where('article_id', $article->id)->first();
        $this->assertNotNull($rev);
        $this->assertSame('Original Title', $rev->title);
        $this->assertStringContainsString('Original body', $rev->body);
        $this->assertSame($admin->id, $rev->user_id);
    }

    public function test_saving_without_content_changes_creates_no_revision(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Article::factory()->create(['author_id' => $admin->id, 'title' => 'Same', 'body' => '<p>Same body</p>']);

        $this->actingAs($admin)->put(route('admin.articles.update', $article), [
            'title' => 'Same', 'body' => '<p>Same body</p>', 'status_override' => 'draft',
        ])->assertRedirect();

        $this->assertSame(0, ArticleRevision::where('article_id', $article->id)->count());
    }

    public function test_revisions_are_capped_at_twenty(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Article::factory()->create(['author_id' => $admin->id, 'body' => '<p>v0</p>']);

        for ($i = 1; $i <= 23; $i++) {
            $this->actingAs($admin)->put(route('admin.articles.update', $article), [
                'title' => $article->title, 'body' => "<p>v{$i}</p>", 'status_override' => 'draft',
            ]);
        }

        $this->assertSame(20, ArticleRevision::where('article_id', $article->id)->count());
    }

    public function test_revision_endpoint_returns_past_content(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Article::factory()->create(['author_id' => $admin->id, 'title' => 'V1', 'body' => '<p>body one</p>']);

        $this->actingAs($admin)->put(route('admin.articles.update', $article), [
            'title' => 'V2', 'body' => '<p>body two</p>', 'status_override' => 'draft',
        ]);

        $rev = ArticleRevision::where('article_id', $article->id)->firstOrFail();

        $this->actingAs($admin)->get(route('admin.articles.revision', [$article, $rev]))
            ->assertOk()
            ->assertJson(['title' => 'V1'])
            ->assertJsonFragment(['body' => '<p>body one</p>']);
    }

    public function test_revision_of_another_article_is_not_accessible(): void
    {
        $admin = User::factory()->admin()->create();
        $a = Article::factory()->create(['author_id' => $admin->id]);
        $b = Article::factory()->create(['author_id' => $admin->id]);
        $rev = ArticleRevision::create([
            'article_id' => $b->id, 'user_id' => $admin->id, 'title' => 'x', 'body' => 'y', 'created_at' => now(),
        ]);

        // Revision belongs to $b but requested under $a — must 404.
        $this->actingAs($admin)->get(route('admin.articles.revision', [$a, $rev]))->assertNotFound();
    }

    public function test_editor_cannot_read_revisions_of_another_authors_article(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $other  = User::factory()->create(['role' => 'editor']);
        $article = Article::factory()->create(['author_id' => $other->id]);
        $rev = ArticleRevision::create([
            'article_id' => $article->id, 'user_id' => $other->id, 'title' => 'x', 'body' => 'y', 'created_at' => now(),
        ]);

        $this->actingAs($editor)->get(route('admin.articles.revision', [$article, $rev]))->assertForbidden();
    }
}
