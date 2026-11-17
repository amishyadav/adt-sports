<?php

namespace App\Models\Ktms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A squad entered in a KTMS tournament.
 *
 * Deliberately not App\Models\Team — that class is the original match console's
 * team and maps to the `teams` table. This one is the KTMS squad and maps to
 * `ktms_teams`; the two are unrelated.
 */
class Team extends Model
{
    protected $table = 'ktms_teams';

    protected $fillable = [
        'tournament_id', 'name', 'short_name', 'logo_path',
        'primary_color', 'secondary_color', 'coach_name', 'city',
    ];

    protected $attributes = [
        'primary_color' => '#D4420A',
        'secondary_color' => '#ffffff',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class, 'tournament_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class, 'team_id')->orderBy('jersey_number');
    }

    public function homeMatches(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'home_team_id');
    }

    public function awayMatches(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'away_team_id');
    }

    public function raids(): HasMany
    {
        return $this->hasMany(Raid::class, 'raiding_team_id');
    }

    public function defensiveActions(): HasMany
    {
        return $this->hasMany(DefensiveAction::class, 'team_id');
    }

    /**
     * Every match this squad appears in, home or away.
     *
     * Returns a query rather than a relation because a side can occupy either
     * column and Eloquent has no single relation for that.
     */
    public function matches()
    {
        return GameMatch::query()
            ->where(fn ($q) => $q->where('home_team_id', $this->id)->orWhere('away_team_id', $this->id));
    }

    public function getInitialsAttribute(): string
    {
        if (filled($this->short_name)) {
            return Str::upper(Str::substr($this->short_name, 0, 3));
        }

        $words = preg_split('/\s+/', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $initials = collect($words)
            ->take(3)
            ->map(fn (string $word) => Str::upper(Str::substr($word, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : 'TM';
    }

    /**
     * Crests are stored through this project's ImageUpload helper, which writes
     * into public/uploads and hands back a rooted path — so there is nothing to
     * resolve here and no storage symlink to set up.
     */
    public function getLogoUrlAttribute(): ?string
    {
        return blank($this->logo_path) ? null : $this->logo_path;
    }
}
