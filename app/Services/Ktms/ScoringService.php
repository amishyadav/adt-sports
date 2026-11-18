<?php

namespace App\Services\Ktms;

use App\Models\Ktms\DefensiveAction;
use App\Models\Ktms\GameMatch;
use App\Models\Ktms\MatchEvent;
use App\Models\Ktms\Player;
use App\Models\Ktms\Raid;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applies scoring actions to a live match.
 *
 * Two rules govern everything in here:
 *
 *  1. The integer columns home_players_on_court / away_players_on_court are the
 *     authoritative court count, because that is what the scoreboard shows. The
 *     ktms_match_player pivot is kept in step when the operator names the
 *     players involved, so the display can also say *who* is out.
 *  2. Player aggregates are never incremented by hand. After each action the
 *     affected players are recalculated from the raid and defensive-action
 *     rows, which keeps undo and later corrections exact.
 */
class ScoringService
{
    /** A defending side reduced to this many players earns 2 points for a tackle. */
    public const SUPER_TACKLE_THRESHOLD = 3;

    /** Points handed to the opposition when a side is wiped out. */
    public const ALL_OUT_POINTS = 2;

    /** A raid worth this many points or more is a super raid. */
    public const SUPER_RAID_POINTS = 3;

    public function __construct(
        protected MatchService $matches,
        protected StatisticsService $statistics,
    ) {}

    /* ─── Manual score adjustment ──────────────────────────── */

    public function adjustScore(GameMatch $match, int $teamId, int $delta): GameMatch
    {
        $this->assertTeamInMatch($match, $teamId);

        return DB::transaction(function () use ($match, $teamId, $delta) {
            $snapshot = $this->snapshot($match);

            $match->addScore($teamId, $delta);
            $match->save();

            $team = $this->teamOf($match, $teamId);

            $this->matches->logEvent(
                $match,
                MatchEvent::TYPE_SCORE_ADJUSTMENT,
                sprintf('%s score adjusted by %+d (now %d)', $team?->name, $delta, $match->scoreFor($teamId)),
                [
                    'team_id' => $teamId,
                    'points' => $delta,
                    'payload' => ['snapshot' => $snapshot],
                ],
            );

            return $match;
        });
    }

    /* ─── Raid lifecycle ───────────────────────────────────── */

    /** Flag a raid as under way (or not) and point the raid clock at the raiding side. */
    public function toggleRaid(GameMatch $match, ?bool $active = null, ?int $raidingTeamId = null): GameMatch
    {
        $active ??= ! $match->is_raid_active;

        if ($raidingTeamId !== null) {
            $this->assertTeamInMatch($match, $raidingTeamId);
        }

        return DB::transaction(function () use ($match, $active, $raidingTeamId) {
            $snapshot = $this->snapshot($match);

            $match->is_raid_active = $active;

            if ($active) {
                // Fall back to whichever side raided last, then the home side.
                $match->raiding_team_id = $raidingTeamId ?? $match->raiding_team_id ?? $match->home_team_id;

                // Each raid gets a clean clock, running from now.
                $match->raid_clock_remaining_seconds = $match->raid_duration_seconds;
                $match->raid_timer_running = true;
                $match->raid_timer_updated_at = now();
            } else {
                $match->freezeRaidClock();
            }

            $match->save();

            $raidingTeam = $this->teamOf($match, (int) $match->raiding_team_id);

            $this->matches->logEvent(
                $match,
                MatchEvent::TYPE_RAID_TOGGLED,
                $active
                    ? sprintf('Raid under way for %s', $raidingTeam?->name ?? 'unknown side')
                    : 'Raid stopped',
                [
                    'team_id' => $match->raiding_team_id,
                    'payload' => ['snapshot' => $snapshot, 'active' => $active],
                ],
            );

            return $match;
        });
    }

    /**
     * Record a completed raid, apply every consequence and refresh the aggregates.
     *
     * @param  array<string, mixed>  $data
     * @return array{match: GameMatch, raid: Raid, messages: array<int, string>}
     */
    public function recordRaid(GameMatch $match, array $data): array
    {
        $raider = Player::with('team')->findOrFail($data['raider_id']);

        // The raiding side is derived from the raider so the two can never disagree.
        $raidingTeamId = (int) $raider->team_id;
        $this->assertTeamInMatch($match, $raidingTeamId, 'The selected raider does not belong to either side in this match.');
        $defendingTeamId = (int) $match->opponentIdOf($raidingTeamId);

        $result = $data['result'];

        return DB::transaction(function () use ($match, $data, $raider, $raidingTeamId, $defendingTeamId, $result) {
            $snapshot = $this->snapshot($match);
            $messages = [];

            $defendersOnCourt = $match->playersOnCourtFor($defendingTeamId);

            // ── Work out the points ─────────────────────────────
            $bonusPoints = ! empty($data['is_bonus']) ? 1 : 0;
            $touchPoints = 0;
            $defendingPoints = 0;
            $isSuperTackle = false;

            if ($result === Raid::RESULT_SUCCESSFUL) {
                $touchPoints = max(0, (int) ($data['touch_points'] ?? 0));
            } elseif ($result === Raid::RESULT_UNSUCCESSFUL) {
                // A depleted defence earns double for stopping the raider.
                $isSuperTackle = $defendersOnCourt > 0 && $defendersOnCourt <= self::SUPER_TACKLE_THRESHOLD;
                $defendingPoints = $isSuperTackle ? 2 : 1;
            } else {
                $bonusPoints = 0; // an empty raid scores nothing at all
            }

            $raidPoints = $touchPoints + $bonusPoints;

            if ($result === Raid::RESULT_SUCCESSFUL && $raidPoints < 1) {
                throw ValidationException::withMessages([
                    'touch_points' => 'A successful raid needs at least one touch or a bonus point.',
                ]);
            }

            $isDoOrDie = array_key_exists('is_do_or_die', $data) && $data['is_do_or_die'] !== null
                ? (bool) $data['is_do_or_die']
                : $this->isDoOrDieRaid($match, $raidingTeamId);

            // ── Persist the raid ────────────────────────────────
            $raidNumber = (int) $match->raid_count + 1;

            $raid = $match->raids()->create([
                'raider_id' => $raider->id,
                'raiding_team_id' => $raidingTeamId,
                'defending_team_id' => $defendingTeamId,
                'raid_number' => $raidNumber,
                'result' => $result,
                'touch_points' => $touchPoints,
                'bonus_points' => $bonusPoints,
                'raid_points' => $raidPoints,
                'defending_points' => $defendingPoints,
                'is_bonus' => $bonusPoints > 0,
                'is_super_raid' => $raidPoints >= self::SUPER_RAID_POINTS,
                'is_do_or_die' => $isDoOrDie,
                'half' => $match->current_half,
                'game_clock_at_event' => $match->effectiveGameClockRemaining(),
                'raid_duration_seconds' => max(
                    0,
                    $match->raid_duration_seconds - $match->effectiveRaidClockRemaining()
                ),
                'notes' => $data['notes'] ?? null,
            ]);

            // ── Defensive actions tied to this raid ─────────────
            $tacklerIds = array_values(array_filter((array) ($data['tackler_ids'] ?? [])));
            $beatenIds = array_values(array_filter((array) ($data['beaten_defender_ids'] ?? [])));

            if ($result === Raid::RESULT_UNSUCCESSFUL && $tacklerIds !== []) {
                // Split the tackle points across everyone in the chain.
                $share = intdiv($defendingPoints, count($tacklerIds));
                $remainder = $defendingPoints % count($tacklerIds);

                foreach ($tacklerIds as $index => $tacklerId) {
                    DefensiveAction::create([
                        'match_id' => $match->id,
                        'raid_id' => $raid->id,
                        'defender_id' => $tacklerId,
                        'team_id' => $defendingTeamId,
                        'action_type' => $isSuperTackle
                            ? DefensiveAction::TYPE_SUPER_TACKLE
                            : ($data['action_type'] ?? DefensiveAction::TYPE_TACKLE),
                        'is_successful' => true,
                        'points_awarded' => $share + ($index < $remainder ? 1 : 0),
                        'half' => $match->current_half,
                        'game_clock_at_event' => $match->effectiveGameClockRemaining(),
                    ]);
                }
            }

            // Defenders who tried and were beaten count as failed defences.
            foreach ($beatenIds as $beatenId) {
                DefensiveAction::create([
                    'match_id' => $match->id,
                    'raid_id' => $raid->id,
                    'defender_id' => $beatenId,
                    'team_id' => $defendingTeamId,
                    'action_type' => $data['action_type'] ?? DefensiveAction::TYPE_TACKLE,
                    'is_successful' => false,
                    'points_awarded' => 0,
                    'half' => $match->current_half,
                    'game_clock_at_event' => $match->effectiveGameClockRemaining(),
                ]);
            }

            // ── Scores ─────────────────────────────────────────
            if ($raidPoints > 0) {
                $match->addScore($raidingTeamId, $raidPoints);
            }

            if ($defendingPoints > 0) {
                $match->addScore($defendingTeamId, $defendingPoints);
            }

            // ── Who is left standing ───────────────────────────
            if ($result === Raid::RESULT_SUCCESSFUL && $touchPoints > 0) {
                $outIds = array_filter((array) ($data['defender_out_ids'] ?? []));
                $this->sendPlayersOut($match, $defendingTeamId, $outIds, $touchPoints);
            }

            if ($result === Raid::RESULT_UNSUCCESSFUL) {
                // The raider is out.
                $this->sendPlayersOut($match, $raidingTeamId, [$raider->id], 1);
            }

            $match->save();

            // ── All out ────────────────────────────────────────
            foreach ([$defendingTeamId, $raidingTeamId] as $teamId) {
                if ($match->playersOnCourtFor($teamId) === 0) {
                    $messages[] = $this->applyAllOut($match, $teamId);
                }
            }

            // ── Hand the raid over to the other side ───────────
            $match->is_raid_active = false;
            $match->raid_count = $raidNumber;
            $match->raiding_team_id = $defendingTeamId;
            $match->raid_clock_remaining_seconds = $match->raid_duration_seconds;
            $match->raid_timer_running = false;
            $match->raid_timer_updated_at = now();
            $match->save();

            // ── Timeline ───────────────────────────────────────
            $this->matches->logEvent(
                $match,
                MatchEvent::TYPE_RAID,
                $this->describeRaid($raid, $raider, $isSuperTackle),
                [
                    'team_id' => $raidingTeamId,
                    'player_id' => $raider->id,
                    'raid_id' => $raid->id,
                    'points' => $raidPoints > 0 ? $raidPoints : $defendingPoints,
                    'payload' => [
                        'snapshot' => $snapshot,
                        'raid_id' => $raid->id,
                        'result' => $result,
                    ],
                ],
            );

            // ── Aggregates ─────────────────────────────────────
            $this->statistics->recalculatePlayers(
                [$raider->id, ...$tacklerIds, ...$beatenIds],
                (int) $match->tournament_id,
            );

            return [
                'match' => $match,
                'raid' => $raid,
                'messages' => array_values(array_filter($messages)),
            ];
        });
    }

    /**
     * Log a defensive action on its own, outside the raid form.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordDefensiveAction(GameMatch $match, array $data): array
    {
        $defender = Player::with('team')->findOrFail($data['defender_id']);
        $teamId = (int) $defender->team_id;
        $this->assertTeamInMatch($match, $teamId, 'The selected defender does not belong to either side in this match.');

        return DB::transaction(function () use ($match, $data, $defender, $teamId) {
            $snapshot = $this->snapshot($match);
            $messages = [];

            $isSuccessful = (bool) ($data['is_successful'] ?? false);
            $opponentId = $match->opponentIdOf($teamId);
            $actionType = $data['action_type'] ?? DefensiveAction::TYPE_TACKLE;

            // Successful stops are worth a point, doubled when the defence is thin.
            if (array_key_exists('points_awarded', $data) && $data['points_awarded'] !== null) {
                $points = max(0, (int) $data['points_awarded']);
            } elseif ($isSuccessful) {
                $onCourt = $match->playersOnCourtFor($teamId);
                $points = ($onCourt > 0 && $onCourt <= self::SUPER_TACKLE_THRESHOLD) ? 2 : 1;
            } else {
                $points = 0;
            }

            if ($isSuccessful && $points >= 2) {
                $actionType = DefensiveAction::TYPE_SUPER_TACKLE;
            }

            $action = DefensiveAction::create([
                'match_id' => $match->id,
                'raid_id' => $data['raid_id'] ?? null,
                'defender_id' => $defender->id,
                'team_id' => $teamId,
                'action_type' => $actionType,
                'is_successful' => $isSuccessful,
                'points_awarded' => $points,
                'half' => $match->current_half,
                'game_clock_at_event' => $match->effectiveGameClockRemaining(),
                'notes' => $data['notes'] ?? null,
            ]);

            if ($points > 0) {
                $match->addScore($teamId, $points);
            }

            // Stopping the raider puts the raider out.
            if ($isSuccessful && ! empty($data['send_raider_out']) && $opponentId) {
                $this->sendPlayersOut($match, $opponentId, array_filter([$data['raider_id'] ?? null]), 1);
            }

            $match->save();

            if ($opponentId && $match->playersOnCourtFor($opponentId) === 0) {
                $messages[] = $this->applyAllOut($match, $opponentId);
                $match->save();
            }

            $this->matches->logEvent(
                $match,
                MatchEvent::TYPE_DEFENSE,
                sprintf(
                    '%s %s (%s)%s',
                    $defender->display_name,
                    $isSuccessful ? 'stops the raider' : 'fails to stop the raider',
                    $action->action_type_label,
                    $points > 0 ? " +{$points}" : '',
                ),
                [
                    'team_id' => $teamId,
                    'player_id' => $defender->id,
                    'raid_id' => $action->raid_id,
                    'points' => $points,
                    'payload' => [
                        'snapshot' => $snapshot,
                        'defensive_action_id' => $action->id,
                    ],
                ],
            );

            $this->statistics->recalculatePlayers([$defender->id], (int) $match->tournament_id);

            return [
                'match' => $match,
                'action' => $action,
                'messages' => array_values(array_filter($messages)),
            ];
        });
    }

    /* ─── Court management ─────────────────────────────────── */

    public function adjustPlayersOnCourt(GameMatch $match, int $teamId, int $delta): GameMatch
    {
        $this->assertTeamInMatch($match, $teamId);

        return DB::transaction(function () use ($match, $teamId, $delta) {
            $snapshot = $this->snapshot($match);

            $match->adjustPlayersOnCourt($teamId, $delta);
            $match->save();

            $team = $this->teamOf($match, $teamId);

            $this->matches->logEvent(
                $match,
                MatchEvent::TYPE_COURT_UPDATE,
                sprintf('%s now have %d on the mat', $team?->name, $match->playersOnCourtFor($teamId)),
                ['team_id' => $teamId, 'payload' => ['snapshot' => $snapshot]],
            );

            return $match;
        });
    }

    /*
     * Setting the court count to an absolute number was the timer console's job
     * and went with it. The scoring console adjusts by ±1 and can revive a whole
     * side, which covers the same ground.
     */

    /** Bring a whole side back to full strength. */
    public function reviveTeam(GameMatch $match, int $teamId): GameMatch
    {
        $this->assertTeamInMatch($match, $teamId);

        return DB::transaction(function () use ($match, $teamId) {
            $snapshot = $this->snapshot($match);

            $this->matches->reviveTeam($match, $teamId);
            $match->save();

            $team = $this->teamOf($match, $teamId);

            $this->matches->logEvent(
                $match,
                MatchEvent::TYPE_COURT_UPDATE,
                sprintf('%s revived to full strength', $team?->name),
                ['team_id' => $teamId, 'payload' => ['snapshot' => $snapshot]],
            );

            return $match;
        });
    }

    /* ─── Undo ─────────────────────────────────────────────── */

    /**
     * Roll back the most recent scoring action.
     *
     * Every action stores a snapshot of the match counters before it ran, so
     * undoing is a matter of restoring that snapshot, dropping any rows the
     * action created and rebuilding the affected aggregates.
     */
    public function undoLastAction(GameMatch $match): array
    {
        $event = $match->events()
            ->where('is_undone', false)
            ->whereIn('event_type', [
                MatchEvent::TYPE_RAID,
                MatchEvent::TYPE_DEFENSE,
                MatchEvent::TYPE_SCORE_ADJUSTMENT,
                MatchEvent::TYPE_COURT_UPDATE,
                MatchEvent::TYPE_ALL_OUT,
                MatchEvent::TYPE_RAID_TOGGLED,
                // The officiating actions. They store the same counter snapshot
                // plus the pivot state of the players they moved, so one code
                // path reverses both kinds.
                MatchEvent::TYPE_SUBSTITUTION,
                MatchEvent::TYPE_INJURY,
                MatchEvent::TYPE_WARNING,
                MatchEvent::TYPE_CARD_GREEN,
                MatchEvent::TYPE_CARD_YELLOW,
                MatchEvent::TYPE_CARD_RED,
                MatchEvent::TYPE_TECHNICAL_POINT,
            ])
            ->orderByDesc('id')
            ->first();

        if (! $event) {
            return ['match' => $match, 'undone' => false, 'message' => 'There is nothing left to undo.'];
        }

        return DB::transaction(function () use ($match, $event) {
            $payload = $event->payload ?? [];
            $snapshot = $payload['snapshot'] ?? null;
            $affectedPlayers = [];

            // Remove whatever the action created.
            if ($raidId = $payload['raid_id'] ?? null) {
                $raid = Raid::with('defensiveActions')->find($raidId);

                if ($raid) {
                    $affectedPlayers[] = $raid->raider_id;
                    $affectedPlayers = [
                        ...$affectedPlayers,
                        ...$raid->defensiveActions->pluck('defender_id')->all(),
                    ];
                    $raid->defensiveActions()->delete();
                    $raid->delete();
                }
            }

            if ($actionId = $payload['defensive_action_id'] ?? null) {
                $action = DefensiveAction::find($actionId);

                if ($action) {
                    $affectedPlayers[] = $action->defender_id;
                    $action->delete();
                }
            }

            // Restore the counters captured before the action ran.
            if (is_array($snapshot)) {
                $match->forceFill([
                    'home_score' => $snapshot['home_score'] ?? $match->home_score,
                    'away_score' => $snapshot['away_score'] ?? $match->away_score,
                    'home_players_on_court' => $snapshot['home_players_on_court'] ?? $match->home_players_on_court,
                    'away_players_on_court' => $snapshot['away_players_on_court'] ?? $match->away_players_on_court,
                    'raid_count' => $snapshot['raid_count'] ?? $match->raid_count,
                    'raiding_team_id' => $snapshot['raiding_team_id'] ?? $match->raiding_team_id,
                    'is_raid_active' => $snapshot['is_raid_active'] ?? $match->is_raid_active,
                ]);
            }

            /*
             * Put the named players back where they were.
             *
             * The counter snapshot above restores how many are standing; this
             * restores which ones. Without it an undone substitution would give
             * the right headcount with the wrong two players swapped.
             */
            if (is_array($payload['lineup'] ?? null)) {
                $this->matches->restoreLineup($match, $payload['lineup']);
            }

            $match->save();

            // Mark this event and anything it triggered (such as an all out) as undone.
            $match->events()
                ->where('id', '>=', $event->id)
                ->update(['is_undone' => true]);

            $this->statistics->recalculatePlayers($affectedPlayers, (int) $match->tournament_id);

            return [
                'match' => $match,
                'undone' => true,
                'message' => 'Undone: '.$event->description,
            ];
        });
    }

    /* ─── Internals ────────────────────────────────────────── */

    /** Two consecutive empty raids by a side make the next one do-or-die. */
    public function isDoOrDieRaid(GameMatch $match, int $raidingTeamId): bool
    {
        $recent = $match->raids()
            ->where('raiding_team_id', $raidingTeamId)
            ->orderByDesc('raid_number')
            ->limit(2)
            ->pluck('result');

        return $recent->count() === 2
            && $recent->every(fn ($result) => $result === Raid::RESULT_EMPTY);
    }

    /**
     * Take players off the mat.
     *
     * `$count` drives the authoritative court number, because in kabaddi one
     * point always equals one player out. Naming the players is optional and
     * only feeds the pivot, so the display can show who is sitting out.
     *
     * @param  array<int, mixed>  $playerIds
     */
    protected function sendPlayersOut(GameMatch $match, int $teamId, array $playerIds, int $count): void
    {
        foreach (array_values(array_filter($playerIds)) as $playerId) {
            $match->lineup()->updateExistingPivot($playerId, [
                'is_on_court' => false,
                'status' => 'out',
            ]);
        }

        $match->adjustPlayersOnCourt($teamId, -abs($count));
    }

    /**
     * A side has been wiped out: the opposition banks two points and the
     * cleared side returns at full strength.
     */
    protected function applyAllOut(GameMatch $match, int $allOutTeamId): string
    {
        $opponentId = $match->opponentIdOf($allOutTeamId);
        $snapshot = $this->snapshot($match);

        if ($opponentId) {
            $match->addScore($opponentId, self::ALL_OUT_POINTS);
        }

        $this->matches->reviveTeam($match, $allOutTeamId);
        $match->save();

        $allOutTeam = $this->teamOf($match, $allOutTeamId);
        $opponent = $opponentId ? $this->teamOf($match, $opponentId) : null;

        $message = sprintf(
            'ALL OUT! %s cleared the mat and take %d points.',
            $opponent?->name,
            self::ALL_OUT_POINTS,
        );

        $this->matches->logEvent($match, MatchEvent::TYPE_ALL_OUT, $message, [
            'team_id' => $opponentId,
            'points' => self::ALL_OUT_POINTS,
            'payload' => ['snapshot' => $snapshot, 'all_out_team_id' => $allOutTeamId],
        ]);

        return sprintf('All out against %s.', $allOutTeam?->name);
    }

    /**
     * Counters captured before an action, used to reverse it later.
     *
     * @return array<string, mixed>
     */
    protected function snapshot(GameMatch $match): array
    {
        return [
            'home_score' => (int) $match->home_score,
            'away_score' => (int) $match->away_score,
            'home_players_on_court' => (int) $match->home_players_on_court,
            'away_players_on_court' => (int) $match->away_players_on_court,
            'raid_count' => (int) $match->raid_count,
            'raiding_team_id' => $match->raiding_team_id,
            'is_raid_active' => (bool) $match->is_raid_active,
        ];
    }

    protected function describeRaid(Raid $raid, Player $raider, bool $isSuperTackle): string
    {
        $parts = [];

        if ($raid->result === Raid::RESULT_SUCCESSFUL) {
            $detail = [];

            if ($raid->touch_points > 0) {
                $detail[] = $raid->touch_points.' touch'.($raid->touch_points > 1 ? 'es' : '');
            }

            if ($raid->bonus_points > 0) {
                $detail[] = 'bonus';
            }

            $parts[] = sprintf(
                '%s%s scores %d (%s)',
                $raid->is_super_raid ? 'SUPER RAID! ' : '',
                $raider->display_name,
                $raid->raid_points,
                implode(' + ', $detail),
            );
        } elseif ($raid->result === Raid::RESULT_UNSUCCESSFUL) {
            $parts[] = sprintf(
                '%s tackled%s (+%d defence)',
                $raider->display_name,
                $isSuperTackle ? ' with a SUPER TACKLE' : '',
                $raid->defending_points,
            );

            if ($raid->bonus_points > 0) {
                $parts[] = 'bonus taken';
            }
        } else {
            $parts[] = sprintf('%s comes back empty', $raider->display_name);
        }

        if ($raid->is_do_or_die) {
            $parts[] = 'do-or-die raid';
        }

        return implode(' — ', $parts);
    }

    /** Whichever side of this match the id refers to. */
    protected function teamOf(GameMatch $match, int $teamId)
    {
        return $match->isHomeTeam($teamId) ? $match->homeTeam : $match->awayTeam;
    }

    protected function assertTeamInMatch(GameMatch $match, int $teamId, ?string $message = null): void
    {
        if (! $match->involvesTeam($teamId)) {
            throw ValidationException::withMessages([
                'team_id' => $message ?? 'That team is not playing in this match.',
            ]);
        }
    }
}
