<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            /*
             * A deactivated account keeps its password but must not get in.
             *
             * Checked after the attempt rather than folded into the credentials
             * so the message can say what is actually wrong, and before
             * last_login_at is stamped so a refused attempt is not recorded as
             * a successful sign-in. regenerate() rather than invalidate() keeps
             * the flashed error available on the redirect while still issuing a
             * fresh session id.
             */
            if (! Auth::user()->isActive()) {
                Auth::logout();
                $request->session()->regenerate();

                return back()
                    ->withErrors(['email' => 'This account is inactive. Contact an administrator.'])
                    ->onlyInput('email');
            }

            $request->session()->regenerate();
            Auth::user()->update(['last_login_at' => now()]);
            return redirect()->intended(route('admin.dashboard'));
        }

        return back()
            ->withErrors(['email' => 'Invalid email or password.'])
            ->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}
