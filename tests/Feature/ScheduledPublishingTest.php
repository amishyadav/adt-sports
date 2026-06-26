<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledPublishingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_schedule_a_post_for_the_future(): void
    {
        $admin = User::factory()->admin()->create();
        $when  = now()->addDays(2);

        $this->actingAs($admin)->post('/admin/articles', [
            'title'           => 'Future Headline',
            'status'          => 'draft',
            'status_override' => 'published',
            'published_at'    => $when->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $article = Article::where('title', 'Future Headline')->first();
        $this->assertSame('published', $article->status);
        $this->assertTrue($article->isScheduled());
        $this->assertFalse($article->isPublished());
        $this->assertEqualsWithDelta($when->timestamp, $article->published_at->timestamp, 5);
    }

    public function test_scheduled_post_is_hidden_from_the_public_until_its_time(): void
    {
        $article = Article::factory()->scheduled()->create(); // published_at = now()+1 week

        // Hidden right now…
        $this->get("/article/{$article->slug}")->assertNotFound();

        // …and absent from the home listing.
        $this->get('/')->assertDontSee($article->title, false);
    }

    public function test_scheduled_post_goes_live_automatically_when_its_time_arrives(): void
    {
        $article = Article::factory()->scheduled()->create(); // ~1 week out

        $this->get("/article/{$article->slug}")->assertNotFound();

        // Travel past the scheduled moment — no cron needed; visibility is time-based.
        $this->travelTo($article->published_at->copy()->addMinute(), function () use ($article) {
            $this->get("/article/{$article->slug}")->assertOk()->assertSee($article->title, false);
        });
    }

    public function test_publishing_without_a_date_publishes_immediately(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/articles', [
            'title'           => 'Publish Now',
            'status'          => 'draft',
            'status_override' => 'published',
        ])->assertRedirect();

        $article = Article::where('title', 'Publish Now')->first();
        $this->assertTrue($article->isPublished());
        $this->assertFalse($article->isScheduled());
    }

    public function test_saving_as_draft_clears_any_publish_date(): void
    {
        $admin   = User::factory()->admin()->create();
        $article = Article::factory()->published()->create(['author_id' => $admin->id]);

        $this->actingAs($admin)->put("/admin/articles/{$article->id}", [
            'title'        => $article->title,
            'status'       => 'draft',
            'published_at' => now()->addWeek()->format('Y-m-d H:i:s'), // ignored for drafts
        ])->assertRedirect();

        $article->refresh();
        $this->assertSame('draft', $article->status);
        $this->assertNull($article->published_at);
    }

    public function test_editor_page_renders_the_publish_date_field(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/articles/new')
            ->assertOk()
            ->assertSee('Publish date', false);
    }

    public function test_admin_list_marks_scheduled_posts(): void
    {
        $admin = User::factory()->admin()->create();
        Article::factory()->scheduled()->create(['title' => 'Sched One']);

        $this->actingAs($admin)->get('/admin/articles')
            ->assertOk()
            ->assertSee('scheduled', false);
    }

    public function test_rescheduling_an_existing_post_updates_its_date(): void
    {
        $admin   = User::factory()->admin()->create();
        $article = Article::factory()->published()->create(['author_id' => $admin->id]);
        $newWhen = now()->addDays(3);

        $this->actingAs($admin)->put("/admin/articles/{$article->id}", [
            'title'           => $article->title,
            'status'          => 'draft',
            'status_override' => 'published',
            'published_at'    => $newWhen->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $article->refresh();
        $this->assertTrue($article->isScheduled());
        $this->assertEqualsWithDelta($newWhen->timestamp, $article->published_at->timestamp, 5);
    }
}
