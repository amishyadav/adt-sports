<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One operator action on the scoring console, with the match state as it was
 * immediately before. Doubles as the match feed and as the undo stack.
 */
class MatchEvent extends Model
{
    public $timestamps = false; // created_at is written on insert, never updated

    protected $fillable = [
        'team_match_id', 'user_id', 'type', 'team', 'points', 'description', 'snapshot', 'created_at',
    ];

    protected $casts = [
        'snapshot'   => 'array',
        'points'     => 'integer',
        'team'       => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Quick-scoring presets. Kabaddi values: a safe raid is worth the touch
     * point, a super raid takes three or more defenders out, and an all out
     * hands two points to the opposing side.
     *
     * Do or Die carries NO points. It is a situation, not an outcome — the
     * third consecutive empty raid — so the console logs it to the match feed
     * and throws the on-air sting without moving the scoreline. Whatever the
     * raid then yields is entered with the normal buttons.
     */
    public const PRESETS = [
        'safe_raid'  => ['label' => 'Safe Raid',  'points' => 1],
        'super_raid' => ['label' => 'Super Raid', 'points' => 3],
        'do_or_die'  => ['label' => 'Do or Die',  'points' => 0],
        'all_out'    => ['label' => 'All Out',    'points' => 2],
    ];

    /** Presets that only mark a situation rather than changing the score. */
    public function isMarker(): bool
    {
        return (self::PRESETS[$this->type]['points'] ?? 0) === 0;
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(TeamMatch::class, 'team_match_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getLabelAttribute(): string
    {
        return self::PRESETS[$this->type]['label'] ?? ucfirst(str_replace('_', ' ', $this->type));
    }
}
