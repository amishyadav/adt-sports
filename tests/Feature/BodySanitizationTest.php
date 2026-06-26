<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BodySanitizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_script_and_handlers_are_stripped_when_storing_an_article(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/articles', [
            'title'  => 'XSS Attempt',
            'status' => 'draft',
            'body'   => '<p>Legit</p><script>alert(1)</script>'
                      . '<img src=x onerror=alert(2)>'
                      . '<a href="javascript:alert(3)">evil</a>',
        ])->assertRedirect();

        $body = Article::where('title', 'XSS Attempt')->value('body');

        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('onerror', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringContainsString('Legit', $body); // legit content preserved
    }

    public function test_mutator_sanitizes_direct_model_writes(): void
    {
        $article = Article::factory()->create([
            'body' => '<p>ok</p><script>alert(9)</script>',
        ]);

        $this->assertStringNotContainsString('<script', $article->fresh()->body);
    }

    public function test_safe_formatting_survives_sanitization(): void
    {
        $article = Article::factory()->create([
            'body' => '<h2>Heading</h2><p><strong>bold</strong></p><div class="callout">note</div>',
        ]);

        $body = $article->fresh()->body;
        $this->assertStringContainsString('<h2>', $body);
        $this->assertStringContainsString('<strong>', $body);
        $this->assertStringContainsString('callout', $body);
    }
}
