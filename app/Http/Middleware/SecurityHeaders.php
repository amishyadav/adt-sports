<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds defense-in-depth security headers to every web response.
 *
 * Note on CSP: the templates rely heavily on inline event handlers
 * (onclick/onsubmit) and inline <script>/<style> blocks, so script/style
 * sources must permit 'unsafe-inline' for now. The stored-XSS sink (article
 * body) is already neutralised by HTMLPurifier (see App\Models\Article), so
 * this CSP is layered protection — it still locks down framing, object/base/
 * form targets, and external origins. Future hardening: move inline JS to
 * files and switch to a nonce-based script-src without 'unsafe-inline'.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers  = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), browsing-topics=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // HSTS is only meaningful over HTTPS; emitting it on plain HTTP is
        // ignored and misleading, so gate it on secure requests.
        if ($request->secure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request));

        return $response;
    }

    private function contentSecurityPolicy(Request $request): string
    {
        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            // We may embed video from these hosts only (see config/purifier.php).
            "frame-src https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com",
            "object-src 'none'",
            // Cover images may be remote https URLs; uploads are same-origin.
            "img-src 'self' data: https:",
            // 'unsafe-inline' required by inline handlers + inline <script> blocks.
            "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com",
            // Inline styles + Google Fonts + Font Awesome stylesheet.
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
            "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",
            // connect-src governs WebSockets as well as fetch/XHR. Reverb listens
            // on its own port, so its endpoint is a different origin and bare
            // 'self' silently blocks it — the live screens then sit frozen on a
            // throttled fallback poll. Only the configured host:port is allowed.
            'connect-src ' . implode(' ', array_merge(["'self'"], $this->websocketOrigins())),
        ];

        if ($request->secure()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }

    /**
     * The exact WebSocket origin the live match screens connect to, or nothing
     * at all when broadcasting is switched off.
     *
     * Deliberately narrow: one host and one port, taken from the broadcast
     * config rather than hard-coded or wildcarded, so this grants no more reach
     * than the app already has. Both schemes are listed because browsers match
     * ws:// and wss:// separately and the deployment decides which is in use.
     */
    private function websocketOrigins(): array
    {
        if (config('broadcasting.default') !== 'reverb') {
            return [];
        }

        $options = config('broadcasting.connections.reverb.options', []);
        $host = $options['host'] ?? null;
        $port = $options['port'] ?? null;

        if (! $host || ! $port) {
            return [];
        }

        return ["ws://{$host}:{$port}", "wss://{$host}:{$port}"];
    }
}
