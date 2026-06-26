<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Spatie\ResponseCache\Facades\ResponseCache;

/**
 * Targeted public-cache invalidation.
 *
 * Replaces the blanket ResponseCache::clear() calls that used to fire on every
 * article/category write — which evicted the ENTIRE guest full-page cache and
 * stampeded popular pages on a busy publishing day — with forgets scoped to the
 * URLs a given change can actually affect.
 *
 * Limitation by design: paginated archives (?page=2..N) can't be enumerated, so
 * only page 1 of each archive is forgotten; deeper pages self-expire on the
 * short response-cache TTL (RESPONSE_CACHE_LIFETIME). That bounds staleness on
 * page 2+ to the TTL, which is invisible to readers and crawlers alike.
 */
class PublicCache
{
    /** When true, forgetArticle() is a no-op — see muted(). */
    private static bool $muted = false;

    /**
     * Run $fn with per-article invalidation suppressed, returning its result.
     * Used by batch paths (e.g. bulk article actions) that would otherwise fire
     * one targeted forget per row — the caller invalidates once afterwards. The
     * finally guarantees the mute can't leak past an exception mid-batch.
     */
    public static function muted(callable $fn): mixed
    {
        self::$muted = true;
        try {
            return $fn();
        } finally {
            self::$muted = false;
        }
    }

    /** SEO document caches that depend on the published-article set. */
    public static function flushSeo(): void
    {
        Cache::forget('seo.sitemap');
        Cache::forget('seo.news_sitemap');
        Cache::forget('seo.feed');
    }

    /**
     * Forget every front-end URL a single article appears on (page 1 of each),
     * plus the SEO documents. Covers the article itself (current + previous slug),
     * the home feed, its author archive, every category it belongs to (primary +
     * additional), and each tag archive.
     */
    public static function forgetArticle(Article $article): void
    {
        if (self::$muted) {
            return; // batch in progress — the caller invalidates once at the end
        }

        $urls = [route('home'), route('article', $article->slug)];

        // A slug change leaves the old URL cached — forget it too.
        $originalSlug = $article->getOriginal('slug');
        if ($originalSlug && $originalSlug !== $article->slug) {
            $urls[] = route('article', $originalSlug);
        }

        if ($article->author_id) {
            $urls[] = route('author', $article->author_id);
        }

        // Primary (category_id) + additional (pivot) categories.
        $categoryIds = collect([$article->category_id])
            ->merge($article->categories()->pluck('categories.id'))
            ->filter()
            ->unique();
        foreach (Category::whereIn('id', $categoryIds)->pluck('slug') as $slug) {
            $urls[] = route('category', $slug);
        }

        // Tag archives ({tag:slug} binding resolves the model to its slug).
        foreach ($article->tags as $tag) {
            $urls[] = route('tag', $tag);
        }

        ResponseCache::forget(array_values(array_unique($urls)));
        self::flushSeo();
    }
}
