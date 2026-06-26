<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatedAndNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_related_prefers_articles_with_shared_tags(): void
    {
        $subject = Article::factory()->published()->withTags(['kabaddi', 'pkl', 'finals'])->create();

        $strongMatch = Article::factory()->published()->withTags(['pkl', 'finals'])->create(); // 2 shared
        $weakMatch   = Article::factory()->published()->withTags(['pkl'])->create();            // 1 shared
        $noMatch     = Article::factory()->published()->withTags(['cricket'])->create();        // 0 shared

        $related = $subject->getRelated(3)->pluck('id');

        $this->assertTrue($related->contains($strongMatch->id));
        $this->assertTrue($related->contains($weakMatch->id));
        // Strongest overlap ranks first.
        $this->assertSame($strongMatch->id, $subject->getRelated(3)->first()->id);
    }

    public function test_related_falls_back_to_category_without_tags(): void
    {
        $category = Category::factory()->create();
        $subject  = Article::factory()->published()->withTags([])->create(['category_id' => $category->id]);
        $sameCat  = Article::factory()->published()->withTags([])->create(['category_id' => $category->id]);

        $this->assertTrue($subject->getRelated(3)->pluck('id')->contains($sameCat->id));
    }

    public function test_related_excludes_self_and_drafts(): void
    {
        $subject = Article::factory()->published()->withTags(['pkl'])->create();
        $draft   = Article::factory()->draft()->withTags(['pkl'])->create();

        $related = $subject->getRelated(3)->pluck('id');
        $this->assertFalse($related->contains($subject->id));
        $this->assertFalse($related->contains($draft->id));
    }

    public function test_previous_and_next_navigation_by_publish_date(): void
    {
        $older  = Article::factory()->published()->create(['published_at' => now()->subDays(3)]);
        $middle = Article::factory()->published()->create(['published_at' => now()->subDays(2)]);
        $newer  = Article::factory()->published()->create(['published_at' => now()->subDay()]);

        $this->assertSame($older->id, $middle->previousArticle()->id);
        $this->assertSame($newer->id, $middle->nextArticle()->id);
        $this->assertNull($newer->nextArticle());
        $this->assertNull($older->previousArticle());
    }

    public function test_article_page_renders_navigation_links(): void
    {
        $older   = Article::factory()->published()->create(['published_at' => now()->subDays(2), 'title' => 'The Older One']);
        $current = Article::factory()->published()->create(['published_at' => now()->subDay()]);

        $this->get("/article/{$current->slug}")
            ->assertOk()
            ->assertSee('rel="prev"', false)
            ->assertSee('The Older One', false);
    }
}
