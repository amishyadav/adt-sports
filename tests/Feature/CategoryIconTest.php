<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryIconTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_category_stores_a_chosen_icon(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Pro Kabaddi', 'color' => '#D4420A', 'icon' => 'fa-trophy',
        ])->assertRedirect();

        $this->assertDatabaseHas('categories', ['name' => 'Pro Kabaddi', 'icon' => 'fa-trophy']);
    }

    public function test_a_custom_font_awesome_class_is_accepted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Volley', 'color' => '#0ea5e9', 'icon' => 'fa-volleyball',
        ])->assertRedirect();

        $this->assertDatabaseHas('categories', ['name' => 'Volley', 'icon' => 'fa-volleyball']);
    }

    public function test_an_injection_like_icon_value_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['fa-x"><script>', 'notfa-foo', 'fa-Bad Caps', '<i>x</i>'] as $bad) {
            $this->actingAs($admin)->post(route('admin.categories.store'), [
                'name' => 'Bad ' . md5($bad), 'color' => '#000', 'icon' => $bad,
            ])->assertSessionHasErrors('icon');
        }
    }

    public function test_display_icon_falls_back_chosen_then_slug_then_generic(): void
    {
        // Chosen icon wins.
        $chosen = Category::factory()->create(['slug' => 'whatever', 'icon' => 'fa-fire']);
        $this->assertSame('fa-fire', $chosen->display_icon);

        // No icon but a known slug → slug default.
        $known = Category::factory()->create(['slug' => 'match-updates', 'icon' => null]);
        $this->assertSame('fa-trophy', $known->display_icon);

        // No icon, unknown slug → generic.
        $unknown = Category::factory()->create(['slug' => 'random-thing', 'icon' => null]);
        $this->assertSame('fa-newspaper', $unknown->display_icon);
    }

    public function test_cover_placeholder_renders_the_category_icon(): void
    {
        $cat = Category::factory()->create(['icon' => 'fa-medal']);
        $article = Article::factory()->create(['category_id' => $cat->id, 'cover_image' => null]);

        $html = view('components.cover-placeholder', ['article' => $article->fresh()])->render();
        $this->assertStringContainsString('fa-medal', $html);
    }

    public function test_existing_categories_were_backfilled_by_migration(): void
    {
        // The seeder runs the real migrations, so the known slugs carry their icons.
        $match = Category::where('slug', 'match-updates')->first();
        if ($match) {
            $this->assertSame('fa-trophy', $match->icon);
        } else {
            $this->markTestSkipped('match-updates category not seeded in test DB');
        }
    }

    public function test_category_edit_form_exposes_the_icon_picker(): void
    {
        $admin = User::factory()->admin()->create();
        Category::factory()->create(['name' => 'Demo', 'icon' => 'fa-star']);

        $this->actingAs($admin)->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('id="catIcon"', false)
            ->assertSee('class="icon-grid"', false)
            ->assertSee('fa-trophy', false); // a grid choice is rendered
    }
}
