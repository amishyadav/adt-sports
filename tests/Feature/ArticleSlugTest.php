<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_slug_is_used_on_create(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/articles', [
            'title' => 'A Very Long Headline About Kabaddi',
            'slug'  => 'my-custom-slug',
            'status_override' => 'draft',
        ])->assertRedirect();

        $this->assertDatabaseHas('articles', ['title' => 'A Very Long Headline About Kabaddi', 'slug' => 'my-custom-slug']);
    }

    public function test_blank_slug_auto_generates_from_title(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/articles', [
            'title' => 'Auto Slug Title',
            'status_override' => 'draft',
        ])->assertRedirect();

        $this->assertDatabaseHas('articles', ['slug' => 'auto-slug-title']);
    }

    public function test_duplicate_custom_slug_is_deduped(): void
    {
        $admin = User::factory()->admin()->create();
        Article::factory()->create(['slug' => 'taken']);

        $this->actingAs($admin)->post('/admin/articles', [
            'title' => 'Another Story',
            'slug'  => 'taken',
            'status_override' => 'draft',
        ])->assertRedirect();

        $this->assertDatabaseHas('articles', ['title' => 'Another Story', 'slug' => 'taken-1']);
    }

    public function test_update_can_change_the_slug(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Article::factory()->create(['author_id' => $admin->id, 'slug' => 'old-slug']);

        $this->actingAs($admin)->put(route('admin.articles.update', $article), [
            'title' => $article->title,
            'slug'  => 'brand-new-slug',
            'status_override' => 'draft',
        ])->assertRedirect();

        $this->assertSame('brand-new-slug', $article->fresh()->slug);
    }

    public function test_update_with_blank_slug_keeps_the_current_one(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Article::factory()->create(['author_id' => $admin->id, 'slug' => 'keep-me']);

        $this->actingAs($admin)->put(route('admin.articles.update', $article), [
            'title' => $article->title,
            'slug'  => '',
            'status_override' => 'draft',
        ])->assertRedirect();

        $this->assertSame('keep-me', $article->fresh()->slug);
    }

    public function test_full_editor_submission_with_empty_optionals_saves(): void
    {
        // Mirrors exactly what the editor form posts: every optional field is
        // present but empty. ConvertEmptyStringsToNull turns each into null, so
        // this catches any controller path that can't tolerate a null form value.
        $admin = User::factory()->admin()->create();
        $cat = Category::factory()->create();

        $this->actingAs($admin)->post('/admin/articles', [
            'title'        => 'Full Form Article',
            'slug'         => '',
            'excerpt'      => '',
            'body'         => '<p>Some body content.</p>',
            'cover_image'  => '',
            'cover_bg'     => '',
            'cover_emoji'  => '📰',
            'category_id'  => $cat->id,
            'categories'   => [$cat->id],
            'meta_title'   => '',
            'meta_desc'    => '',
            'tags'         => '',
            'published_at' => '',
            'status_override' => 'published',
        ])->assertRedirect();

        $this->assertDatabaseHas('articles', ['title' => 'Full Form Article', 'status' => 'published']);
    }

    public function test_full_editor_update_with_empty_optionals_saves(): void
    {
        // The update path differs from store (read-time guard, slug retry,
        // revision snapshot) — exercise it with the same all-empty-optionals
        // payload the editor posts, including an empty body.
        $admin = User::factory()->admin()->create();
        $cat = Category::factory()->create();
        $article = Article::factory()->create(['author_id' => $admin->id]);

        $this->actingAs($admin)->put(route('admin.articles.update', $article), [
            'title'        => 'Edited Title',
            'slug'         => '',
            'excerpt'      => '',
            'body'         => '',
            'cover_image'  => '',
            'cover_bg'     => '',
            'cover_emoji'  => '📰',
            'category_id'  => $cat->id,
            'meta_title'   => '',
            'meta_desc'    => '',
            'tags'         => '',
            'published_at' => '',
            'status_override' => 'draft',
        ])->assertRedirect();

        $this->assertDatabaseHas('articles', ['id' => $article->id, 'title' => 'Edited Title']);
    }

    public function test_saving_with_empty_tags_does_not_error(): void
    {
        // The editor submits an empty `tags` field; ConvertEmptyStringsToNull
        // turns it into null. Saving must still succeed (no tags), not 500.
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/articles', [
            'title' => 'No Tags Story', 'tags' => '', 'status_override' => 'draft',
        ])->assertRedirect();

        $article = Article::where('title', 'No Tags Story')->firstOrFail();
        $this->assertCount(0, $article->tags);
    }

    public function test_body_less_update_preserves_read_time(): void
    {
        $admin = User::factory()->admin()->create();
        // Long body -> multi-minute read time.
        $article = Article::factory()->create([
            'author_id' => $admin->id,
            'status'    => 'draft',
            'body'      => '<p>' . str_repeat('word ', 1200) . '</p>',
        ]);
        $readTime = $article->fresh()->read_time;

        // Mimic the inline publish button: title + status only, no body.
        $this->actingAs($admin)->put(route('admin.articles.update', $article), [
            'title' => $article->title, 'status' => 'published',
        ])->assertRedirect();

        $fresh = $article->fresh();
        $this->assertSame('published', $fresh->status);
        $this->assertSame($readTime, $fresh->read_time); // not clobbered to "1 min"
    }

    public function test_editor_renders_tag_suggestions(): void
    {
        $admin = User::factory()->admin()->create();
        Tag::firstOrCreate(['slug' => 'kabaddi'], ['name' => 'Kabaddi']);

        $this->actingAs($admin)->get('/admin/articles/new')
            ->assertOk()
            ->assertSee('id="tagSuggest"', false)
            ->assertSee('value="Kabaddi"', false);
    }
}
