<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return redirect()->route('admin.login')
                ->with('error', 'Please log in to access the admin panel.');
        }

        $user = Auth::user();

        /*
         * Deactivated while signed in: end the session here rather than waiting
         * for the next sign-in, so switching an account off takes effect on the
         * user's very next request.
         */
        if (! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'This account is inactive. Contact an administrator.']);
        }

        /*
         * Which role names open the panel lives on the model, and is compared
         * without case: role names are typed by hand on the Roles screen and
         * come out as "Admin" as often as "admin".
         */
        if (! $user->hasPanelAccess()) {
            abort(403, 'Access denied.');
        }

        return $next($request);
    }
}
