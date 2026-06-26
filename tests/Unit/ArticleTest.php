<?php

namespace Tests\Unit;

use App\Models\Article;
use Tests\TestCase; // boots the app so Eloquent datetime casts resolve

class ArticleTest extends TestCase
{
    public function test_is_published_requires_status_and_past_publish_date(): void
    {
        $this->assertTrue($this->article('published', now()->subDay())->isPublished());
        $this->assertFalse($this->article('published', now()->addWeek())->isPublished(), 'future-dated');
        $this->assertFalse($this->article('published', null)->isPublished(), 'null publish date');
        $this->assertFalse($this->article('draft', now()->subDay())->isPublished(), 'draft');
    }

    public function test_calculate_read_time_rounds_up_by_200_wpm(): void
    {
        $this->assertSame('1 min', Article::calculateReadTime('one two three'));
        $this->assertSame('2 min', Article::calculateReadTime(str_repeat('word ', 400)));
        $this->assertSame('1 min', Article::calculateReadTime('')); // floor of 1
    }

    public function test_calculate_read_time_ignores_html_tags(): void
    {
        $this->assertSame('1 min', Article::calculateReadTime('<p><strong>hello</strong> world</p>'));
    }

    private function article(string $status, $publishedAt): Article
    {
        $a = new Article();
        $a->status = $status;
        $a->published_at = $publishedAt;

        return $a;
    }
}
