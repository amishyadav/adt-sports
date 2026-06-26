<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Self-contained pagination view styled by our own CSS (the Bootstrap/
        // Tailwind defaults rely on framework utility classes we don't load,
        // which makes them stack vertically and show duplicate mobile/desktop bars).
        Paginator::defaultView('vendor.pagination.adt');
        Paginator::defaultSimpleView('vendor.pagination.adt');

        // Throttle admin login: 5 attempts/min per email+IP to block brute force
        // without letting one IP lock out every account.
        RateLimiter::for('login', function (Request $request) {
            $key = Str::lower((string) $request->input('email')) . '|' . $request->ip();
            return Limit::perMinute(5)->by($key);
        });

        // Branded password-reset email (reuses the broker's token plumbing).
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);

            return (new MailMessage)
                ->subject('Reset your ADT Sports password')
                ->greeting('Password reset')
                ->line('We received a request to reset the password for your ADT Sports admin account.')
                ->action('Reset password', $url)
                ->line('This link expires in 60 minutes. If you didn\'t request this, you can safely ignore this email — nothing will change.');
        });

        if ($this->app->environment('production')) {
            // Signed confirmation links bake in the scheme; force HTTPS so they
            // don't 403 when generated behind a TLS-terminating proxy.
            URL::forceScheme('https');

            // Guard against shipping with the Resend test sender, which only
            // delivers to the account owner — confirmation emails would silently
            // never reach real users.
            if (Str::contains((string) config('mail.from.address'), 'resend.dev')) {
                Log::warning('MAIL_FROM_ADDRESS is still onboarding@resend.dev in production — confirmation emails will only reach the Resend account owner. Set it to a verified domain.');
            }
        }
    }
}
