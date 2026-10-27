<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'short_name', 'logo', 'status'];

    protected $casts = ['status' => 'boolean'];

    /* ── Relationships ─────────────────────────────────── */

    /** Fixtures where this team is the first side. */
    public function homeMatches(): HasMany
    {
        return $this->hasMany(TeamMatch::class, 'team_1');
    }

    /** Fixtures where this team is the second side. */
    public function awayMatches(): HasMany
    {
        return $this->hasMany(TeamMatch::class, 'team_2');
    }

    /* ── Scopes ────────────────────────────────────────── */

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', true);
    }

    public function scopeSearch(Builder $q, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') {
            return $q;
        }

        return $q->where(fn ($x) => $x
            ->where('name', 'like', "%{$term}%")
            ->orWhere('short_name', 'like', "%{$term}%"));
    }

    /* ── Accessors ─────────────────────────────────────── */

    /** Total fixtures this team appears in, on either side. */
    public function getMatchesCountAttribute(): int
    {
        return ($this->home_matches_count ?? $this->homeMatches()->count())
             + ($this->away_matches_count ?? $this->awayMatches()->count());
    }

    /** Two-letter fallback shown in place of a logo. */
    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim($this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $first = isset($parts[0]) ? strtoupper(substr($parts[0], 0, 1)) : '?';
        $second = isset($parts[1]) ? strtoupper(substr($parts[1], 0, 1)) : '';

        return $first . $second;
    }

    /** Short label for scoreboards — falls back to the full name. */
    public function getDisplayNameAttribute(): string
    {
        return $this->short_name ?: $this->name;
    }
}
