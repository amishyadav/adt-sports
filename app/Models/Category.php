<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use Spatie\ResponseCache\Facades\ResponseCache;

class Category extends Model
{
    use HasFactory;
    protected $fillable = ['name','slug','color','icon','description','article_count'];

    public const CACHE_KEY = 'categories.all';

    /** Default icon per known slug — the fallback when a category has no chosen icon. */
    private const DEFAULT_ICONS = [
        'match-updates'  => 'fa-trophy',
        'player-stories' => 'fa-user',
        'league-news'    => 'fa-bullhorn',
        'analysis'       => 'fa-chart-line',
        'grassroots'     => 'fa-seedling',
        'international'   => 'fa-earth-asia',
        'originals'      => 'fa-star',
        'tsr-analytics'  => 'fa-chart-simple',
    ];

    /** Curated Font Awesome classes offered in the admin icon picker. */
    public static function iconChoices(): array
    {
        return [
            'fa-trophy', 'fa-medal', 'fa-ranking-star', 'fa-crown', 'fa-star',
            'fa-person-running', 'fa-hand-fist', 'fa-shield-halved', 'fa-flag', 'fa-fire',
            'fa-bolt', 'fa-stopwatch', 'fa-chart-line', 'fa-chart-simple', 'fa-chart-pie',
            'fa-bullhorn', 'fa-newspaper', 'fa-microphone', 'fa-video', 'fa-camera',
            'fa-user', 'fa-users', 'fa-seedling', 'fa-earth-asia', 'fa-globe',
            'fa-location-dot', 'fa-calendar', 'fa-clipboard-list', 'fa-graduation-cap', 'fa-handshake',
        ];
    }

    /** The icon to actually render: admin choice → slug default → generic. */
    public function getDisplayIconAttribute(): string
    {
        return $this->icon ?: (self::DEFAULT_ICONS[$this->slug] ?? 'fa-newspaper');
    }

    protected static function booted(): void
    {
        // The category list renders on every public page; bust both the list cache
        // and the full-page response cache on any change.
        static::saved(function () {
            Cache::forget(self::CACHE_KEY);
            ResponseCache::clear();
        });
        static::deleted(function () {
            Cache::forget(self::CACHE_KEY);
            ResponseCache::clear();
        });
    }

    public function articles() { return $this->hasMany(Article::class); }

    /**
     * Cached, alphabetically-ordered list for nav/sidebars.
     *
     * WARNING: the cached models carry article_count as it was when the cache was
     * built. refreshCount() deliberately does NOT bust this cache (see there), so
     * article_count on these instances can be stale. That's fine today — it's only
     * rendered fresh on the admin page — but if you ever show article_count on a
     * cached frontend page, bust self::CACHE_KEY in refreshCount() first.
     */
    public static function ordered()
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => static::orderBy('name')->get());
    }

    public function refreshCount(): void
    {
        // Count published articles where this category is the primary OR an
        // additional (pivot) category — mirrors the listing in
        // FrontendController::category() so the badge matches what's shown.
        $count = Article::query()
            ->where('status', 'published')
            ->where(function ($q) {
                $q->where('category_id', $this->id)
                  ->orWhereHas('categories', fn ($c) => $c->where('categories.id', $this->id));
            })
            ->count();

        if ((int) $this->article_count === $count) {
            return; // unchanged — skip the write (and its cache-busting saved hook)
        }

        // Write via the query builder so this high-frequency denormalisation
        // (it runs on every article save) does NOT fire the model saved hook and
        // its blanket ResponseCache::clear(). The article's observer already does
        // a targeted forget for the affected URLs. Same discipline as the
        // views/likes counters, which also bypass model events.
        //
        // CAVEAT: this also means self::CACHE_KEY (the ordered() collection) is
        // NOT busted on a count change. Safe only because article_count is read
        // fresh on the admin page and never rendered from the cached collection —
        // see ordered(). Render it on a cached frontend page and you must add
        // Cache::forget(self::CACHE_KEY) here.
        static::whereKey($this->id)->update(['article_count' => $count]);
        $this->article_count = $count;
    }
}
