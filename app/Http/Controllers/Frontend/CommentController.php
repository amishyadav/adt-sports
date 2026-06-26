<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Comment;
use App\Support\CommenterIdentity;
use App\Support\PublicCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Mews\Purifier\Facades\Purifier;

class CommentController extends Controller
{
    /**
     * Accept a comment from an identified (subscribed) visitor. The author's
     * name + email come from the signed identity cookie — set when they
     * subscribed-to-comment — not from the form, so we never re-ask. Comments
     * are post-moderated: they go live immediately and an admin can hide or
     * remove them afterwards. A honeypot + rate limit guard against bots.
     */
    public function store(Request $request, Article $article)
    {
        abort_unless($article->isPublished(), 404);

        // Must have subscribed first. If not, bounce back to the gate.
        $identity = CommenterIdentity::get($request);
        if ($identity === null) {
            return $this->result($request, $article, 'subscribe');
        }

        // Honeypot: real users never fill this. Pretend success for bots.
        if ($request->filled('hp_url')) {
            return $this->result($request, $article, 'posted');
        }

        // Validate manually: the article page is cached for guests, so a
        // redirect-back would serve the cached HTML *without* the @error output.
        // Instead we send the visitor to a distinct, cache-safe ?comment=error URL.
        $validator = Validator::make($request->all(), [
            'body' => ['required', 'string', 'max:5000'],
        ]);

        if ($validator->fails()) {
            return $this->result($request, $article, 'error');
        }

        $body = $validator->validated()['body'];

        // Suppress exact duplicates (same article + email + body) — a cheap
        // brake on double-submits and replayed spam. Compare against the
        // sanitized form so it matches what the Comment mutator will store.
        $cleanBody = Purifier::clean($body, 'comment');
        $duplicate = $article->comments()
            ->where('author_email', $identity['email'])
            ->where('body', $cleanBody)
            ->exists();

        $comment = null;
        if (! $duplicate) {
            $comment = $article->comments()->create([
                'author_name'  => $identity['name'],
                'author_email' => $identity['email'],
                'body'         => $body,
                'approved'     => true, // post-moderation: live now, moderated later
                'ip'           => $request->ip(),
            ]);

            // It's on the public page now, so evict that page's guest cache.
            PublicCache::forgetArticle($article);
        }

        return $this->result($request, $article, 'posted', $comment);
    }

    /**
     * Respond to the submission. A fetch() request (Accept: application/json)
     * gets a JSON payload so the comment can be injected inline with no reload;
     * a no-JS form post falls back to the cache-safe ?comment= redirect (a
     * distinct URL per outcome, so the notice survives the guest full-page cache).
     */
    private function result(Request $request, Article $article, string $status, ?Comment $comment = null)
    {
        if ($request->expectsJson()) {
            $payload = ['ok' => $status === 'posted', 'status' => $status];

            if ($status === 'posted') {
                $payload['count'] = $article->comments()->approved()->count();
                // No html on a suppressed duplicate — it's already on the page.
                if ($comment) {
                    $payload['html'] = view('frontend.partials.comment', ['comment' => $comment])->render();
                }
            } else {
                $payload['message'] = match ($status) {
                    'error'     => 'Please check your comment (under 5000 characters), then try again.',
                    'subscribe' => 'Please subscribe below before commenting.',
                    default     => 'Something went wrong. Please try again.',
                };
            }

            return response()->json($payload, match ($status) {
                'error'     => 422,
                'subscribe' => 403,
                default     => 200,
            });
        }

        return redirect()->to(route('article', $article->slug) . '?comment=' . $status . '#comments');
    }
}
