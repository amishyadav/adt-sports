<?php

namespace App\Models\Ktms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A tackle or other defensive act, usually inside a raid but able to stand alone. */
class DefensiveAction extends Model
{
    protected $table = 'ktms_defensive_actions';

    public const TYPE_TACKLE = 'tackle';
    public const TYPE_SUPER_TACKLE = 'super_tackle';

    public const ACTION_TYPES = [
        'tackle' => 'Tackle',
        'super_tackle' => 'Super Tackle',
        'ankle_hold' => 'Ankle Hold',
        'thigh_hold' => 'Thigh Hold',
        'block' => 'Block',
        'dash' => 'Dash',
        'chain_tackle' => 'Chain Tackle',
    ];

    protected $fillable = [
        'match_id', 'raid_id', 'defender_id', 'team_id', 'action_type',
        'is_successful', 'points_awarded', 'half', 'game_clock_at_event', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_successful' => 'boolean',
            'points_awarded' => 'integer',
            'half' => 'integer',
            'game_clock_at_event' => 'integer',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function raid(): BelongsTo
    {
        return $this->belongsTo(Raid::class, 'raid_id');
    }

    public function defender(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'defender_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function getActionTypeLabelAttribute(): string
    {
        return self::ACTION_TYPES[$this->action_type]
            ?? ucfirst(str_replace('_', ' ', (string) $this->action_type));
    }
}
