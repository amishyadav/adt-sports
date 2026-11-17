<?php

namespace App\Services\Ktms;

use App\Models\Ktms\GameMatch;
use App\Models\Ktms\Player;
use App\Models\Ktms\PlayerStatistic;
use App\Models\Ktms\Tournament;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the ktms_player_statistics aggregates in step with the raw raid and
 * defensive-action rows.
 *
 * Aggregates are always recomputed from the underlying records rather than
 * nudged by deltas. A recalculation touches only a handful of indexed rows, and
 * in exchange an undo or a later correction can never leave the totals
 * drifting.
 */
class StatisticsService
{
    /** Rebuild the aggregate row for one player in one tournament. */
    public function recalculatePlayer(int $playerId, int $tournamentId): PlayerStatistic
    {
        $raiding = $this->raidTotals($playerId, $tournamentId);
        $defending = $this->defenseTotals($playerId, $tournamentId);

        $statistic = PlayerStatistic::firstOrNew([
            'player_id' => $playerId,
            'tournament_id' => $tournamentId,
        ]);

        $statistic->fill([
            'matches_played' => $this->matchesPlayed($playerId, $tournamentId),

            'total_raids' => $raiding->total_raids,
            'successful_raids' => $raiding->successful_raids,
            'unsuccessful_raids' => $raiding->unsuccessful_raids,
            'empty_raids' => $raiding->empty_raids,
            'raid_points' => $raiding->raid_points,
            'touch_points' => $raiding->touch_points,
            'bonus_points' => $raiding->bonus_points,
            'super_raids' => $raiding->super_raids,
            'do_or_die_raids' => $raiding->do_or_die_raids,
            'do_or_die_conversions' => $raiding->do_or_die_conversions,

            'total_defenses' => $defending->total_defenses,
            'successful_defenses' => $defending->successful_defenses,
            'failed_defenses' => $defending->failed_defenses,
            'defense_points' => $defending->defense_points,
            'super_tackles' => $defending->super_tackles,
        ]);

        $statistic->syncTotalPoints();
        $statistic->save();

        return $statistic;
    }

    /**
     * @param  iterable<int|null>  $playerIds
     */
    public function recalculatePlayers(iterable $playerIds, int $tournamentId): void
    {
        foreach (collect($playerIds)->filter()->unique() as $playerId) {
            $this->recalculatePlayer((int) $playerId, $tournamentId);
        }
    }

    /**
     * Rebuild every aggregate in a tournament. Used by the seeder and by
     * `ktms:rebuild-stats`.
     */
    public function recalculateTournament(Tournament $tournament): int
    {
        $playerIds = Player::query()
            ->whereIn('team_id', $tournament->teams()->select('ktms_teams.id'))
            ->pluck('id');

        foreach ($playerIds as $playerId) {
            $this->recalculatePlayer((int) $playerId, $tournament->id);
        }

        return $playerIds->count();
    }

    /*
     * CASE WHEN rather than SUM(col = 'value'): the shorthand works on MySQL and
     * SQLite but is not standard SQL, and this project runs on both.
     */

    protected function raidTotals(int $playerId, int $tournamentId): object
    {
        $row = DB::table('ktms_raids')
            ->join('ktms_matches', 'ktms_matches.id', '=', 'ktms_raids.match_id')
            ->where('ktms_raids.raider_id', $playerId)
            ->where('ktms_matches.tournament_id', $tournamentId)
            ->selectRaw('COUNT(*) AS total_raids')
            ->selectRaw("COALESCE(SUM(CASE WHEN ktms_raids.result = 'successful' THEN 1 ELSE 0 END), 0) AS successful_raids")
            ->selectRaw("COALESCE(SUM(CASE WHEN ktms_raids.result = 'unsuccessful' THEN 1 ELSE 0 END), 0) AS unsuccessful_raids")
            ->selectRaw("COALESCE(SUM(CASE WHEN ktms_raids.result = 'empty' THEN 1 ELSE 0 END), 0) AS empty_raids")
            ->selectRaw('COALESCE(SUM(ktms_raids.raid_points), 0) AS raid_points')
            ->selectRaw('COALESCE(SUM(ktms_raids.touch_points), 0) AS touch_points')
            ->selectRaw('COALESCE(SUM(ktms_raids.bonus_points), 0) AS bonus_points')
            ->selectRaw('COALESCE(SUM(CASE WHEN ktms_raids.is_super_raid = 1 THEN 1 ELSE 0 END), 0) AS super_raids')
            ->selectRaw('COALESCE(SUM(CASE WHEN ktms_raids.is_do_or_die = 1 THEN 1 ELSE 0 END), 0) AS do_or_die_raids')
            ->selectRaw("COALESCE(SUM(CASE WHEN ktms_raids.is_do_or_die = 1 AND ktms_raids.result = 'successful' THEN 1 ELSE 0 END), 0) AS do_or_die_conversions")
            ->first();

        return $this->normalise($row, [
            'total_raids', 'successful_raids', 'unsuccessful_raids', 'empty_raids',
            'raid_points', 'touch_points', 'bonus_points', 'super_raids',
            'do_or_die_raids', 'do_or_die_conversions',
        ]);
    }

    protected function defenseTotals(int $playerId, int $tournamentId): object
    {
        $row = DB::table('ktms_defensive_actions')
            ->join('ktms_matches', 'ktms_matches.id', '=', 'ktms_defensive_actions.match_id')
            ->where('ktms_defensive_actions.defender_id', $playerId)
            ->where('ktms_matches.tournament_id', $tournamentId)
            ->selectRaw('COUNT(*) AS total_defenses')
            ->selectRaw('COALESCE(SUM(CASE WHEN ktms_defensive_actions.is_successful = 1 THEN 1 ELSE 0 END), 0) AS successful_defenses')
            ->selectRaw('COALESCE(SUM(CASE WHEN ktms_defensive_actions.is_successful = 0 THEN 1 ELSE 0 END), 0) AS failed_defenses')
            ->selectRaw('COALESCE(SUM(ktms_defensive_actions.points_awarded), 0) AS defense_points')
            ->selectRaw("COALESCE(SUM(CASE WHEN ktms_defensive_actions.action_type = 'super_tackle' AND ktms_defensive_actions.is_successful = 1 THEN 1 ELSE 0 END), 0) AS super_tackles")
            ->first();

        return $this->normalise($row, [
            'total_defenses', 'successful_defenses', 'failed_defenses',
            'defense_points', 'super_tackles',
        ]);
    }

    /**
     * A player counts as having played once they are named in the lineup of a
     * match that actually got under way.
     */
    protected function matchesPlayed(int $playerId, int $tournamentId): int
    {
        return (int) DB::table('ktms_match_player')
            ->join('ktms_matches', 'ktms_matches.id', '=', 'ktms_match_player.match_id')
            ->where('ktms_match_player.player_id', $playerId)
            ->where('ktms_matches.tournament_id', $tournamentId)
            ->whereIn('ktms_matches.status', GameMatch::STARTED_STATUSES)
            ->distinct()
            ->count('ktms_matches.id');
    }

    /**
     * Guarantee every expected key exists and is an integer.
     *
     * @param  array<int, string>  $keys
     */
    protected function normalise(?object $row, array $keys): object
    {
        $normalised = [];

        foreach ($keys as $key) {
            $normalised[$key] = (int) ($row->{$key} ?? 0);
        }

        return (object) $normalised;
    }
}
