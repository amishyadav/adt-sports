<?php

namespace App\Services\Ktms;

use App\Models\Ktms\GameMatch;
use App\Models\Ktms\MatchEvent;
use App\Models\Ktms\Tournament;
use Illuminate\Support\Facades\DB;

/**
 * Owns the match lifecycle: creation, lineups, both clocks, half changes and
 * completion. The scoring side of the house lives in ScoringService.
 */
class MatchService
{
    public function __construct(protected StatisticsService $statistics) {}

    /* ─── Creation & lineup ────────────────────────────────── */

    /**
     * @param  array<string, mixed>  $data
     */
    public function createForTournament(Tournament $tournament, array $data): GameMatch
    {
        return DB::transaction(function () use ($tournament, $data) {
            $half = (int) ($data['half_duration_seconds'] ?? $tournament->half_duration_seconds ?? 1200);
            $raid = (int) ($data['raid_duration_seconds'] ?? $tournament->raid_duration_seconds ?? 30);
            $perSide = (int) ($data['players_per_side'] ?? $tournament->players_per_side ?? 7);

            $match = $tournament->matches()->create([
                ...$data,
                'status' => $data['status'] ?? GameMatch::STATUS_SCHEDULED,
                'match_number' => $data['match_number'] ?? ((int) $tournament->matches()->max('match_number') + 1),
                'venue' => $data['venue'] ?? $tournament->venue,
                'half_duration_seconds' => $half,
                'raid_duration_seconds' => $raid,
                'players_per_side' => $perSide,
                // Every live counter starts from a known, explicit baseline.
                'home_score' => 0,
                'away_score' => 0,
                'current_half' => 1,
                'game_clock_remaining_seconds' => $half,
                'game_timer_running' => false,
                'game_timer_updated_at' => now(),
                'raid_clock_remaining_seconds' => $raid,
                'raid_timer_running' => false,
                'raid_timer_updated_at' => now(),
                'is_raid_active' => false,
                'raid_count' => 0,
                'home_players_on_court' => $perSide,
                'away_players_on_court' => $perSide,
            ]);

            $this->initialiseLineup($match);

            return $match;
        });
    }

    /**
     * Seat every squad member: the first `players_per_side` active players by
     * jersey number start on the mat, the rest wait on the bench.
     */
    public function initialiseLineup(GameMatch $match): void
    {
        $match->load(['homeTeam.players', 'awayTeam.players']);

        DB::transaction(function () use ($match) {
            $match->lineup()->detach();

            foreach ([$match->homeTeam, $match->awayTeam] as $team) {
                if (! $team) {
                    continue;
                }

                $players = $team->players
                    ->where('is_active', true)
                    ->sortBy('jersey_number')
                    ->values();

                foreach ($players as $index => $player) {
                    $isStarter = $index < $match->playersPerSide();

                    $match->lineup()->attach($player->id, [
                        'team_id' => $team->id,
                        'is_starter' => $isStarter,
                        'is_on_court' => $isStarter,
                        'status' => $isStarter ? 'on_court' : 'bench',
                    ]);
                }
            }
        });
    }

    /** Put a side back to full strength, which is what happens after an all out. */
    public function reviveTeam(GameMatch $match, int $teamId): void
    {
        $match->lineup()
            ->wherePivot('team_id', $teamId)
            ->get()
            ->each(function ($player) use ($match) {
                $match->lineup()->updateExistingPivot($player->id, [
                    'is_on_court' => (bool) $player->pivot->is_starter,
                    'status' => $player->pivot->is_starter ? 'on_court' : 'bench',
                ]);
            });

        $match->setPlayersOnCourt($teamId, $match->playersPerSide());
    }

    /* ─── Status transitions ───────────────────────────────── */

    public function start(GameMatch $match): GameMatch
    {
        $match->status = GameMatch::STATUS_LIVE;
        $match->started_at ??= now();
        $match->game_timer_running = true;
        $match->game_timer_updated_at = now();
        $match->save();

        $this->logEvent($match, MatchEvent::TYPE_STATUS_CHANGE, 'Match under way');

        return $match;
    }

    public function setStatus(GameMatch $match, string $status): GameMatch
    {
        $match->status = $status;

        if ($status !== GameMatch::STATUS_LIVE) {
            // Never leave a clock ticking on a match that is not running.
            $match->freezeGameClock();
            $match->freezeRaidClock();
        }

        if ($status === GameMatch::STATUS_COMPLETED) {
            return $this->complete($match);
        }

        $match->save();

        $this->logEvent($match, MatchEvent::TYPE_STATUS_CHANGE, 'Status set to '.$match->status_label);

        return $match;
    }

    public function complete(GameMatch $match): GameMatch
    {
        $match->status = GameMatch::STATUS_COMPLETED;
        $match->freezeGameClock();
        $match->freezeRaidClock();
        $match->is_raid_active = false;
        $match->completed_at = now();
        $match->winner_team_id = match (true) {
            $match->home_score > $match->away_score => $match->home_team_id,
            $match->away_score > $match->home_score => $match->away_team_id,
            default => null, // a tie
        };
        $match->save();

        $result = $match->winner_team_id
            ? ($match->winnerTeam?->name.' win '.$match->home_score.'-'.$match->away_score)
            : "Match tied {$match->home_score}-{$match->away_score}";

        $this->logEvent($match, MatchEvent::TYPE_STATUS_CHANGE, "Full time. {$result}");

        // matches_played only counts started matches, so refresh the squads.
        $this->recalculateSquadStatistics($match);

        return $match;
    }

    /* ─── Game clock ───────────────────────────────────────── */

    public function startGameClock(GameMatch $match): GameMatch
    {
        if ($match->effectiveGameClockRemaining() <= 0) {
            return $match;
        }

        // Re-anchor from the current derived value so repeated starts are harmless.
        $match->game_clock_remaining_seconds = $match->effectiveGameClockRemaining();
        $match->game_timer_running = true;
        $match->game_timer_updated_at = now();

        if ($match->status === GameMatch::STATUS_SCHEDULED) {
            $match->status = GameMatch::STATUS_LIVE;
            $match->started_at ??= now();
        }

        $match->save();

        $this->logEvent($match, MatchEvent::TYPE_TIMER, 'Game clock started');

        return $match;
    }

    public function pauseGameClock(GameMatch $match): GameMatch
    {
        $match->freezeGameClock();
        $match->save();

        $this->logEvent($match, MatchEvent::TYPE_TIMER, 'Game clock paused');

        return $match;
    }

    public function resetGameClock(GameMatch $match): GameMatch
    {
        $match->game_clock_remaining_seconds = $match->half_duration_seconds;
        $match->game_timer_running = false;
        $match->game_timer_updated_at = now();
        $match->save();

        $this->logEvent($match, MatchEvent::TYPE_TIMER, 'Game clock reset');

        return $match;
    }

    /** Nudge the game clock, for operator corrections. */
    public function adjustGameClock(GameMatch $match, int $seconds): GameMatch
    {
        $current = $match->effectiveGameClockRemaining();
        $updated = max(0, min($match->half_duration_seconds, $current + $seconds));

        $match->game_clock_remaining_seconds = $updated;
        $match->game_timer_updated_at = now();
        $match->save();

        $this->logEvent($match, MatchEvent::TYPE_TIMER, sprintf(
            'Game clock adjusted by %+ds to %s',
            $seconds,
            GameMatch::formatClock($updated)
        ));

        return $match;
    }

    public function setHalf(GameMatch $match, int $half): GameMatch
    {
        $half = max(1, min(2, $half));
        $match->current_half = $half;

        // A new half always begins with a full game clock and a fresh raid clock.
        $match->game_clock_remaining_seconds = $match->half_duration_seconds;
        $match->game_timer_running = false;
        $match->game_timer_updated_at = now();
        $match->raid_clock_remaining_seconds = $match->raid_duration_seconds;
        $match->raid_timer_running = false;
        $match->raid_timer_updated_at = now();
        $match->is_raid_active = false;
        $match->raiding_team_id = null;

        // Both sides return to full strength at the restart.
        $match->setPlayersOnCourt((int) $match->home_team_id, $match->playersPerSide());
        $match->setPlayersOnCourt((int) $match->away_team_id, $match->playersPerSide());

        if ($match->status === GameMatch::STATUS_HALF_TIME && $half === 2) {
            $match->status = GameMatch::STATUS_LIVE;
        }

        $match->save();

        $this->reviveTeam($match, (int) $match->home_team_id);
        $this->reviveTeam($match, (int) $match->away_team_id);
        $match->save();

        $this->logEvent($match, MatchEvent::TYPE_HALF_CHANGE, "Half {$half} set up");

        return $match;
    }

    /* ─── Raid clock ───────────────────────────────────────── */

    public function startRaidClock(GameMatch $match): GameMatch
    {
        $remaining = $match->effectiveRaidClockRemaining();

        // Starting an expired raid clock implies the operator wants a fresh one.
        $match->raid_clock_remaining_seconds = $remaining > 0 ? $remaining : $match->raid_duration_seconds;
        $match->raid_timer_running = true;
        $match->raid_timer_updated_at = now();
        $match->save();

        $this->logEvent($match, MatchEvent::TYPE_TIMER, 'Raid clock started');

        return $match;
    }

    public function pauseRaidClock(GameMatch $match): GameMatch
    {
        $match->freezeRaidClock();
        $match->save();

        $this->logEvent($match, MatchEvent::TYPE_TIMER, 'Raid clock paused');

        return $match;
    }

    /** Put the raid clock back to its full duration, optionally leaving it running. */
    public function resetRaidClock(GameMatch $match, bool $autoStart = false): GameMatch
    {
        $match->raid_clock_remaining_seconds = $match->raid_duration_seconds;
        $match->raid_timer_running = $autoStart;
        $match->raid_timer_updated_at = now();
        $match->save();

        $this->logEvent(
            $match,
            MatchEvent::TYPE_TIMER,
            $autoStart ? 'Raid clock restarted' : 'Raid clock reset'
        );

        return $match;
    }

    /* ─── Helpers ──────────────────────────────────────────── */

    /**
     * Append an entry to the match timeline.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function logEvent(GameMatch $match, string $type, string $description, array $attributes = []): MatchEvent
    {
        return $match->events()->create([
            'event_type' => $type,
            'description' => $description,
            'half' => $match->current_half,
            'game_clock_at_event' => $match->effectiveGameClockRemaining(),
            ...$attributes,
        ]);
    }

    public function recalculateSquadStatistics(GameMatch $match): void
    {
        $playerIds = DB::table('ktms_match_player')
            ->where('match_id', $match->id)
            ->pluck('player_id');

        $this->statistics->recalculatePlayers($playerIds, (int) $match->tournament_id);
    }

    /** Wipe every scoring record for a match and put it back to its opening state. */
    public function resetLiveState(GameMatch $match): GameMatch
    {
        DB::transaction(function () use ($match) {
            $playerIds = DB::table('ktms_match_player')->where('match_id', $match->id)->pluck('player_id');

            $match->events()->delete();
            $match->defensiveActions()->delete();
            $match->raids()->delete();

            $match->forceFill([
                'status' => GameMatch::STATUS_SCHEDULED,
                'home_score' => 0,
                'away_score' => 0,
                'current_half' => 1,
                'game_clock_remaining_seconds' => $match->half_duration_seconds,
                'game_timer_running' => false,
                'game_timer_updated_at' => now(),
                'raid_clock_remaining_seconds' => $match->raid_duration_seconds,
                'raid_timer_running' => false,
                'raid_timer_updated_at' => now(),
                'is_raid_active' => false,
                'raiding_team_id' => null,
                'raid_count' => 0,
                'home_players_on_court' => $match->playersPerSide(),
                'away_players_on_court' => $match->playersPerSide(),
                'winner_team_id' => null,
                'started_at' => null,
                'completed_at' => null,
            ])->save();

            $this->initialiseLineup($match);

            // Aggregates must forget the deleted records.
            $this->statistics->recalculatePlayers($playerIds, (int) $match->tournament_id);
        });

        return $match->refresh();
    }

    /**
     * Squads available to face each other in a tournament.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ktms\Team>
     */
    public function selectableTeams(Tournament $tournament)
    {
        return $tournament->teams()->orderBy('name')->get();
    }
}
