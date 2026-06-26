<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishing_an_article_is_logged_with_actor_and_subject(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/articles', [
            'title' => 'Logged Story', 'status_override' => 'published',
        ])->assertRedirect();

        $log = ActivityLog::where('action', 'article.created')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(Article::first()->id, $log->subject_id);
        $this->assertStringContainsString('Published', $log->description);
    }

    public function test_trashing_an_article_is_logged(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Article::factory()->create(['author_id' => $admin->id]);

        $this->actingAs($admin)->delete(route('admin.articles.destroy', $article))->assertRedirect();

        $this->assertDatabaseHas('activity_logs', ['action' => 'article.trashed', 'subject_id' => $article->id]);
    }

    public function test_category_creation_is_logged(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Pro Kabaddi', 'color' => '#D4420A',
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_logs', ['action' => 'category.created']);
    }

    public function test_activity_page_lists_entries_and_is_admin_only(): void
    {
        $admin = User::factory()->admin()->create();
        ActivityLog::record('article.created', null, 'A sample logged action');

        $this->actingAs($admin)->get(route('admin.activity.index'))
            ->assertOk()
            ->assertSee('Activity Log', false)
            ->assertSee('A sample logged action', false);
    }

    public function test_editor_cannot_open_activity_page(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->get(route('admin.activity.index'))->assertForbidden();
    }
}
