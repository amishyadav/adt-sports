<?php

namespace App\Observers;

use App\Models\Article;
use App\Models\Category;
use App\Support\PublicCache;

class ArticleObserver
{
    public function saved(Article $article): void
    {
        $this->refreshCategoryCounts($article);

        // Only touch the public cache when the change is publicly visible — a
        // draft autosave changes nothing a guest can see, so it must not evict
        // anything. Publishing, unpublishing, or editing a live post does.
        if ($this->publiclyRelevant($article)) {
            PublicCache::forgetArticle($article);
        }
    }

    public function deleted(Article $article): void
    {
        $this->refreshCategoryCounts($article);

        // Trashing a published post pulls it from public listings.
        if ($this->publiclyRelevant($article)) {
            PublicCache::forgetArticle($article);
        }
    }

    public function restored(Article $article): void
    {
        $this->refreshCategoryCounts($article);

        if ($this->publiclyRelevant($article)) {
            PublicCache::forgetArticle($article);
        }
    }

    public function forceDeleted(Article $article): void
    {
        // A force-deleted post was already trashed (absent from public listings),
        // so only the SEO documents could still reference it.
        PublicCache::flushSeo();
    }

    /**
     * Is this article (or was it, before this write) part of the public surface?
     * isPublished() covers the live state; getOriginal('status') catches an
     * unpublish (published -> draft) where the row is no longer live but its
     * cached pages still need evicting.
     */
    private function publiclyRelevant(Article $article): bool
    {
        return $article->isPublished()
            || $article->getOriginal('status') === 'published';
    }

    /**
     * Keep the denormalised published-article counts in sync for the primary
     * category, the previous primary (on a category change), and any additional
     * (pivot) categories — the pivot rows still exist for a soft-deleted article,
     * so delete/restore counts stay accurate. Pivot membership *changes* made via
     * the controller are refreshed by ArticleController::syncCategories(), since
     * the pivot isn't attached yet when the create save event fires.
     */
    private function refreshCategoryCounts(Article $article): void
    {
        collect([$article->category_id, $article->getOriginal('category_id')])
            ->merge($article->categories()->pluck('categories.id'))
            ->filter()
            ->unique()
            ->each(fn ($id) => Category::find($id)?->refreshCount());
    }
}
