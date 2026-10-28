<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeamMatch extends Model
{
    use HasFactory;

    /** "matches" is a reserved-ish word in several engines — table is explicit. */
    protected $table = 'team_matches';

    protected $fillable = [
        'team_1', 'team_2', 'status',
        'team_1_score', 'team_2_score',
        'players_left_1', 'players_left_2',
        'scheduled_at', 'venue',
        'timer_started_at', 'timer_elapsed_seconds', 'timer_duration_seconds', 'period',
        'raid_team', 'raid_started_at', 'raid_duration_seconds',
        'courts_swapped',
    ];

    protected $casts = [
        'scheduled_at'           => 'datetime',
        'timer_started_at'       => 'datetime',
        'raid_started_at'        => 'datetime',
        'team_1_score'           => 'integer',
        'team_2_score'           => 'integer',
        'players_left_1'         => 'integer',
        'players_left_2'         => 'integer',
        'timer_elapsed_seconds'  => 'integer',
        'timer_duration_seconds' => 'integer',
        'raid_duration_seconds'  => 'integer',
        'raid_team'              => 'integer',
        'courts_swapped'         => 'boolean',
    ];

    /** The match lifecycle. Stored as a plain string — see the migration. */
    public const STATUSES = ['pending', 'live', 'completed', 'cancelled'];

    /** Full strength on the mat at the start of a half, and after an all out. */
    public const FULL_SQUAD = 7;

    /* ── Relationships ─────────────────────────────────── */

    public function team1(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_1');
    }

    public function team2(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_2');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MatchEvent::class, 'team_match_id');
    }

    /* ── Scopes ────────────────────────────────────────── */

    /** Fixtures involving a given team on either side. */
    public function scopeForTeam(Builder $q, int $teamId): Builder
    {
        return $q->where(fn ($x) => $x->where('team_1', $teamId)->orWhere('team_2', $teamId));
    }

    public function scopeSearch(Builder $q, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') {
            return $q;
        }

        return $q->where(fn ($x) => $x
            ->whereHas('team1', fn ($t) => $t->where('name', 'like', "%{$term}%"))
            ->orWhereHas('team2', fn ($t) => $t->where('name', 'like', "%{$term}%"))
            ->orWhere('venue', 'like', "%{$term}%"));
    }

    /* ── Accessors ─────────────────────────────────────── */

    public function getStatusLabelAttribute(): string
    {
        return ucfirst($this->status);
    }

    /** "Chennai vs Jaipur" — the pairing, in team_1 / team_2 order. */
    public function getTitleAttribute(): string
    {
        return ($this->team1->name ?? 'Team 1') . ' vs ' . ($this->team2->name ?? 'Team 2');
    }

    /* ── Match clock (counts down) ─────────────────────── */

    public function getTimerRunningAttribute(): bool
    {
        return $this->timer_started_at !== null;
    }

    /**
     * Seconds burned off the clock: whatever earlier runs banked, plus the time
     * since the current run started. Derived from two columns rather than stored
     * as a ticking value, so every open screen computes the same number and a
     * reload or a restart loses nothing.
     */
    public function getElapsedSecondsAttribute(): int
    {
        $seconds = (int) $this->timer_elapsed_seconds;

        if ($this->timer_started_at) {
            // Carbon 3 returns a signed float here — floor it and clamp, so a
            // slightly-future start time can't read as negative.
            $seconds += (int) max(0, floor($this->timer_started_at->diffInSeconds(now())));
        }

        return $seconds;
    }

    /** What the board shows: time left in the half, never below zero. */
    public function getRemainingSecondsAttribute(): int
    {
        return max(0, (int) $this->timer_duration_seconds - $this->elapsed_seconds);
    }

    public function getClockAttribute(): string
    {
        return self::formatClock($this->remaining_seconds);
    }

    /* ── Raid clock (30 seconds, counts down) ──────────── */

    public function getRaidRunningAttribute(): bool
    {
        return $this->raid_team !== null && $this->raid_started_at !== null;
    }

    /** Seconds left in the current raid, or a full raid when nobody is in. */
    public function getRaidRemainingAttribute(): int
    {
        if (! $this->raid_running) {
            return (int) $this->raid_duration_seconds;
        }

        $elapsed = (int) max(0, floor($this->raid_started_at->diffInSeconds(now())));

        return max(0, (int) $this->raid_duration_seconds - $elapsed);
    }

    /* ── Court sides ───────────────────────────────────── */

    /**
     * The two sides in display order. Teams change ends at half time, so the
     * boards read from here instead of assuming team_1 is always on the left —
     * that keeps each score attached to its own team through a swap.
     */
    public function sides(): array
    {
        $first = $this->sideData(1);
        $second = $this->sideData(2);

        return $this->courts_swapped ? [$second, $first] : [$first, $second];
    }

    public function sideData(int $side): array
    {
        $team = $side === 1 ? $this->team1 : $this->team2;

        return [
            'side'    => $side,
            'name'    => $team->name ?? 'Team ' . $side,
            'short'   => $team->display_name ?? 'T' . $side,
            'logo'    => $team->logo ?? null,
            'score'   => (int) ($side === 1 ? $this->team_1_score : $this->team_2_score),
            'players' => (int) ($side === 1 ? $this->players_left_1 : $this->players_left_2),
            'raiding' => $this->raid_team === $side,
        ];
    }

    /* ── Console state ─────────────────────────────────── */

    /**
     * Everything an operator action can change. Captured before each event so
     * Undo can restore it verbatim instead of inverting the action.
     */
    public function scoreState(): array
    {
        return [
            'team_1_score'           => (int) $this->team_1_score,
            'team_2_score'           => (int) $this->team_2_score,
            'players_left_1'         => (int) $this->players_left_1,
            'players_left_2'         => (int) $this->players_left_2,
            'period'                 => $this->period,
            'status'                 => $this->status,
            'courts_swapped'         => (bool) $this->courts_swapped,
            'timer_started_at'       => $this->timer_started_at?->toDateTimeString(),
            'timer_elapsed_seconds'  => (int) $this->timer_elapsed_seconds,
            'timer_duration_seconds' => (int) $this->timer_duration_seconds,
            'raid_team'              => $this->raid_team,
            'raid_started_at'        => $this->raid_started_at?->toDateTimeString(),
        ];
    }

    /**
     * The most recent scoring event, for the on-air animation sting.
     *
     * Only the raid presets qualify — a players-left tweak or a court swap must
     * not throw a banner on the stream. The id is what the screens dedupe on:
     * they fire once when it increases and ignore it on first load, so opening
     * an overlay mid-match never replays an old event.
     */
    public function animationCue(): ?array
    {
        $event = $this->events()
            ->whereIn('type', array_keys(MatchEvent::PRESETS))
            ->orderByDesc('id')
            ->first();

        if (! $event) {
            return null;
        }

        // An all out is recorded against the side that was wiped out, but the
        // points — and so the name worth showing — belong to the other side.
        $credited = $event->type === 'all_out'
            ? ($event->team === 1 ? 2 : 1)
            : $event->team;

        return [
            'id'        => $event->id,
            'type'      => $event->type,
            'label'     => $event->label,
            'points'    => $event->points,
            'team'      => $credited,
            'team_name' => $credited ? $this->sideData($credited)['name'] : null,
        ];
    }

    /** Everything the live screens poll for, in one shape. */
    public function toScreenPayload(): array
    {
        [$left, $right] = $this->sides();

        return [
            'cue'            => $this->animationCue(),
            // Server wall-clock, so each screen can work out its own offset and
            // derive both countdowns from timestamps instead of counting down
            // locally. Independent local counters are what made the match clock
            // and the raid clock drift apart.
            'now'            => (int) round(microtime(true) * 1000),
            'clocks'         => [
                'timer' => [
                    'running'    => $this->timer_running,
                    'started_at' => $this->timer_started_at ? $this->timer_started_at->getTimestamp() * 1000 : null,
                    'elapsed'    => (int) $this->timer_elapsed_seconds,
                    'duration'   => (int) $this->timer_duration_seconds,
                ],
                'raid' => [
                    'running'    => $this->raid_running,
                    'started_at' => $this->raid_started_at ? $this->raid_started_at->getTimestamp() * 1000 : null,
                    'duration'   => (int) $this->raid_duration_seconds,
                ],
            ],
            'id'             => $this->id,
            'status'         => $this->status,
            'label'          => $this->status_label,
            'period'         => $this->period,
            'venue'          => $this->venue,
            'title'          => $this->title,
            'clock'          => $this->clock,
            'seconds'        => $this->remaining_seconds,
            'running'        => $this->timer_running,
            'raid'           => $this->raid_remaining,
            'raid_running'   => $this->raid_running,
            'raid_team'      => $this->raid_team,
            'courts_swapped' => (bool) $this->courts_swapped,
            'full_squad'     => self::FULL_SQUAD,
            'left'           => $left,
            'right'          => $right,
            // Kept in team_1 / team_2 order for anything that needs it by side.
            'teams'          => [$this->sideData(1), $this->sideData(2)],
        ];
    }

    /** mm:ss, widening to h:mm:ss only if a half somehow runs past an hour. */
    public static function formatClock(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $seconds % 60)
            : sprintf('%02d:%02d', $minutes, $seconds % 60);
    }
}
