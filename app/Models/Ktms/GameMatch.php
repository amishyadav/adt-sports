<?php

namespace App\Models\Ktms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single kabaddi match in the KTMS section.
 *
 * Named GameMatch rather than Match because `match` is a reserved keyword in
 * PHP 8, so it maps explicitly onto `ktms_matches`.
 */
class GameMatch extends Model
{
    protected $table = 'ktms_matches';

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_LIVE = 'live';
    public const STATUS_HALF_TIME = 'half_time';
    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_SCHEDULED => 'Scheduled',
        self::STATUS_LIVE => 'Live',
        self::STATUS_HALF_TIME => 'Half Time',
        self::STATUS_COMPLETED => 'Completed',
    ];

    /** Statuses that mean the match has actually got under way. */
    public const STARTED_STATUSES = [self::STATUS_LIVE, self::STATUS_HALF_TIME, self::STATUS_COMPLETED];

    protected $fillable = [
        'tournament_id', 'home_team_id', 'away_team_id', 'match_number', 'round',
        'venue', 'scheduled_at', 'status', 'home_score', 'away_score',
        'home_players_on_court', 'away_players_on_court', 'players_per_side',
        'current_half', 'half_duration_seconds',
        'game_clock_remaining_seconds', 'game_timer_running', 'game_timer_updated_at',
        'raid_duration_seconds', 'raid_clock_remaining_seconds', 'raid_timer_running', 'raid_timer_updated_at',
        'is_raid_active', 'raiding_team_id', 'raid_count',
        'winner_team_id', 'started_at', 'completed_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_SCHEDULED,
        'players_per_side' => 7,
        'half_duration_seconds' => 1200,
        'raid_duration_seconds' => 30,
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'game_timer_updated_at' => 'datetime',
            'raid_timer_updated_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'game_timer_running' => 'boolean',
            'raid_timer_running' => 'boolean',
            'is_raid_active' => 'boolean',
            'home_score' => 'integer',
            'away_score' => 'integer',
            'home_players_on_court' => 'integer',
            'away_players_on_court' => 'integer',
            'players_per_side' => 'integer',
            'current_half' => 'integer',
            'half_duration_seconds' => 'integer',
            'game_clock_remaining_seconds' => 'integer',
            'raid_duration_seconds' => 'integer',
            'raid_clock_remaining_seconds' => 'integer',
            'raid_count' => 'integer',
        ];
    }

    /* ─── Relationships ────────────────────────────────────── */

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class, 'tournament_id');
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function raidingTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'raiding_team_id');
    }

    public function winnerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_team_id');
    }

    public function raids(): HasMany
    {
        return $this->hasMany(Raid::class, 'match_id');
    }

    public function defensiveActions(): HasMany
    {
        return $this->hasMany(DefensiveAction::class, 'match_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MatchEvent::class, 'match_id');
    }

    public function lineup(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'ktms_match_player', 'match_id', 'player_id')
            ->withPivot(['team_id', 'is_starter', 'is_on_court', 'status'])
            ->withTimestamps();
    }

    /* ─── Clocks ───────────────────────────────────────────────
     | Remaining seconds are stored alongside the timestamp at which that value
     | was true. While a timer runs the live value is derived from that anchor,
     | so every connected browser agrees on the clock without the clients having
     | to stay in lockstep.
     */

    public function effectiveGameClockRemaining(): int
    {
        return $this->deriveRemaining(
            $this->game_clock_remaining_seconds,
            (bool) $this->game_timer_running,
            $this->game_timer_updated_at,
        );
    }

    public function effectiveRaidClockRemaining(): int
    {
        return $this->deriveRemaining(
            $this->raid_clock_remaining_seconds,
            (bool) $this->raid_timer_running,
            $this->raid_timer_updated_at,
        );
    }

    protected function deriveRemaining(?int $remaining, bool $running, $anchor): int
    {
        $remaining = (int) $remaining;

        if ($running && $anchor) {
            $elapsed = (int) floor(max(0, $anchor->diffInSeconds(now())));
            $remaining -= $elapsed;
        }

        return max(0, $remaining);
    }

    /** Write the current derived value down and stop the game clock. */
    public function freezeGameClock(): void
    {
        $this->game_clock_remaining_seconds = $this->effectiveGameClockRemaining();
        $this->game_timer_running = false;
        $this->game_timer_updated_at = now();
    }

    public function freezeRaidClock(): void
    {
        $this->raid_clock_remaining_seconds = $this->effectiveRaidClockRemaining();
        $this->raid_timer_running = false;
        $this->raid_timer_updated_at = now();
    }

    /* ─── Convenience ──────────────────────────────────────── */

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isHomeTeam(int $teamId): bool
    {
        return (int) $this->home_team_id === $teamId;
    }

    public function involvesTeam(int $teamId): bool
    {
        return in_array($teamId, [(int) $this->home_team_id, (int) $this->away_team_id], true);
    }

    public function opponentIdOf(int $teamId): ?int
    {
        if (! $this->involvesTeam($teamId)) {
            return null;
        }

        return (int) ($this->isHomeTeam($teamId) ? $this->away_team_id : $this->home_team_id);
    }

    public function scoreColumnFor(int $teamId): string
    {
        return $this->isHomeTeam($teamId) ? 'home_score' : 'away_score';
    }

    public function courtColumnFor(int $teamId): string
    {
        return $this->isHomeTeam($teamId) ? 'home_players_on_court' : 'away_players_on_court';
    }

    public function scoreFor(int $teamId): int
    {
        return (int) $this->{$this->scoreColumnFor($teamId)};
    }

    public function playersOnCourtFor(int $teamId): int
    {
        return (int) $this->{$this->courtColumnFor($teamId)};
    }

    /**
     * Squad size on the mat at full strength, falling back to the standard
     * seven so the value is never null on a model that has not been reloaded.
     */
    public function playersPerSide(): int
    {
        return (int) ($this->players_per_side ?: 7);
    }

    /** Apply a signed point delta to a side, never dropping below zero. */
    public function addScore(int $teamId, int $points): int
    {
        $column = $this->scoreColumnFor($teamId);
        $applied = max(0, (int) $this->{$column} + $points);
        $this->{$column} = $applied;

        return $applied;
    }

    /** Adjust how many of a side are standing, clamped to 0..players_per_side. */
    public function adjustPlayersOnCourt(int $teamId, int $delta): int
    {
        $column = $this->courtColumnFor($teamId);
        $value = max(0, min($this->playersPerSide(), (int) $this->{$column} + $delta));
        $this->{$column} = $value;

        return $value;
    }

    public function setPlayersOnCourt(int $teamId, int $value): int
    {
        $column = $this->courtColumnFor($teamId);
        $value = max(0, min($this->playersPerSide(), $value));
        $this->{$column} = $value;

        return $value;
    }

    public function defendingTeamId(): ?int
    {
        return $this->raiding_team_id ? $this->opponentIdOf((int) $this->raiding_team_id) : null;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Badge class from the KTMS stylesheet. */
    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_LIVE => 'badge-ktms-live',
            self::STATUS_HALF_TIME => 'badge-ktms-half',
            self::STATUS_COMPLETED => 'badge-ktms-done',
            default => 'badge-ktms-soon',
        };
    }

    public function getTitleAttribute(): string
    {
        return ($this->homeTeam?->name ?? 'TBD').' vs '.($this->awayTeam?->name ?? 'TBD');
    }

    /** Format a second count as mm:ss. */
    public static function formatClock(int $seconds): string
    {
        $seconds = max(0, $seconds);

        return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
