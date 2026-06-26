<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Article, ActivityLog, ArticleRevision, Category, Tag};
use App\Support\PublicCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Spatie\ResponseCache\Facades\ResponseCache;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $query = Article::with(['category', 'author'])->latest();

        // Editors manage only their own articles; admins see everything.
        if (! Auth::user()->isAdmin()) {
            $query->where('author_id', Auth::id());
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        $articles   = $query->paginate(20)->withQueryString();
        $categories = Category::orderBy('name')->get();

        return view('admin.articles.index', compact('articles', 'categories'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();
        $allTags    = Tag::orderBy('name')->pluck('name');
        $article    = null;
        return view('admin.articles.editor', compact('categories', 'article', 'allTags'));
    }

    public function store(Request $request)
    {
        $data = $this->validateArticle($request);
        $data['status'] = $this->resolveStatus($request);
        $categoryIds = $data['categories'] ?? [];
        unset($data['categories']);

        $data['author_id'] = Auth::id();
        $data['read_time'] = Article::calculateReadTime($data['body'] ?? '');
        $data['published_at'] = $this->resolvePublishDate($data, $request);

        $article = $this->createWithUniqueSlug($data); // category counts refreshed by ArticleObserver
        $this->syncCategories($article, $categoryIds);
        $article->syncTagsFromInput($request->input('tags', ''));

        ActivityLog::record('article.created', $article,
            ($data['status'] === 'published' ? 'Published' : 'Created draft') . ' "' . $article->title . '"');

        $msg = $data['status'] === 'published' ? 'Article published successfully!' : 'Draft saved.';
        return redirect()->route('admin.articles.edit', $article)->with('success', $msg);
    }

    public function edit(Article $article)
    {
        $this->authorizeArticle($article); // editors may only open their own drafts
        $categories = Category::orderBy('name')->get();
        $allTags    = Tag::orderBy('name')->pluck('name');
        $revisions  = $article->revisions()->with('user')->get();
        return view('admin.articles.editor', compact('article', 'categories', 'allTags', 'revisions'));
    }

    public function update(Request $request, Article $article)
    {
        $this->authorizeArticle($article);

        // Capture the current content so we can archive it as a revision if the
        // edit actually changes the title/excerpt/body.
        $original = $article->only(['title', 'excerpt', 'body']);

        $data = $this->validateArticle($request);
        $data['status'] = $this->resolveStatus($request);
        $categoryIds = $data['categories'] ?? [];
        unset($data['categories']);

        // Only recompute read-time when the body was actually submitted. The
        // inline publish/unpublish buttons post title+status without a body, and
        // calculating from an empty string would clobber a real article's read
        // time down to "1 min".
        if (array_key_exists('body', $data)) {
            $data['read_time'] = Article::calculateReadTime($data['body']);
        }
        $data['published_at'] = $this->resolvePublishDate($data, $request, $article);

        // Slug: keep the current one unless the editor explicitly changed it;
        // slugify + de-dupe (ignoring this article). Retry on the check-then-write
        // race, exactly like createWithUniqueSlug() does on store.
        $desiredSlug = ! empty($data['slug']) ? $data['slug'] : $article->slug;
        $base = Str::slug($desiredSlug) ?: 'article';
        $data['slug'] = $this->uniqueSlug($desiredSlug, $article->id);
        for ($attempt = 0; ; $attempt++) {
            try {
                $article->update($data); // category counts refreshed by ArticleObserver
                break;
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 5) {
                    throw $e;
                }
                $data['slug'] = $base . '-' . Str::lower(Str::random(6));
            }
        }
        $this->syncCategories($article, $categoryIds);
        $article->syncTagsFromInput($request->input('tags', ''));

        // Archive the prior content if the edit changed any of these fields.
        if ($article->wasChanged(['title', 'excerpt', 'body'])) {
            $this->snapshotRevision($article, $original);
        }

        ActivityLog::record('article.updated', $article, 'Updated "' . $article->title . '"');

        $msg = $data['status'] === 'published' ? 'Published!' : 'Changes saved.';
        return back()->with('success', $msg);
    }

    public function destroy(Article $article)
    {
        $this->authorizeArticle($article);
        $article->delete(); // soft delete -> Trash; counts refreshed by ArticleObserver
        ActivityLog::record('article.trashed', $article, 'Moved "' . $article->title . '" to Trash');
        return redirect()->route('admin.articles.index')->with('success', 'Moved to Trash.');
    }

    public function trash()
    {
        $query = Article::onlyTrashed()
            ->with(['category', 'author'])
            ->latest('deleted_at');

        // Editors see only their own trashed articles; admins see everything.
        if (! Auth::user()->isAdmin()) {
            $query->where('author_id', Auth::id());
        }

        $articles = $query->paginate(20);

        return view('admin.articles.trash', compact('articles'));
    }

    public function restore(string $id)
    {
        $article = Article::onlyTrashed()->findOrFail($id);
        $this->authorizeArticle($article);
        $article->restore();
        ActivityLog::record('article.restored', $article, 'Restored "' . $article->title . '" from Trash');

        return back()->with('success', 'Article restored.');
    }

    public function forceDestroy(string $id)
    {
        $article = Article::onlyTrashed()->findOrFail($id);
        $this->authorizeArticle($article);
        $title = $article->title; // capture before the row is gone
        $article->forceDelete();
        ActivityLog::record('article.deleted', null, 'Permanently deleted "' . $title . '"');

        return back()->with('success', 'Article permanently deleted.');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:publish,unpublish,trash,delete',
            'ids'    => 'required|array|max:200',
            'ids.*'  => 'integer',
        ]);

        // Permanent delete operates on trashed rows; the rest on live ones.
        $query = $data['action'] === 'delete' ? Article::withTrashed() : Article::query();
        $articles = $query->whereIn('id', $data['ids'])->get();

        // Suppress the observer's per-row targeted forget during the loop, then
        // invalidate once below — a bulk action over up to 200 rows otherwise
        // fires hundreds of individual cache ops.
        $count = PublicCache::muted(function () use ($articles, $data) {
            $count = 0;
            foreach ($articles as $article) {
                // Editors may only act on their own articles; silently skip the rest.
                if (! Auth::user()->isAdmin() && $article->author_id !== Auth::id()) {
                    continue;
                }

                match ($data['action']) {
                    'publish'   => $article->update([
                        'status'       => 'published',
                        'published_at' => ($article->published_at && $article->published_at->isPast())
                            ? $article->published_at : now(),
                    ]),
                    'unpublish' => $article->update(['status' => 'draft']),
                    'trash'     => $article->delete(),
                    'delete'    => $article->forceDelete(),
                };
                $count++;
            }

            return $count;
        });

        // One invalidation for the whole batch. 'delete' force-removes already-
        // trashed (non-public) rows, so only the SEO docs can still reference them.
        if ($count > 0) {
            if ($data['action'] === 'delete') {
                PublicCache::flushSeo();
            } else {
                ResponseCache::clear();
                PublicCache::flushSeo();
            }
        }

        ActivityLog::record('article.bulk', null, "Bulk {$data['action']} applied to {$count} article(s)");

        return back()->with('success', "Applied “{$data['action']}” to {$count} article(s).");
    }

    /** Save a snapshot of the prior content, keeping the 20 most recent. */
    private function snapshotRevision(Article $article, array $original): void
    {
        $article->revisions()->create([
            'user_id'    => Auth::id(),
            'title'      => $original['title'],
            'excerpt'    => $original['excerpt'],
            'body'       => $original['body'],
            'created_at' => now(),
        ]);

        $ids = $article->revisions()->pluck('id'); // newest-first (relation orders by id desc)
        if ($ids->count() > 20) {
            ArticleRevision::whereIn('id', $ids->slice(20))->delete();
        }
    }

    /**
     * Return a single revision's content as JSON so the editor can load it in
     * for review (non-destructive — the editor must still Save to apply it).
     */
    public function revision(Article $article, ArticleRevision $revision)
    {
        $this->authorizeArticle($article);
        abort_if($revision->article_id !== $article->id, 404);

        return response()->json([
            'title'   => $revision->title,
            'excerpt' => $revision->excerpt,
            'body'    => $revision->body,
        ]);
    }

    /** Only an admin or the article's own author may mutate it. */
    private function authorizeArticle(Article $article): void
    {
        if (! Auth::user()->isAdmin() && $article->author_id !== Auth::id()) {
            abort(403, 'Permission denied.');
        }
    }

    private function validateArticle(Request $request): array
    {
        $data = $request->validate([
            'title'       => 'required|string|max:500',
            'slug'        => 'nullable|string|max:255',
            'excerpt'     => 'nullable|string|max:1000',
            'body'        => 'nullable|string',
            'cover_image' => 'nullable|string|max:500',
            'cover_emoji' => 'nullable|string|max:20',
            'cover_bg'    => 'nullable|string|max:300',
            'category_id' => 'nullable|exists:categories,id',
            'featured'     => 'nullable',
            'breaking'     => 'nullable',
            'meta_title'   => 'nullable|string|max:255',
            'meta_desc'    => 'nullable|string|max:500',
            'tags'         => 'nullable|string|max:2000',
            'published_at' => 'nullable|date',
            'categories'   => 'nullable|array',
            'categories.*' => 'integer|exists:categories,id',
        ]);

        // These columns are NOT NULL with DB defaults, but the editor submits
        // them as (often empty) hidden fields — ConvertEmptyStringsToNull turns
        // "" into null. Restore the defaults so the insert/update can't fail.
        $data['cover_bg']    = ($data['cover_bg'] ?? null) ?: 'linear-gradient(145deg,#1A1410,#221808)';
        $data['cover_emoji'] = ($data['cover_emoji'] ?? null) ?: '📰';

        return $data;
    }

    /** Store the article's additional categories (the primary stays on category_id). */
    private function syncCategories(Article $article, array $categoryIds): void
    {
        $additional = collect($categoryIds)
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === (int) $article->category_id) // don't duplicate the primary
            ->unique()
            ->values()
            ->all();

        // Refresh counts for every category whose pivot membership changed
        // (the union of what was attached before and after), so additional-
        // category badges stay accurate when membership is edited.
        $before = $article->categories()->pluck('categories.id')->all();
        $article->categories()->sync($additional);

        Category::whereIn('id', array_values(array_unique(array_merge($before, $additional))))
            ->get()
            ->each->refreshCount();
    }

    /**
     * Status is driven by which button was pressed (Save Draft / Publish),
     * not a separate dropdown. Falls back to a posted 'status' field, then draft.
     */
    private function resolveStatus(Request $request): string
    {
        $status = $request->input('status_override', $request->input('status', 'draft'));

        return in_array($status, ['draft', 'published'], true) ? $status : 'draft';
    }

    /**
     * Decide the publish timestamp:
     *  - draft            -> null (a draft has no publish date)
     *  - published + date -> that date (future date = scheduled; it goes live
     *                         automatically once now() passes it)
     *  - published, none  -> now()
     */
    private function resolvePublishDate(array $data, Request $request, ?Article $existing = null): ?Carbon
    {
        if (($data['status'] ?? null) !== 'published') {
            return null;
        }

        if ($request->filled('published_at')) {
            return Carbon::parse($request->input('published_at'));
        }

        // Keep an already-published post's original date; otherwise publish now.
        return $existing?->published_at ?? now();
    }

    /**
     * Create an article with a unique slug, tolerant of the check-then-insert
     * race: if a concurrent publish grabbed the same slug, the DB unique
     * constraint fires and we retry with a random suffix instead of 500ing.
     */
    private function createWithUniqueSlug(array $data): Article
    {
        // Use the editor-supplied slug if given, otherwise derive from the title.
        $desired = ! empty($data['slug']) ? $data['slug'] : $data['title'];
        $base = Str::slug($desired) ?: 'article';
        $data['slug'] = $this->uniqueSlug($desired);

        for ($attempt = 0; ; $attempt++) {
            try {
                return Article::create($data);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 5) {
                    throw $e;
                }
                $data['slug'] = $base . '-' . Str::lower(Str::random(6));
            }
        }
    }

    /**
     * Slugify a desired value and make it unique. Checks soft-deleted rows too
     * (the slug column is unique regardless of trashed state).
     */
    private function uniqueSlug(string $desired, ?int $ignoreId = null): string
    {
        $base = Str::slug($desired) ?: 'article';
        $slug = $base;
        $i = 1;

        while (
            Article::withTrashed()->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
