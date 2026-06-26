<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\UniqueConstraintViolationException;
use Mews\Purifier\Facades\Purifier;
use App\Observers\ArticleObserver;

#[ObservedBy(ArticleObserver::class)]
class Article extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title','slug','excerpt','body','cover_image','cover_emoji',
        'cover_bg','category_id','author_id','status','featured',
        'breaking','views','likes','read_time','meta_title','meta_desc','published_at',
    ];

    protected $casts = [
        'featured'     => 'boolean',
        'breaking'     => 'boolean',
        'published_at' => 'datetime',
    ];

    /* ── Mutators ──────────────────────────────────────── */
    /**
     * Sanitize rich-text body on write. This is the single chokepoint for
     * stored-XSS defense: every write path (admin controller, seeder, jobs)
     * runs through here, and the view can safely render {!! $article->body !!}.
     */
    public function setBodyAttribute(?string $value): void
    {
        $this->attributes['body'] = $value === null || $value === ''
            ? $value
            : Purifier::clean($value, 'article');
    }

    /* ── Relationships ─────────────────────────────────── */
    public function category() { return $this->belongsTo(Category::class); } // primary (URL/canonical)
    public function author()   { return $this->belongsTo(User::class, 'author_id'); }

    /** Additional categories (beyond the primary) via the article_category pivot. */
    public function categories() { return $this->belongsToMany(Category::class); }

    /** Normalized tags via the article_tag pivot. */
    public function tags() { return $this->belongsToMany(Tag::class); }

    /** Reader comments (moderated — see Comment::scopeApproved). */
    public function comments() { return $this->hasMany(Comment::class); }

    /** Saved snapshots of prior content (newest first). */
    public function revisions() { return $this->hasMany(ArticleRevision::class)->latest('id'); }

    /**
     * Sync this article's tags from free-text input (comma-separated string or
     * an array of names). Names are canonicalized via Tag::fromName so casing
     * and whitespace variants collapse to a single tag.
     */
    public function syncTagsFromInput(string|array|null $input): void
    {
        // The editor always submits a (possibly empty) tags field, and Laravel's
        // ConvertEmptyStringsToNull middleware turns "" into null — treat that as
        // "no tags" rather than letting it blow up the save.
        $names = is_array($input) ? $input : explode(',', (string) $input);

        $ids = collect($names)
            ->map(fn ($name) => Tag::fromName((string) $name))
            ->filter()
            ->unique('id')
            ->take(25) // cap tags per article — keeps one bad paste from minting hundreds
            ->pluck('id')
            ->all();

        $this->tags()->sync($ids);
    }

    /* ── Scopes ────────────────────────────────────────── */
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')
                 ->whereNotNull('published_at')
                 ->where('published_at', '<=', now());
    }

    /**
     * Canonical "is this publicly visible?" check — the row-level mirror of
     * scopePublished(). Use this for access guards and view counting so a
     * future-scheduled post (status=published, published_at in the future)
     * is never reachable by slug or counted.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published'
            && $this->published_at !== null
            && $this->published_at->lessThanOrEqualTo(now());
    }

    /** Marked published but dated in the future — live automatically once that time passes. */
    public function isScheduled(): bool
    {
        return $this->status === 'published'
            && $this->published_at !== null
            && $this->published_at->isFuture();
    }

    public function scopeScheduled(Builder $q): Builder
    {
        return $q->where('status', 'published')->where('published_at', '>', now());
    }

    public function scopeInCategory(Builder $q, string $slug): Builder
    {
        // Match the category page: primary (category_id) OR an additional (pivot)
        // category, so the homepage ?category= filter and /category/{slug} agree.
        return $q->where(fn ($x) => $x
            ->whereHas('category', fn ($c) => $c->where('slug', $slug))
            ->orWhereHas('categories', fn ($c) => $c->where('slug', $slug)));
    }

    public function scopeSearch(Builder $q, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') {
            return $q;
        }

        // MySQL: use the FULLTEXT index (indexed, searches the body too).
        // Strip boolean operators (+ - * " ( ) ~ < > @) from user input first —
        // otherwise "Jaipur -Patna" would silently EXCLUDE Patna and a stray
        // quote would return nothing. Then prefix-match each remaining word.
        if (DB::connection()->getDriverName() === 'mysql') {
            $cleaned = preg_replace('/[^\p{L}\p{N}_]+/u', ' ', $term);
            $boolean = collect(preg_split('/\s+/', trim($cleaned)))
                ->filter()
                ->map(fn ($word) => $word . '*')
                ->implode(' ');

            if ($boolean === '') {
                return $q->whereRaw('1 = 0'); // nothing searchable after stripping
            }

            return $q->whereFullText(['title', 'excerpt', 'body'], $boolean, ['mode' => 'boolean']);
        }

        // SQLite / other: LIKE fallback (now including the body).
        return $q->where(fn ($x) => $x
            ->where('title',   'like', "%{$term}%")
            ->orWhere('excerpt', 'like', "%{$term}%")
            ->orWhere('body',  'like', "%{$term}%"));
    }

    /* ── Accessors ─────────────────────────────────────── */
    public function getFormattedDateAttribute(): string
    {
        return ($this->published_at ?? $this->created_at)?->format('d M Y') ?? '';
    }

    public function getTagsListAttribute(): string
    {
        return $this->tags->pluck('name')->implode(', ');
    }

    /* ── Helpers ───────────────────────────────────────── */
    public static function generateSlug(string $title): string
    {
        $base  = Str::slug($title);
        $slug  = $base;
        $count = 1;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $count++;
        }
        return $slug;
    }

    public static function calculateReadTime(?string $body): string
    {
        $words = str_word_count(strip_tags((string) $body));
        return max(1, (int) ceil($words / 200)) . ' min';
    }

    /**
     * Related posts ranked by shared-tag overlap, topped up with same-category
     * posts when there aren't enough tag matches. Falls back to category-only
     * when the article has no tags.
     */
    public function getRelated(int $limit = 3)
    {
        $tagIds = $this->tags()->pluck('tags.id');
        $base   = static::published()->where('id', '!=', $this->id);

        if ($tagIds->isNotEmpty()) {
            // Indexed join through article_tag: filter to posts sharing at least
            // one tag, rank by how many tags they share (DB-side, no PHP sort).
            $tagMatches = (clone $base)
                ->whereHas('tags', fn (Builder $q) => $q->whereIn('tags.id', $tagIds))
                ->withCount(['tags as shared_tags' => fn ($q) => $q->whereIn('tags.id', $tagIds)])
                ->orderByDesc('shared_tags')
                ->latest('published_at')
                ->limit($limit)
                ->get();

            if ($tagMatches->count() >= $limit) {
                return $tagMatches;
            }

            // Top up with same-category posts not already chosen.
            $fill = (clone $base)
                ->where('category_id', $this->category_id)
                ->whereNotIn('id', $tagMatches->pluck('id'))
                ->latest('published_at')
                ->limit($limit - $tagMatches->count())
                ->get();

            return $tagMatches->concat($fill)->values();
        }

        return $base->where('category_id', $this->category_id)
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    /** The previous (older) published article, for in-article navigation. */
    public function previousArticle(): ?self
    {
        if ($this->published_at === null) {
            return null; // drafts/unpublished posts have no place in the timeline
        }

        return static::published()
            ->where('published_at', '<', $this->published_at)
            ->orderByDesc('published_at')
            ->first();
    }

    /** The next (newer) published article. */
    public function nextArticle(): ?self
    {
        if ($this->published_at === null) {
            return null;
        }

        return static::published()
            ->where('published_at', '>', $this->published_at)
            ->orderBy('published_at')
            ->first();
    }

    /**
     * Buffer a view instead of writing the hot articles row on every request.
     * A scheduled command (app:flush-article-views) folds these into
     * articles.views without bumping updated_at. Uses update-or-insert so it
     * stays atomic and driver-agnostic (MySQL + SQLite).
     */
    public function incrementViews(): void
    {
        $updated = DB::table('article_view_buffer')
            ->where('article_id', $this->id)
            ->update(['count' => DB::raw('count + 1')]);

        if ($updated === 0) {
            try {
                DB::table('article_view_buffer')->insert([
                    'article_id' => $this->id,
                    'count'      => 1,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                // Lost the insert race — the row now exists, so increment it.
                DB::table('article_view_buffer')
                    ->where('article_id', $this->id)
                    ->update(['count' => DB::raw('count + 1')]);
            }
        }
    }

    /**
     * Toggle a like for a given browser fingerprint and return the new state.
     * The article_likes unique key dedupes per visitor; the denormalised
     * articles.likes counter is updated with a raw query so updated_at (and the
     * sitemap lastmod) stays stable — same discipline as view counting.
     *
     * @return array{liked: bool, likes: int}
     */
    public function toggleLike(string $fingerprint): array
    {
        // Drive the toggle off the atomic DELETE result rather than a separate
        // exists() read: DELETE returns the affected-row count, so two racing
        // unlikes can't both decrement (the second deletes 0 rows). The insert
        // path is likewise guarded by the unique constraint. This keeps the
        // denormalised likes counter in lock-step with the article_likes rows.
        $removed = DB::transaction(function () use ($fingerprint) {
            $rows = DB::table('article_likes')
                ->where('article_id', $this->id)
                ->where('fingerprint', $fingerprint)
                ->delete();

            if ($rows > 0) {
                DB::table('articles')->where('id', $this->id)->where('likes', '>', 0)
                    ->update(['likes' => DB::raw('likes - 1')]);
            }

            return $rows;
        });

        if ($removed > 0) {
            $liked = false;
        } else {
            try {
                DB::transaction(function () use ($fingerprint) {
                    DB::table('article_likes')->insert([
                        'article_id'  => $this->id,
                        'fingerprint' => $fingerprint,
                        'created_at'  => now(),
                    ]);
                    DB::table('articles')->where('id', $this->id)
                        ->update(['likes' => DB::raw('likes + 1')]);
                });
            } catch (UniqueConstraintViolationException $e) {
                // Raced a concurrent like — the row (and its increment) already exist.
            }
            $liked = true;
        }

        return [
            'liked' => $liked,
            'likes' => (int) DB::table('articles')->where('id', $this->id)->value('likes'),
        ];
    }
}
