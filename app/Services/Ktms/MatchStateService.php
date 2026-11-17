<?php

namespace App\Services\Ktms;

use App\Models\Ktms\GameMatch;
use App\Models\Ktms\MatchEvent;
use App\Models\Ktms\Team;
use Illuminate\Support\Collection;

/**
 * Builds the JSON snapshot shared by the scoring console, the timer console and
 * the broadcast display, so all three always agree on the state of the match.
 */
class MatchStateService
{
    public function __construct(
        protected MatchService $matches,
        protected ScoringService $scoring,
    ) {}

    /**
     * Stop clocks that have run down and move the match on where the rules say so.
     *
     * Called before every snapshot. It only writes when something actually
     * changed, so polling stays cheap.
     */
    public function settleExpiredTimers(GameMatch $match): GameMatch
    {
        $dirty = false;

        if ($match->raid_timer_running && $match->effectiveRaidClockRemaining() <= 0) {
            // The raid clock expiring is the operator's cue, not an automatic
            // score, so stop the clock and let them record the outcome.
            $match->raid_clock_remaining_seconds = 0;
            $match->raid_timer_running = false;
            $match->raid_timer_updated_at = now();
            $dirty = true;
        }

        if ($match->game_timer_running && $match->effectiveGameClockRemaining() <= 0) {
            $match->game_clock_remaining_seconds = 0;
            $match->game_timer_running = false;
            $match->game_timer_updated_at = now();
            $match->is_raid_active = false;
            $dirty = true;

            if ($match->current_half === 1 && $match->status === GameMatch::STATUS_LIVE) {
                $match->status = GameMatch::STATUS_HALF_TIME;
                $match->save();
                $this->matches->logEvent($match, MatchEvent::TYPE_HALF_CHANGE, 'Half time');
                $dirty = false;
            } elseif ($match->current_half >= 2 && $match->status === GameMatch::STATUS_LIVE) {
                $this->matches->complete($match);
                $dirty = false;
            }
        }

        if ($dirty) {
            $match->save();
        }

        return $match;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(GameMatch $match, bool $withLineups = true): array
    {
        $this->settleExpiredTimers($match);

        $match->loadMissing(['homeTeam', 'awayTeam', 'tournament']);

        $gameRemaining = $match->effectiveGameClockRemaining();
        $raidRemaining = $match->effectiveRaidClockRemaining();

        $lastRaid = $match->raids()
            ->with('raider')
            ->orderByDesc('raid_number')
            ->first();

        return [
            'match' => [
                'id' => $match->id,
                'status' => $match->status,
                'status_label' => $match->status_label,
                'status_badge' => $match->status_badge,
                'current_half' => $match->current_half,
                'match_number' => $match->match_number,
                'round' => $match->round,
                'venue' => $match->venue,
                'players_per_side' => $match->playersPerSide(),
                'raid_count' => $match->raid_count,
                'is_raid_active' => (bool) $match->is_raid_active,
                'raiding_team_id' => $match->raiding_team_id,
                'defending_team_id' => $match->defendingTeamId(),
                'winner_team_id' => $match->winner_team_id,
                'tournament' => [
                    'id' => $match->tournament?->id,
                    'name' => $match->tournament?->name,
                ],
            ],

            'score' => [
                'home' => (int) $match->home_score,
                'away' => (int) $match->away_score,
            ],

            'clocks' => [
                'game' => [
                    'remaining' => $gameRemaining,
                    'formatted' => GameMatch::formatClock($gameRemaining),
                    'running' => (bool) $match->game_timer_running,
                    'duration' => (int) $match->half_duration_seconds,
                    'expired' => $gameRemaining <= 0,
                ],
                'raid' => [
                    'remaining' => $raidRemaining,
                    'formatted' => GameMatch::formatClock($raidRemaining),
                    'running' => (bool) $match->raid_timer_running,
                    'duration' => (int) $match->raid_duration_seconds,
                    'expired' => $raidRemaining <= 0,
                ],
            ],

            'teams' => [
                'home' => $this->teamPayload($match, $match->homeTeam, 'home', $withLineups),
                'away' => $this->teamPayload($match, $match->awayTeam, 'away', $withLineups),
            ],

            'last_raid' => $lastRaid ? [
                'id' => $lastRaid->id,
                'raid_number' => $lastRaid->raid_number,
                'result' => $lastRaid->result,
                'result_label' => $lastRaid->result_label,
                'result_badge' => $lastRaid->result_badge,
                'raid_points' => $lastRaid->raid_points,
                'defending_points' => $lastRaid->defending_points,
                'is_super_raid' => (bool) $lastRaid->is_super_raid,
                'is_do_or_die' => (bool) $lastRaid->is_do_or_die,
                'raider' => $lastRaid->raider?->display_name,
                'raiding_team_id' => $lastRaid->raiding_team_id,
            ] : null,

            // Warn the operator when the side about to raid must score.
            'next_raid_is_do_or_die' => $match->raiding_team_id
                ? $this->scoring->isDoOrDieRaid($match, (int) $match->raiding_team_id)
                : false,

            'timeline' => $this->timeline($match),

            // Clients use this to line their local countdown up with the server.
            'server_time' => now()->toIso8601String(),
            'revision' => $match->updated_at?->getTimestamp(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function teamPayload(GameMatch $match, ?Team $team, string $side, bool $withLineups): array
    {
        if (! $team) {
            return ['id' => null, 'name' => 'TBD', 'side' => $side];
        }

        $payload = [
            'id' => $team->id,
            'side' => $side,
            'name' => $team->name,
            'short_name' => $team->short_name,
            'initials' => $team->initials,
            'logo_url' => $team->logo_url,
            'primary_color' => $team->primary_color,
            'secondary_color' => $team->secondary_color,
            'score' => $match->scoreFor($team->id),
            'players_on_court' => $match->playersOnCourtFor($team->id),
            'is_raiding' => (int) $match->raiding_team_id === (int) $team->id,
        ];

        if ($withLineups) {
            $payload['lineup'] = $this->lineup($match, (int) $team->id);
        }

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function lineup(GameMatch $match, int $teamId): array
    {
        return $match->lineup()
            ->wherePivot('team_id', $teamId)
            ->orderBy('jersey_number')
            ->get()
            ->map(fn ($player) => [
                'id' => $player->id,
                'name' => $player->name,
                'jersey_number' => $player->jersey_number,
                'display_name' => $player->display_name,
                'role' => $player->role,
                'role_label' => $player->role_label,
                'position_label' => $player->position_label,
                'is_captain' => (bool) $player->is_captain,
                'is_starter' => (bool) $player->pivot->is_starter,
                'is_on_court' => (bool) $player->pivot->is_on_court,
                'status' => $player->pivot->status,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function timeline(GameMatch $match, int $limit = 15): array
    {
        return $match->events()
            ->with(['team', 'player'])
            ->where('is_undone', false)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn ($event) => [
                'id' => $event->id,
                'type' => $event->event_type,
                'description' => $event->description,
                'points' => $event->points,
                'half' => $event->half,
                'clock' => $event->clock_label,
                'icon' => $event->icon,
                'team_id' => $event->team_id,
                'team_name' => $event->team?->name,
                'player_name' => $event->player?->display_name,
            ])
            ->values()
            ->all();
    }

    /**
     * Players a side can pick from, split into those on the mat and those off it.
     *
     * @return Collection<string, mixed>
     */
    public function selectablePlayers(GameMatch $match, int $teamId): Collection
    {
        $players = $match->lineup()
            ->wherePivot('team_id', $teamId)
            ->orderBy('jersey_number')
            ->get();

        return collect([
            'on_court' => $players->filter(fn ($p) => (bool) $p->pivot->is_on_court)->values(),
            'off_court' => $players->reject(fn ($p) => (bool) $p->pivot->is_on_court)->values(),
        ]);
    }
}
