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

    /* Lineup changes made by the bench rather than by play. */
    public const TYPE_SUBSTITUTION = 'substitution';
    public const TYPE_INJURY = 'injury';

    /* Officiating decisions. */
    public const TYPE_WARNING = 'warning';
    public const TYPE_CARD_GREEN = 'card_green';
    public const TYPE_CARD_YELLOW = 'card_yellow';
    public const TYPE_CARD_RED = 'card_red';
    public const TYPE_TECHNICAL_POINT = 'technical_point';

    /**
     * The sanctions, and what each one does to the player.
     *
     * A warning and a green card are on the record only. A yellow card is a
     * suspension and a red card an expulsion, so both take the player off the
     * mat — which is why they are listed with that consequence here rather than
     * being left for the caller to remember.
     *
     * @var array<string, array{label: string, leavesMat: bool, forMatch: bool}>
     */
    public const SANCTIONS = [
        self::TYPE_WARNING => ['label' => 'Warning', 'leavesMat' => false, 'forMatch' => false],
        self::TYPE_CARD_GREEN => ['label' => 'Green card', 'leavesMat' => false, 'forMatch' => false],
        self::TYPE_CARD_YELLOW => ['label' => 'Yellow card', 'leavesMat' => true, 'forMatch' => false],
        self::TYPE_CARD_RED => ['label' => 'Red card', 'leavesMat' => true, 'forMatch' => true],
    ];

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
            self::TYPE_SUBSTITUTION => 'fa-right-left',
            self::TYPE_INJURY => 'fa-kit-medical',
            self::TYPE_WARNING => 'fa-bell',
            self::TYPE_CARD_GREEN,
            self::TYPE_CARD_YELLOW,
            self::TYPE_CARD_RED => 'fa-square',
            self::TYPE_TECHNICAL_POINT => 'fa-gavel',
            default => 'fa-circle-dot',
        };
    }

    /**
     * A colour token for the timeline icon.
     *
     * Cards are the one thing an operator scans a timeline for, and three
     * identically coloured squares would defeat the point.
     */
    public function getIconToneAttribute(): string
    {
        return match ($this->event_type) {
            self::TYPE_CARD_GREEN => 'green',
            self::TYPE_CARD_YELLOW, self::TYPE_WARNING => 'amber',
            self::TYPE_CARD_RED, self::TYPE_ALL_OUT => 'red',
            self::TYPE_INJURY => 'red',
            self::TYPE_SUBSTITUTION => 'info',
            self::TYPE_TECHNICAL_POINT => 'amber',
            default => 'brand',
        };
    }
}
