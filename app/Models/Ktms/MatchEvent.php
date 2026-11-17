<?php

namespace App\Models\Ktms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The match timeline. Each row keeps a snapshot of the counters as they were
 * before its action ran, and that snapshot is what undo replays.
 *
 * Deliberately not App\Models\MatchEvent — that class belongs to the original
 * match console and maps to `match_events`. This one maps to
 * `ktms_match_events`.
 */
class MatchEvent extends Model
{
    protected $table = 'ktms_match_events';

    public const TYPE_SCORE_ADJUSTMENT = 'score_adjustment';
    public const TYPE_RAID = 'raid';
    public const TYPE_DEFENSE = 'defense';
    public const TYPE_RAID_STARTED = 'raid_started';
    public const TYPE_RAID_TOGGLED = 'raid_toggled';
    public const TYPE_TIMER = 'timer';
    public const TYPE_HALF_CHANGE = 'half_change';
    public const TYPE_COURT_UPDATE = 'court_update';
    public const TYPE_STATUS_CHANGE = 'status_change';
    public const TYPE_ALL_OUT = 'all_out';

    protected $fillable = [
        'match_id', 'team_id', 'player_id', 'raid_id', 'event_type',
        'description', 'points', 'half', 'game_clock_at_event',
        'payload', 'is_undone',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'points' => 'integer',
            'half' => 'integer',
            'game_clock_at_event' => 'integer',
            'is_undone' => 'boolean',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }

    public function raid(): BelongsTo
    {
        return $this->belongsTo(Raid::class, 'raid_id');
    }

    public function getClockLabelAttribute(): string
    {
        return $this->game_clock_at_event === null
            ? '--:--'
            : GameMatch::formatClock($this->game_clock_at_event);
    }

    /** Font Awesome icon, since that is what this admin panel loads. */
    public function getIconAttribute(): string
    {
        return match ($this->event_type) {
            self::TYPE_RAID => 'fa-person-running',
            self::TYPE_DEFENSE => 'fa-shield-halved',
            self::TYPE_ALL_OUT => 'fa-triangle-exclamation',
            self::TYPE_TIMER => 'fa-stopwatch',
            self::TYPE_HALF_CHANGE => 'fa-arrows-rotate',
            self::TYPE_SCORE_ADJUSTMENT => 'fa-plus-minus',
            self::TYPE_COURT_UPDATE => 'fa-users',
            self::TYPE_STATUS_CHANGE => 'fa-flag',
            default => 'fa-circle-dot',
        };
    }
}
