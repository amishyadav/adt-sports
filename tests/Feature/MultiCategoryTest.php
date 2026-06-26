<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_can_be_assigned_additional_categories(): void
    {
        $admin   = User::factory()->admin()->create();
        $primary = Category::factory()->create();
        $extra   = Category::factory()->create();

        $this->actingAs($admin)->post('/admin/articles', [
            'title'           => 'Multi Cat',
            'status_override' => 'published',
            'category_id'     => $primary->id,
            'categories'      => [$extra->id],
        ])->assertRedirect();

        $article = Article::where('title', 'Multi Cat')->first();
        $this->assertSame($primary->id, $article->category_id);
        $this->assertEqualsCanonicalizing([$extra->id], $article->categories->pluck('id')->all());
    }

    public function test_primary_is_not_duplicated_into_additional(): void
    {
        $admin   = User::factory()->admin()->create();
        $primary = Category::factory()->create();
        $extra   = Category::factory()->create();

        $this->actingAs($admin)->post('/admin/articles', [
            'title'           => 'Dedup Cat',
            'status_override' => 'published',
            'category_id'     => $primary->id,
            'categories'      => [$primary->id, $extra->id], // primary included on purpose
        ])->assertRedirect();

        $article = Article::where('title', 'Dedup Cat')->first();
        $this->assertEqualsCanonicalizing([$extra->id], $article->categories->pluck('id')->all());
    }

    public function test_article_shows_on_both_primary_and_additional_category_pages(): void
    {
        $primary = Category::factory()->create();
        $extra   = Category::factory()->create();
        $article = Article::factory()->published()->create(['title' => 'Cross Posted', 'category_id' => $primary->id]);
        $article->categories()->sync([$extra->id]);

        $this->get("/category/{$primary->slug}")->assertOk()->assertSee('Cross Posted', false);
        $this->get("/category/{$extra->slug}")->assertOk()->assertSee('Cross Posted', false);
    }

    public function test_article_absent_from_unrelated_category_listing(): void
    {
        $primary = Category::factory()->create();
        $other   = Category::factory()->create();
        // Assert on the excerpt: it renders only in the main category listing,
        // not the global trending sidebar (which shows titles only).
        Article::factory()->published()->create([
            'title'      => 'Only Primary',
            'excerpt'    => 'UNIQUE-EXCERPT-MARKER-XYZ',
            'category_id' => $primary->id,
        ]);

        $this->get("/category/{$other->slug}")
            ->assertOk()
            ->assertDontSee('UNIQUE-EXCERPT-MARKER-XYZ', false);

        // Sanity: it DOES appear on its own category listing.
        $this->get("/category/{$primary->slug}")
            ->assertSee('UNIQUE-EXCERPT-MARKER-XYZ', false);
    }

    public function test_trashed_multi_category_article_does_not_leak_to_category_pages(): void
    {
        $primary = Category::factory()->create();
        $extra   = Category::factory()->create();
        $article = Article::factory()->published()->create(['title' => 'Soon Trashed', 'category_id' => $primary->id]);
        $article->categories()->sync([$extra->id]);
        $article->delete();

        $this->get("/category/{$extra->slug}")->assertOk()->assertDontSee('Soon Trashed', false);
    }

    public function test_homepage_category_filter_includes_additional_category_articles(): void
    {
        $primary = Category::factory()->create();
        $extra   = Category::factory()->create();
        $article = Article::factory()->published()->create([
            'title'       => 'Cross Filter',
            'excerpt'     => 'HOME-FILTER-MARKER-XYZ',
            'category_id' => $primary->id,
        ]);
        $article->categories()->sync([$extra->id]);

        // Filtering the homepage by the ADDITIONAL category must surface the article,
        // matching the /category/{slug} page behaviour.
        $this->get('/?category=' . $extra->slug)
            ->assertOk()
            ->assertSee('HOME-FILTER-MARKER-XYZ', false);
    }

    public function test_editor_renders_additional_category_checkboxes(): void
    {
        $admin = User::factory()->admin()->create();
        Category::factory()->create(['name' => 'Zebra Cat']);

        $this->actingAs($admin)->get('/admin/articles/new')
            ->assertOk()
            ->assertSee('Additional categories', false)
            ->assertSee('name="categories[]"', false);
    }

    public function test_publish_works_from_button_without_a_status_field(): void
    {
        $admin = User::factory()->admin()->create();

        // New form: the Publish button carries status_override; there is no Status dropdown.
        $this->actingAs($admin)->post('/admin/articles', [
            'title'           => 'Button Publish',
            'status_override' => 'published',
        ])->assertRedirect();

        $article = Article::where('title', 'Button Publish')->first();
        $this->assertSame('published', $article->status);
        $this->assertTrue($article->isPublished());
    }

    public function test_refresh_count_includes_pivot_categories(): void
    {
        $primary = Category::factory()->create();
        $extra   = Category::factory()->create();

        $article = Article::factory()->published()->create(['category_id' => $primary->id]);
        $article->categories()->sync([$extra->id]);

        // The pivot (additional) category must count the cross-posted article.
        $extra->refreshCount();
        $this->assertSame(1, $extra->fresh()->article_count);

        // And the primary still counts it too.
        $primary->refreshCount();
        $this->assertSame(1, $primary->fresh()->article_count);
    }

    public function test_refresh_count_excludes_drafts(): void
    {
        $category = Category::factory()->create();
        Article::factory()->draft()->create(['category_id' => $category->id]);

        $category->refreshCount();
        $this->assertSame(0, $category->fresh()->article_count);
    }

    public function test_creating_article_refreshes_additional_category_count(): void
    {
        $admin   = User::factory()->admin()->create();
        $primary = Category::factory()->create();
        $extra   = Category::factory()->create();

        $this->actingAs($admin)->post('/admin/articles', [
            'title'           => 'Counted Cross Post',
            'status_override' => 'published',
            'category_id'     => $primary->id,
            'categories'      => [$extra->id],
        ])->assertRedirect();

        // Both the primary and the additional category should reflect the article.
        $this->assertSame(1, $primary->fresh()->article_count);
        $this->assertSame(1, $extra->fresh()->article_count);
    }
}
