<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\{Article, Category, Setting, Tag, User};
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FrontendController extends Controller
{
    private function shared(): array
    {
        return [
            'settings'   => Setting::allAsArray(),
            'categories' => Category::ordered(),
        ];
    }

    public function home(Request $request)
    {
        $perPage   = (int) Setting::get('articles_per_page', 10);
        $catSlug   = $request->get('category');

        // The hero block (lead + 3 stacked) is showcased separately at the top, so
        // exclude those articles from the feed to avoid showing the same stories
        // twice. Computed before the feed query so the exclusion stays consistent
        // across "Load more" pages (which re-enter this method with ?partial=1).
        $heroLead  = Article::with(['category','author'])->published()->where('featured', true)->latest('published_at')->first()
                     ?? Article::with(['category','author'])->published()->latest('published_at')->first();
        $heroStack = Article::with('category')->published()->latest('published_at')
                     ->where('id', '!=', $heroLead?->id ?? 0)->limit(3)->get();
        $heroIds   = $heroStack->pluck('id')->push($heroLead?->id)->filter()->all();

        // Exclude the hero set only on the unfiltered homepage, where the hero and
        // feed draw from the same global newest list. Under ?category= the hero stays
        // a global showcase while the feed is category-specific, so excluding there
        // could hide a category's own article from its filtered list.
        $query = Article::with(['category','author'])->published()
            ->when(! $catSlug, fn ($q) => $q->whereNotIn('id', $heroIds))
            ->latest('published_at');
        if ($catSlug) $query->inCategory($catSlug);

        $articles  = $query->paginate($perPage)->withQueryString();

        // "Load more" returns just the next page's rows (skips trending/featured + layout).
        if ($request->boolean('partial')) {
            return view('frontend.partials.feed_fragment', compact('articles'));
        }

        $trending  = Article::with('category')->published()->orderByDesc('views')->limit(5)->get();
        $featured  = Article::with(['category','author'])->published()->where('featured',true)->latest('published_at')->limit(3)->get();

        return view('frontend.home', array_merge($this->shared(), compact(
            'articles','heroLead','heroStack','trending','catSlug','featured'
        )));
    }

    public function article(string $slug)
    {
        $article = Article::with(['category','author','tags'])
            ->where('slug', $slug)->firstOrFail();

        // Only publicly-visible posts (published + past publish date) are reachable
        // by slug; the author or an admin may preview anything else. Everyone else
        // gets a 404 — including future-scheduled posts.
        $viewer     = auth()->user();
        $canPreview = $viewer && ($viewer->isAdmin() || $viewer->id === $article->author_id);
        abort_if(! $article->isPublished() && ! $canPreview, 404);

        // Views are counted via an async beacon (see hit()) so this page can be
        // fully cached. Bonus: only real browsers (JS) count, not crawlers.

        $related  = $article->getRelated(3);
        $prev     = $article->previousArticle();
        $next     = $article->nextArticle();
        $trending = Article::with('category')->published()
            ->orderByDesc('views')->where('id','!=',$article->id)->limit(5)->get();
        $comments = $article->comments()->approved()->latest()->get();

        return view('frontend.article', array_merge($this->shared(), compact(
            'article','related','prev','next','trending','comments'
        )));
    }

    /**
     * Async view-count beacon. Called by JS from the (cacheable) article page so
     * view counting is decoupled from page rendering. Returns 204 (never cached).
     */
    public function hit(Article $article)
    {
        if ($article->isPublished()) {
            $article->incrementViews();
        }

        return response()->noContent();
    }

    /**
     * Toggle a like for the article. Like the view beacon, this is decoupled
     * from the (cached) page: it's a POST, never cached, identified by a
     * long-lived per-browser cookie so a visitor likes an article at most once.
     */
    public function like(Request $request, Article $article)
    {
        abort_unless($article->isPublished(), 404);

        $fingerprint = $this->visitorFingerprint($request);
        $result      = $article->toggleLike($fingerprint);

        return response()->json($result)->cookie(
            'adt_uid', $fingerprint, 60 * 24 * 365, null, null, $request->secure(), true
        );
    }

    /** Stable per-browser id from a signed-ish cookie; minted on first use. */
    private function visitorFingerprint(Request $request): string
    {
        $uid = (string) $request->cookie('adt_uid', '');

        if (! preg_match('/^[a-f0-9\-]{16,64}$/i', $uid)) {
            $uid = (string) Str::uuid();
        }

        return $uid;
    }

    public function category(Request $request, string $slug)
    {
        $category = Category::where('slug',$slug)->firstOrFail();
        $perPage  = (int) Setting::get('articles_per_page', 10);
        $articles = Article::with(['category','author'])
            ->published()
            ->where(function ($q) use ($category) {
                $q->where('category_id', $category->id) // primary
                  ->orWhereHas('categories', fn ($c) => $c->where('categories.id', $category->id)); // additional
            })
            ->latest('published_at')->paginate($perPage)->withQueryString();

        if ($request->boolean('partial')) {
            return view('frontend.partials.feed_fragment', ['articles' => $articles, 'rowCat' => $category]);
        }

        $trending = Article::with('category')->published()->orderByDesc('views')->limit(5)->get();

        return view('frontend.category', array_merge($this->shared(), compact(
            'category','articles','trending'
        )));
    }

    public function author(Request $request, User $user)
    {
        $perPage  = (int) Setting::get('articles_per_page', 10);
        $articles = Article::with(['category','author'])
            ->published()->where('author_id', $user->id)
            ->latest('published_at')->paginate($perPage)->withQueryString();

        // Avoid thin/empty author pages being indexed.
        abort_if($articles->total() === 0, 404);

        if ($request->boolean('partial')) {
            return view('frontend.partials.feed_fragment', ['articles' => $articles, 'hideAuthor' => true]);
        }

        $trending = Article::with('category')->published()->orderByDesc('views')->limit(5)->get();

        return view('frontend.author', array_merge($this->shared(), compact(
            'user','articles','trending'
        )));
    }

    public function tag(Request $request, Tag $tag)
    {
        $perPage  = (int) Setting::get('articles_per_page', 10);
        $articles = Article::with(['category','author'])
            ->published()
            ->whereHas('tags', fn ($q) => $q->whereKey($tag->id))
            ->latest('published_at')->paginate($perPage)->withQueryString();

        // Avoid thin/empty tag pages being indexed.
        abort_if($articles->total() === 0, 404);

        if ($request->boolean('partial')) {
            return view('frontend.partials.feed_fragment', compact('articles'));
        }

        $trending = Article::with('category')->published()->orderByDesc('views')->limit(5)->get();

        return view('frontend.tag', array_merge($this->shared(), compact(
            'tag','articles','trending'
        )));
    }

    public function search(Request $request)
    {
        $q = trim($request->get('q',''));
        $articles = $q
            ? Article::with(['category','author'])->published()->search($q)->latest('published_at')->paginate(15)->withQueryString()
            : collect();

        if ($q && $request->boolean('partial')) {
            return view('frontend.partials.feed_fragment', compact('articles'));
        }

        $trending = Article::with('category')->published()->orderByDesc('views')->limit(5)->get();

        return view('frontend.search', array_merge($this->shared(), compact('articles','q','trending')));
    }
}
