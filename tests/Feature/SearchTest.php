<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_title(): void
    {
        Article::factory()->published()->create(['title' => 'Unique Kabaddi Headline ZZZQ']);
        Article::factory()->published()->create(['title' => 'Unrelated Story']);

        $this->get('/search?q=ZZZQ')
            ->assertOk()
            ->assertSee('Unique Kabaddi Headline ZZZQ', false);
    }

    public function test_search_matches_body_text(): void
    {
        Article::factory()->published()->create([
            'title' => 'Plain Title Here',
            'body'  => '<p>the needle9000 marker is only in the body</p>',
        ]);

        $this->get('/search?q=needle9000')
            ->assertOk()
            ->assertSee('Plain Title Here', false);
    }

    public function test_search_excludes_drafts(): void
    {
        Article::factory()->draft()->create(['title' => 'Hidden Draft QQQX']);

        $this->get('/search?q=QQQX')
            ->assertOk()
            ->assertDontSee('Hidden Draft QQQX', false);
    }

    public function test_search_is_rate_limited(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->get('/search?q=kabaddi')->assertOk();
        }

        $this->get('/search?q=kabaddi')->assertStatus(429);
    }
}
