<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mews\Purifier\Facades\Purifier;
use Tests\TestCase;

class EmbedPurifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_youtube_embed_survives_purification(): void
    {
        $html = '<div class="embed-responsive"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0"></iframe></div>';
        $clean = Purifier::clean($html, 'article');

        $this->assertStringContainsString('<iframe', $clean);
        $this->assertStringContainsString('youtube.com/embed/dQw4w9WgXcQ', $clean);
    }

    public function test_vimeo_embed_survives_purification(): void
    {
        $clean = Purifier::clean('<iframe src="https://player.vimeo.com/video/123456"></iframe>', 'article');
        $this->assertStringContainsString('player.vimeo.com/video/123456', $clean);
    }

    public function test_iframe_from_an_untrusted_host_is_stripped(): void
    {
        $clean = Purifier::clean('<iframe src="https://evil.example.com/phish"></iframe>', 'article');

        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringNotContainsString('evil.example.com', $clean);
    }

    public function test_script_tag_is_still_stripped_from_body(): void
    {
        $clean = Purifier::clean('<script>alert(1)</script><p>hello</p>', 'article');

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringContainsString('hello', $clean);
    }

    public function test_article_body_setter_keeps_a_valid_embed(): void
    {
        $author = User::factory()->admin()->create();
        $article = Article::factory()->create([
            'author_id' => $author->id,
            'body' => '<p>Watch:</p><div class="embed-responsive"><iframe src="https://www.youtube.com/embed/abc12345"></iframe></div>',
        ]);

        $this->assertStringContainsString('youtube.com/embed/abc12345', $article->fresh()->body);
    }

    public function test_saving_article_with_styled_body_and_embed_does_not_error(): void
    {
        // Guards the full controller -> body mutator -> Purifier path against an
        // unsupported-CSS-property warning becoming a 500 (e.g. border-radius).
        $admin = User::factory()->admin()->create();
        $article = Article::factory()->create(['author_id' => $admin->id]);

        $this->actingAs($admin)->put(route('admin.articles.update', $article), [
            'title' => $article->title,
            'body'  => '<p style="border-radius:8px;color:#c00">Styled</p>'
                     . '<div class="embed-responsive"><iframe src="https://www.youtube.com/embed/abc12345"></iframe></div>',
            'status_override' => 'draft',
        ])->assertRedirect(); // not a 500

        $this->assertStringContainsString('youtube.com/embed/abc12345', $article->fresh()->body);
    }

    public function test_response_csp_allows_video_frame_sources(): void
    {
        $resp = $this->get('/');
        $csp = $resp->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('frame-src', $csp);
        $this->assertStringContainsString('player.vimeo.com', $csp);
        $this->assertStringContainsString('youtube.com', $csp);
    }
}
