<?php

namespace App\Models\Ktms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One player's aggregate record for one tournament, so a performance is always
 * scoped to the competition it was earned in.
 *
 * These rows are rebuilt from the underlying raid and defensive-action records
 * rather than nudged up and down — see StatisticsService for why.
 */
class PlayerStatistic extends Model
{
    protected $table = 'ktms_player_statistics';

    protected $fillable = [
        'player_id', 'tournament_id', 'matches_played',
        'total_raids', 'successful_raids', 'unsuccessful_raids', 'empty_raids',
        'raid_points', 'touch_points', 'bonus_points', 'super_raids',
        'do_or_die_raids', 'do_or_die_conversions',
        'total_defenses', 'successful_defenses', 'failed_defenses',
        'defense_points', 'super_tackles', 'total_points',
    ];

    /** Every counter is an integer, so cast them all in one pass. */
    protected function casts(): array
    {
        return collect($this->fillable)
            ->reject(fn (string $column) => in_array($column, ['player_id', 'tournament_id'], true))
            ->mapWithKeys(fn (string $column) => [$column => 'integer'])
            ->all();
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class, 'tournament_id');
    }

    /* ─── Derived metrics ──────────────────────────────────── */

    public function getRaidSuccessRateAttribute(): float
    {
        return $this->percentage($this->successful_raids, $this->total_raids);
    }

    public function getDefenseSuccessRateAttribute(): float
    {
        return $this->percentage($this->successful_defenses, $this->total_defenses);
    }

    public function getDoOrDieConversionRateAttribute(): float
    {
        return $this->percentage($this->do_or_die_conversions, $this->do_or_die_raids);
    }

    public function getPointsPerMatchAttribute(): float
    {
        return $this->matches_played > 0
            ? round($this->total_points / $this->matches_played, 2)
            : 0.0;
    }

    public function getRaidPointsPerRaidAttribute(): float
    {
        return $this->total_raids > 0
            ? round($this->raid_points / $this->total_raids, 2)
            : 0.0;
    }

    protected function percentage(int $part, int $whole): float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 1) : 0.0;
    }

    /** Recalculate total_points from its two components. */
    public function syncTotalPoints(): void
    {
        $this->total_points = $this->raid_points + $this->defense_points;
    }
}
