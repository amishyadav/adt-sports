<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to full administrators.
 *
 * The 'admin' alias (AdminMiddleware) admits all staff (admin + editor) so
 * editors can manage their own articles and media. This middleware is the
 * stricter gate for global state — settings, users, and the category taxonomy —
 * which only administrators may mutate.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Auth::check() && Auth::user()->isAdmin(), 403);

        return $next($request);
    }
}
