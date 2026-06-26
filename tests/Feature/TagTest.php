<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Setting;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    public function test_tag_page_lists_all_articles_sharing_the_tag(): void
    {
        Setting::set('articles_per_page', 10);

        Article::factory()->published()->withTags(['PKL', 'Finals'])->create(['title' => 'Alpha story']);
        Article::factory()->published()->withTags(['PKL'])->create(['title' => 'Bravo story']);
        Article::factory()->published()->withTags(['Cricket'])->create(['title' => 'Unrelated story']);

        $tag = Tag::where('slug', 'pkl')->firstOrFail();

        // The article list (not the all-articles trending sidebar) holds exactly the two PKL posts.
        $articles = $tag->articles()->published()->get();
        $this->assertEqualsCanonicalizing(['Alpha story', 'Bravo story'], $articles->pluck('title')->all());

        $this->get(route('tag', $tag))
            ->assertOk()
            ->assertSee('Alpha story')
            ->assertSee('Bravo story')
            ->assertSee('2 articles');
    }

    public function test_tag_names_are_case_and_whitespace_normalized(): void
    {
        Article::factory()->withTags(['PKL'])->create();
        Article::factory()->withTags(['pkl'])->create();
        Article::factory()->withTags(['  P K L  '])->create();

        // "PKL" and "pkl" collapse to one tag; " P K L " slugifies to "p-k-l" (distinct).
        $this->assertSame(1, Tag::where('slug', 'pkl')->count());
        $this->assertSame(2, Article::whereHas('tags', fn ($q) => $q->where('slug', 'pkl'))->count());
    }

    public function test_unpublished_only_tag_returns_404(): void
    {
        $draft = Article::factory()->draft()->withTags(['SecretTag'])->create();
        $tag   = Tag::where('slug', 'secrettag')->firstOrFail();

        $this->get(route('tag', $tag))->assertNotFound();
    }

    public function test_sitemap_lists_tags_with_published_articles(): void
    {
        Article::factory()->published()->withTags(['WorldCup'])->create();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(route('tag', 'worldcup'), false);
    }

    public function test_overlong_tag_is_truncated_not_fatal(): void
    {
        $admin = \App\Models\User::factory()->admin()->create();
        $long  = str_repeat('a', 300);

        $this->actingAs($admin)->post('/admin/articles', [
            'title'  => 'Long Tag Story',
            'status' => 'draft',
            'tags'   => $long,
        ])->assertRedirect(); // not a 500

        $article = Article::where('title', 'Long Tag Story')->firstOrFail();
        $this->assertSame(1, $article->tags->count());
        $this->assertLessThanOrEqual(50, mb_strlen($article->tags->first()->name));
    }

    public function test_tags_field_over_max_length_is_rejected(): void
    {
        $admin = \App\Models\User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/articles', [
            'title'  => 'Too Many Tags',
            'status' => 'draft',
            'tags'   => str_repeat('x,', 1500), // > 2000 chars
        ])->assertSessionHasErrors('tags');
    }

    public function test_tag_count_is_capped_per_article(): void
    {
        $admin = \App\Models\User::factory()->admin()->create();
        $tags  = collect(range(1, 40))->map(fn ($i) => "tag$i")->implode(', ');

        $this->actingAs($admin)->post('/admin/articles', [
            'title'  => 'Capped Tags',
            'status' => 'draft',
            'tags'   => $tags,
        ])->assertRedirect();

        $article = Article::where('title', 'Capped Tags')->firstOrFail();
        $this->assertSame(25, $article->tags->count());
    }

    public function test_editing_an_article_replaces_its_tags(): void
    {
        $admin   = \App\Models\User::factory()->admin()->create();
        $article = Article::factory()->withTags(['old-one', 'shared'])->create(['author_id' => $admin->id]);

        $this->actingAs($admin)->put(route('admin.articles.update', $article), [
            'title'  => $article->title,
            'status' => 'draft',
            'tags'   => 'shared, brand-new',
        ])->assertRedirect();

        $slugs = $article->fresh()->tags->pluck('slug')->sort()->values()->all();
        $this->assertSame(['brand-new', 'shared'], $slugs);
    }
}
