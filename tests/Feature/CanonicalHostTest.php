<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalHostTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_a_non_canonical_host_to_the_canonical_one(): void
    {
        config(['app.canonical_host' => 'canonical.test']);

        // Runs before routing, so the path need not resolve — and the query string
        // and path are preserved on the 301.
        $this->get('http://www.other.test/category/news?page=2')
            ->assertStatus(301)
            ->assertRedirect('http://canonical.test/category/news?page=2');
    }

    public function test_passes_through_when_already_on_the_canonical_host(): void
    {
        config(['app.canonical_host' => 'canonical.test']);

        $this->get('http://canonical.test/')->assertOk();
    }

    public function test_does_not_redirect_the_health_check(): void
    {
        config(['app.canonical_host' => 'canonical.test']);

        // The load balancer may hit /up via an internal host that never matches.
        $this->get('http://internal.test/up')->assertOk();
    }

    public function test_does_not_redirect_non_get_methods(): void
    {
        config(['app.canonical_host' => 'canonical.test']);

        // A 301 on a POST would drop the body — only GET/HEAD are redirected.
        $response = $this->post('http://www.other.test/article/1/like');
        $this->assertNotSame(301, $response->getStatusCode());
    }

    public function test_is_disabled_when_canonical_host_is_unset(): void
    {
        config(['app.canonical_host' => null]);

        $this->get('http://anything.test/')->assertOk();
    }

    /**
     * Deploy guard: in any environment that sets CANONICAL_HOST it MUST equal the
     * APP_URL host, or targeted ResponseCache::forget() (which rebuilds URLs from
     * APP_URL) will miss the cached pages. Skips where it's unset (local/CI).
     */
    public function test_canonical_host_when_configured_matches_app_url(): void
    {
        $canonical = config('app.canonical_host');

        if (! $canonical) {
            $this->markTestSkipped('CANONICAL_HOST not configured in this environment.');
        }

        $this->assertSame(
            $canonical,
            parse_url(config('app.url'), PHP_URL_HOST),
            'CANONICAL_HOST must equal the APP_URL host or targeted cache forgets will miss.'
        );
    }
}
