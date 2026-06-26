<?php

namespace App\Support;

use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Spatie\ResponseCache\CacheProfiles\CacheProfile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Full-page caching for anonymous public reads only.
 *
 * SECURITY: authenticated requests are NEVER cached, so admin pages, drafts,
 * and author previews are always rendered live and a cached page can never be
 * served to the wrong user.
 */
class PublicResponseCacheProfile implements CacheProfile
{
    public function enabled(Request $request): bool
    {
        return (bool) config('responsecache.enabled', true);
    }

    public function shouldCacheRequest(Request $request): bool
    {
        // Hard stop: never cache for a logged-in user.
        if (Auth::check()) {
            return false;
        }

        if (! $request->isMethod('get')) {
            return false;
        }

        // Exclude the admin panel, the view-count beacon, the per-visitor
        // subscribe/confirm endpoints, and search results.
        if ($request->is('admin', 'admin/*', 'article/*/hit', 'article/*/commenter/*', 'subscribe/*', 'search')) {
            return false;
        }

        // The ?comment=… landing pages reflect a just-now action — and, under
        // post-moderation, the freshly-posted comment itself. Caching them (a
        // separate cache key per query string) would let the SECOND commenter
        // land on the FIRST commenter's cached copy, missing their own comment.
        // Always render these live; the bare article URL stays cached + busted.
        if ($request->has('comment')) {
            return false;
        }

        return true;
    }

    public function shouldCacheResponse(Response $response): bool
    {
        // Only plain successful HTML/XML responses (not 204 beacons, redirects, errors).
        return $response->getStatusCode() === 200;
    }

    public function cacheRequestUntil(Request $request): DateTime
    {
        return Carbon::now()->addSeconds(
            (int) config('responsecache.cache_lifetime_in_seconds', 3600)
        );
    }

    /** Guests only — no per-user cache variation. */
    public function useCacheNameSuffix(Request $request): string
    {
        return 'guest';
    }
}
