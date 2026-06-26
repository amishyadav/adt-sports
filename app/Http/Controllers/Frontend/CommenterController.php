<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Mail\CommenterConfirmation;
use App\Models\Article;
use App\Models\Setting;
use App\Models\Subscriber;
use App\Support\CommenterIdentity;
use App\Support\SubscribeThrottle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class CommenterController extends Controller
{
    /**
     * Step 1 of double opt-in: capture name + email and email a single-use,
     * short-lived signed confirmation link. Nothing is unlocked yet — no cookie
     * is set and the address stays unverified until the link is confirmed.
     */
    public function subscribe(Request $request, Article $article)
    {
        abort_unless($article->isPublished(), 404);

        // Honeypot: bots fill this; password managers don't autofill the
        // non-semantic name. Pretend success, send nothing.
        if ($request->filled('hp_url')) {
            return $this->result($request, $article, 'check');
        }

        // Validate the email first so we can tell a returning (already verified)
        // subscriber — whose stored name we keep, so they needn't re-type it —
        // from a brand-new one, who must give a name (it shows on their comments).
        $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email     = Str::lower((string) $request->input('email'));
        $existing  = Subscriber::where('email', $email)->first();
        $returning = $existing && $existing->isVerified();

        // Trim first so a whitespace-only name can't slip past 'required' and
        // become a blank comment author.
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate([
            'name' => [$returning ? 'nullable' : 'required', 'string', 'max:80'],
        ]);

        // Per-inbox cap shared across every subscribe path (see SubscribeThrottle)
        // so we can't be used to mail-bomb an inbox. Fails "open" with the same
        // check-your-inbox response so nothing is leaked.
        if (SubscribeThrottle::tooMany($email)) {
            return $this->result($request, $article, 'check');
        }

        $token = Str::random(48);

        try {
            $subscriber = Subscriber::firstOrNew(['email' => $email]);
            if (! $subscriber->exists) {
                $subscriber->source     = 'comment';
                $subscriber->ip         = $request->ip();
                $subscriber->created_at = now();
            }
            // Don't let a stranger rename an already-verified subscriber.
            if (! $subscriber->exists || ! $subscriber->isVerified()) {
                $subscriber->name = $data['name'];
            }
            $subscriber->confirmation_token = $token; // rotate — older links die
            $subscriber->save();
        } catch (UniqueConstraintViolationException $e) {
            $subscriber = Subscriber::where('email', $email)->firstOrFail();
            $subscriber->update(['confirmation_token' => $token]);
        }

        $confirmUrl = URL::temporarySignedRoute('article.commenter.confirm', now()->addHour(), [
            'article'    => $article->id,
            'subscriber' => $subscriber->id,
            't'          => $token,
        ]);

        // Send first; only arm the rate limiters once it actually went out, so a
        // transient (sync) mail failure doesn't lock the visitor out with no
        // email. For async sends, CommenterConfirmation::failed() clears the
        // burst limiter so a worker-side failure self-heals too.
        try {
            Mail::to($email)->send(new CommenterConfirmation(
                $subscriber->name ?: 'Reader', // stored (authoritative) name
                $confirmUrl,
                Setting::get('site_name', 'ADT Sports'),
                $email,
            ));
        } catch (\Throwable $e) {
            report($e);
            return $this->result($request, $article, 'mailfail');
        }

        SubscribeThrottle::hit($email);

        return $this->result($request, $article, 'check');
    }

    /**
     * Step 2: the emailed link. GET renders a confirm page (no side effects — safe
     * for link scanners to pre-fetch); the page's button POSTs back here, which
     * verifies the address and issues the identity cookies. The token makes the
     * link single-use.
     */
    public function confirm(Request $request, string $article, Subscriber $subscriber)
    {
        $articleModel = Article::find($article);
        $token        = (string) $request->query('t', '');
        $valid        = $subscriber->confirmation_token !== null
                        && hash_equals($subscriber->confirmation_token, $token);

        if ($request->isMethod('get')) {
            return $valid
                ? view('commenter.confirm', [
                    'article'  => $articleModel,
                    'siteName' => Setting::get('site_name', 'ADT Sports'),
                    'action'   => $request->fullUrl(),
                ])
                : $this->redirectExpired($articleModel);
        }

        // POST — perform the confirmation.
        if (! $valid) {
            return $this->redirectExpired($articleModel);
        }

        $subscriber->update([
            'verified_at'        => $subscriber->verified_at ?? now(),
            'confirmation_token' => null, // single-use
        ]);

        $target = $articleModel
            ? route('article', $articleModel->slug) . '#comments'
            : route('home');

        $response = redirect()->to($target);
        foreach (CommenterIdentity::issue(trim((string) $subscriber->name) ?: 'Reader', $subscriber->email) as $cookie) {
            $response->withCookie($cookie);
        }

        return $response;
    }

    /** Forget the commenter identity (e.g. "not you?"). */
    public function signOut(Request $request, Article $article)
    {
        $response = redirect()->to(route('article', $article->slug) . '#comments');
        foreach (CommenterIdentity::forget() as $cookie) {
            $response->withCookie($cookie);
        }

        return $response;
    }

    /**
     * JSON for a fetch() submit (so the comment gate doesn't reload the page),
     * else the cache-safe ?comment= redirect for no-JS visitors.
     */
    private function result(Request $request, Article $article, string $status)
    {
        if ($request->expectsJson()) {
            $messages = [
                'check'    => "📧 Check your inbox (and spam) for the link to start commenting. We send at most " . SubscribeThrottle::MAX_PER_DAY . " emails a day to an address.",
                'mailfail' => "We couldn't send the email just now. Please try again in a moment.",
            ];

            return response()->json([
                'ok'      => $status === 'check',
                'message' => $messages[$status] ?? '',
            ], $status === 'mailfail' ? 503 : 200);
        }

        return redirect()->to(route('article', $article->slug) . '?comment=' . $status . '#comments');
    }

    private function redirectExpired(?Article $article)
    {
        return $article
            ? redirect()->to(route('article', $article->slug) . '?comment=expired#comments')
            : redirect()->route('home');
    }
}
