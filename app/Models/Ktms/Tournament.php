<?php

namespace App\Models\Ktms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

/**
 * A kabaddi competition: the squads, the fixtures and the statistics earned in it.
 *
 * Part of the KTMS section, which keeps its own tables under a ktms_ prefix and
 * its own models in this namespace so it sits alongside the original match
 * console without touching it.
 */
class Tournament extends Model
{
    protected $table = 'ktms_tournaments';

    public const STATUS_UPCOMING = 'upcoming';
    public const STATUS_ONGOING = 'ongoing';
    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_UPCOMING => 'Upcoming',
        self::STATUS_ONGOING => 'Ongoing',
        self::STATUS_COMPLETED => 'Completed',
    ];

    protected $fillable = [
        'name', 'slug', 'season', 'description', 'venue', 'city',
        'start_date', 'end_date', 'status',
        'half_duration_seconds', 'raid_duration_seconds', 'players_per_side',
    ];

    /** Mirrors the column defaults so a new model reads sensibly before saving. */
    protected $attributes = [
        'status' => self::STATUS_UPCOMING,
        'half_duration_seconds' => 1200,
        'raid_duration_seconds' => 30,
        'players_per_side' => 7,
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'half_duration_seconds' => 'integer',
            'raid_duration_seconds' => 'integer',
            'players_per_side' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Keep the slug filled so a tournament always has a clean URL key.
        static::saving(function (Tournament $tournament): void {
            if (blank($tournament->slug)) {
                $tournament->slug = static::uniqueSlug($tournament->name, $tournament->id);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'tournament';
        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class, 'tournament_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'tournament_id');
    }

    public function players(): HasManyThrough
    {
        return $this->hasManyThrough(Player::class, Team::class, 'tournament_id', 'team_id');
    }

    public function playerStatistics(): HasMany
    {
        return $this->hasMany(PlayerStatistic::class, 'tournament_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Badge class from the KTMS stylesheet. */
    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ONGOING => 'badge-ktms-live',
            self::STATUS_COMPLETED => 'badge-ktms-done',
            default => 'badge-ktms-soon',
        };
    }
}
