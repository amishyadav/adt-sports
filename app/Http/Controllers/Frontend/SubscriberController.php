<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Mail\CommenterConfirmation;
use App\Models\Setting;
use App\Models\Subscriber;
use App\Support\CommenterIdentity;
use App\Support\SubscribeThrottle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class SubscriberController extends Controller
{
    /**
     * Newsletter sign-up — double opt-in, same model as the comment gate.
     * Captures name + email, stores the address UNVERIFIED with a single-use
     * confirmation token, and emails a signed link. The address only joins the
     * list once that link is confirmed.
     */
    public function store(Request $request)
    {
        // Honeypot.
        if ($request->filled('hp_url')) {
            return $this->checkInbox($request);
        }

        // Trim first so a whitespace-only name can't satisfy 'required'.
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate([
            'name'   => ['required', 'string', 'max:80'],
            'email'  => ['required', 'email', 'max:255'],
            // Allowlist the source — it lands in a CSV export, so reject anything
            // that could be a spreadsheet formula payload.
            'source' => ['nullable', 'in:home,article,site,footer,modal'],
        ]);
        $email = Str::lower($data['email']);

        // Already confirmed? Nothing to do — don't re-email.
        $existing = Subscriber::where('email', $email)->first();
        if ($existing && $existing->isVerified()) {
            return $this->checkInbox($request, "✅ You're already subscribed!");
        }

        // Per-inbox cap shared with the comment gate (SubscribeThrottle).
        if (SubscribeThrottle::tooMany($email)) {
            return $this->checkInbox($request);
        }

        $token = Str::random(48);

        try {
            $subscriber = Subscriber::firstOrNew(['email' => $email]);
            if (! $subscriber->exists) {
                $subscriber->source     = $data['source'] ?? 'site';
                $subscriber->ip         = $request->ip();
                $subscriber->created_at = now();
            }
            // Never overwrite a verified subscriber's name (handled above anyway).
            if (! $subscriber->exists || ! $subscriber->isVerified()) {
                $subscriber->name = $data['name'];
            }
            $subscriber->confirmation_token = $token; // rotate — older links die
            $subscriber->save();
        } catch (UniqueConstraintViolationException $e) {
            $subscriber = Subscriber::where('email', $email)->firstOrFail();
            $subscriber->update(['confirmation_token' => $token]);
        }

        $confirmUrl = URL::temporarySignedRoute('subscribe.confirm', now()->addHour(), [
            'subscriber' => $subscriber->id,
            't'          => $token,
        ]);

        try {
            Mail::to($email)->send(new CommenterConfirmation(
                $subscriber->name ?: $data['name'],
                $confirmUrl,
                Setting::get('site_name', 'ADT Sports'),
                $email,
            ));
        } catch (\Throwable $e) {
            report($e);
            return $this->mailFailed($request);
        }

        SubscribeThrottle::hit($email);

        return $this->checkInbox($request);
    }

    /**
     * The emailed confirmation link. GET renders a confirm page (no side effects,
     * scanner-safe); the button POSTs back here to verify the address, mark it
     * confirmed, and issue the shared identity cookie (so a confirmed subscriber
     * can also comment). Single-use via the token.
     */
    public function confirm(Request $request, Subscriber $subscriber)
    {
        $token = (string) $request->query('t', '');
        $valid = $subscriber->confirmation_token !== null
                 && hash_equals($subscriber->confirmation_token, $token);

        if ($request->isMethod('get')) {
            return $valid
                ? view('subscribe.confirm', [
                    'siteName'  => Setting::get('site_name', 'ADT Sports'),
                    'action'    => $request->fullUrl(),
                    'confirmed' => false,
                ])
                : redirect()->route('home');
        }

        // POST — perform the confirmation.
        if (! $valid) {
            return redirect()->route('home');
        }

        $subscriber->update([
            'verified_at'        => $subscriber->verified_at ?? now(),
            'confirmation_token' => null,
        ]);

        $response = response()->view('subscribe.confirm', [
            'siteName'  => Setting::get('site_name', 'ADT Sports'),
            'confirmed' => true,
        ]);
        foreach (CommenterIdentity::issue(trim((string) $subscriber->name) ?: 'Reader', $subscriber->email) as $cookie) {
            $response->withCookie($cookie);
        }

        return $response;
    }

    private function checkInbox(Request $request, ?string $message = null)
    {
        $message ??= "📧 Almost there — check your inbox (and spam) to confirm. We send at most " . SubscribeThrottle::MAX_PER_DAY . " emails a day to an address.";

        return $request->expectsJson() || $request->ajax()
            ? response()->json(['ok' => true, 'message' => $message])
            : back()->with('success', $message);
    }

    private function mailFailed(Request $request)
    {
        $message = "We couldn't send the confirmation email just now. Please try again in a moment.";

        return $request->expectsJson() || $request->ajax()
            ? response()->json(['ok' => false, 'message' => $message], 503)
            : back()->with('error', $message);
    }
}
