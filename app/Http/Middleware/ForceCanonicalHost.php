<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirect every public request to the single canonical host (301) when
 * CANONICAL_HOST is configured.
 *
 * Why this exists: the full-page response cache keys on the request host, and
 * targeted ResponseCache::forget() rebuilds that host from APP_URL. If pages get
 * cached under more than one host (apex vs www, a bare IP, a staging alias), a
 * forget() generated from APP_URL silently misses the others and they go stale
 * until the TTL. Collapsing every request onto one host makes the forget host
 * always match the cached host — and removes duplicate-host indexing for SEO.
 *
 * Unset CANONICAL_HOST (local/dev/test) = disabled, so nothing changes there.
 *
 * Registered in the GLOBAL stack (bootstrap/app.php) AFTER TrustProxies — so
 * getHost() already reflects X-Forwarded-Host — and BEFORE the web group, so the
 * 301 never reaches (or gets stored by) the response cache.
 */
class ForceCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonical = config('app.canonical_host');

        // Only redirect safe, cacheable methods. Skip the health check, which the
        // load balancer may hit via an internal host/IP that will never match.
        if (
            $canonical
            && in_array($request->getMethod(), ['GET', 'HEAD'], true)
            && ! $request->is('up')
            && $request->getHost() !== $canonical
        ) {
            return redirect()->away(
                $request->getScheme() . '://' . $canonical . $request->getRequestUri(),
                301
            );
        }

        return $next($request);
    }
}
