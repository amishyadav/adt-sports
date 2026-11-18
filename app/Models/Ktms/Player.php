<?php

namespace App\Models\Ktms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    protected $table = 'ktms_players';

    public const ROLE_RAIDER = 'raider';
    public const ROLE_DEFENDER = 'defender';
    public const ROLE_ALL_ROUNDER = 'all-rounder';

    public const ROLES = [
        self::ROLE_RAIDER => 'Raider',
        self::ROLE_DEFENDER => 'Defender',
        self::ROLE_ALL_ROUNDER => 'All-rounder',
    ];

    public const POSITIONS = [
        'left-corner' => 'Left Corner',
        'left-in' => 'Left In',
        'left-cover' => 'Left Cover',
        'center' => 'Center',
        'right-cover' => 'Right Cover',
        'right-in' => 'Right In',
        'right-corner' => 'Right Corner',
    ];

    protected $fillable = [
        'team_id', 'name', 'jersey_number', 'role', 'position',
        'date_of_birth', 'height_cm', 'weight_kg', 'nationality',
        'is_captain', 'is_active',
    ];

    protected $attributes = [
        'role' => self::ROLE_ALL_ROUNDER,
        'is_captain' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'jersey_number' => 'integer',
            'height_cm' => 'integer',
            'weight_kg' => 'integer',
            'is_captain' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function statistics(): HasMany
    {
        return $this->hasMany(PlayerStatistic::class, 'player_id');
    }

    public function raids(): HasMany
    {
        return $this->hasMany(Raid::class, 'raider_id');
    }

    public function defensiveActions(): HasMany
    {
        return $this->hasMany(DefensiveAction::class, 'defender_id');
    }

    public function matches(): BelongsToMany
    {
        return $this->belongsToMany(GameMatch::class, 'ktms_match_player', 'player_id', 'match_id')
            ->withPivot(['team_id', 'is_starter', 'is_on_court', 'status'])
            ->withTimestamps();
    }

    /**
     * The aggregate row for this player in a tournament, created on first ask so
     * reports list every squad member even before their first match.
     */
    public function statisticFor(int $tournamentId): PlayerStatistic
    {
        return PlayerStatistic::firstOrCreate([
            'player_id' => $this->id,
            'tournament_id' => $tournamentId,
        ]);
    }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLES[$this->role] ?? ucfirst((string) $this->role);
    }

    public function getPositionLabelAttribute(): ?string
    {
        return $this->position ? (self::POSITIONS[$this->position] ?? $this->position) : null;
    }

    public function getDisplayNameAttribute(): string
    {
        return "#{$this->jersey_number} {$this->name}";
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }
}
