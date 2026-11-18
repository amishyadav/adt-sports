<?php

namespace App\Services\Ktms;

use App\Models\Ktms\GameMatch;
use App\Models\Ktms\MatchEvent;
use App\Models\Ktms\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The things that happen to a match away from the raid: substitutions,
 * injuries, warnings, cards and technical points.
 *
 * All of them follow the same shape as the scoring actions, deliberately:
 *
 *  - the court headcount stays the authoritative number, because that is what
 *    the scoreboard shows, and the lineup pivot is kept in step so the display
 *    can also say *who* is standing;
 *  - every action stores a snapshot of the counters before it ran, plus the
 *    pivot state of the players it touched, so Undo can reverse it exactly.
 *
 * Player statistics are untouched. These events are not raids or tackles, so
 * the aggregates that StatisticsService rebuilds from those rows are unaffected
 * and nothing needs recalculating.
 */
class OfficialsService
{
    public function __construct(protected MatchService $matches) {}

    /* ─── Lineup ───────────────────────────────────────────── */

    /**
     * Swap a player on the mat for one on the bench.
     *
     * The headcount does not move: one comes off as one goes on.
     */
    public function substitute(GameMatch $match, int $outPlayerId, int $inPlayerId): array
    {
        $out = $this->playerInMatch($match, $outPlayerId);
        $in = $this->playerInMatch($match, $inPlayerId);

        if ($out->team_id !== $in->team_id) {
            throw ValidationException::withMessages([
                'in_player_id' => 'A substitute has to come from the same squad.',
            ]);
        }

        if ($out->id === $in->id) {
            throw ValidationException::withMessages([
                'in_player_id' => 'Pick a different player to come on.',
            ]);
        }

        $this->assertOnMat($match, $out, 'out_player_id');
        $this->assertOnBench($match, $in, 'in_player_id');

        return DB::transaction(function () use ($match, $out, $in) {
            $before = $this->counters($match);
            $pivots = $this->matches->lineupSnapshot($match, [$out->id, $in->id]);

            $this->matches->takeOffMat($match, $out->id, 'bench');
            $this->matches->putOnMat($match, $in->id);

            $match->save();

            $this->log($match, MatchEvent::TYPE_SUBSTITUTION, (int) $out->team_id, $in->id, sprintf(
                'Substitution for %s: %s on for %s',
                $this->teamName($match, (int) $out->team_id),
                $in->display_name,
                $out->display_name,
            ), 0, $before, $pivots);

            return ['match' => $match, 'messages' => []];
        });
    }

    /**
     * Record an injury.
     *
     * With a replacement named it behaves as a substitution and the headcount
     * holds. Without one the side plays a player short, which is what actually
     * happens when there is nobody to bring on.
     */
    public function injury(GameMatch $match, int $playerId, ?int $replacementId = null): array
    {
        $player = $this->playerInMatch($match, $playerId);
        $this->assertOnMat($match, $player, 'player_id');

        $replacement = null;

        if ($replacementId) {
            $replacement = $this->playerInMatch($match, $replacementId);

            if ($replacement->team_id !== $player->team_id) {
                throw ValidationException::withMessages([
                    'replacement_id' => 'A replacement has to come from the same squad.',
                ]);
            }

            $this->assertOnBench($match, $replacement, 'replacement_id');
        }

        return DB::transaction(function () use ($match, $player, $replacement) {
            $before = $this->counters($match);
            $ids = array_filter([$player->id, $replacement?->id]);
            $pivots = $this->matches->lineupSnapshot($match, $ids);

            $this->matches->takeOffMat($match, $player->id, 'out');

            if ($replacement) {
                $this->matches->putOnMat($match, $replacement->id);
            } else {
                // Nobody to bring on, so the side is one short.
                $match->adjustPlayersOnCourt((int) $player->team_id, -1);
            }

            $match->save();

            $description = $replacement
                ? sprintf('%s injured — %s on in their place', $player->display_name, $replacement->display_name)
                : sprintf('%s injured and off the mat — %s play a player short',
                    $player->display_name, $this->teamName($match, (int) $player->team_id));

            $this->log($match, MatchEvent::TYPE_INJURY, (int) $player->team_id, $player->id,
                $description, 0, $before, $pivots);

            return ['match' => $match, 'messages' => []];
        });
    }

    /* ─── Sanctions ────────────────────────────────────────── */

    /**
     * Record a warning or a card.
     *
     * A warning and a green card go on the record only. A yellow card is a
     * suspension and a red card an expulsion, so both take the player off the
     * mat and the side plays short — a suspended player cannot be replaced.
     *
     * No point is awarded either way. Where the rules call for one it is
     * recorded separately as a technical point, so the operator decides rather
     * than the engine guessing at a competition's regulations.
     */
    public function sanction(GameMatch $match, string $type, int $playerId, ?string $note = null): array
    {
        if (! array_key_exists($type, MatchEvent::SANCTIONS)) {
            throw ValidationException::withMessages(['type' => 'That is not a sanction this console records.']);
        }

        $rule = MatchEvent::SANCTIONS[$type];
        $player = $this->playerInMatch($match, $playerId);

        if ($rule['leavesMat']) {
            $this->assertOnMat($match, $player, 'player_id');
        }

        return DB::transaction(function () use ($match, $type, $rule, $player, $note) {
            $before = $this->counters($match);
            $pivots = $this->matches->lineupSnapshot($match, [$player->id]);

            if ($rule['leavesMat']) {
                $this->matches->takeOffMat($match, $player->id, 'out', $rule['forMatch']);
                $match->adjustPlayersOnCourt((int) $player->team_id, -1);
            }

            $match->save();

            $tail = match (true) {
                $rule['forMatch'] => ' — out for the rest of the match',
                $rule['leavesMat'] => ' — suspended and off the mat',
                default => '',
            };

            $description = sprintf('%s: %s (%s)%s',
                $rule['label'],
                $player->display_name,
                $this->teamName($match, (int) $player->team_id),
                $tail,
            );

            if (filled($note)) {
                $description .= ' · '.$note;
            }

            $this->log($match, $type, (int) $player->team_id, $player->id,
                $description, 0, $before, $pivots);

            $messages = $rule['leavesMat']
                ? [$this->teamName($match, (int) $player->team_id).' are a player short.']
                : [];

            return ['match' => $match, 'messages' => $messages];
        });
    }

    /**
     * Award a technical point to a side.
     *
     * Given against the other side for an infringement, so the team chosen here
     * is the one that receives it. The headcount does not change.
     */
    public function technicalPoint(GameMatch $match, int $teamId, int $points = 1, ?string $note = null): array
    {
        if (! $match->involvesTeam($teamId)) {
            throw ValidationException::withMessages(['team_id' => 'That team is not playing in this match.']);
        }

        return DB::transaction(function () use ($match, $teamId, $points, $note) {
            $before = $this->counters($match);

            $match->addScore($teamId, $points);
            $match->save();

            $description = sprintf('Technical point: %s +%d',
                $this->teamName($match, $teamId), $points);

            if (filled($note)) {
                $description .= ' · '.$note;
            }

            $this->log($match, MatchEvent::TYPE_TECHNICAL_POINT, $teamId, null,
                $description, $points, $before, []);

            return ['match' => $match, 'messages' => []];
        });
    }

    /* ─── Internals ────────────────────────────────────────── */

    /**
     * Append the timeline entry, carrying everything Undo needs.
     *
     * @param  array<string, mixed>  $before
     * @param  array<int, array<string, mixed>>  $pivots
     */
    protected function log(
        GameMatch $match,
        string $type,
        ?int $teamId,
        ?int $playerId,
        string $description,
        int $points,
        array $before,
        array $pivots,
    ): void {
        $this->matches->logEvent($match, $type, $description, [
            'team_id' => $teamId,
            'player_id' => $playerId,
            'points' => $points,
            'payload' => [
                'snapshot' => $before,
                // Read back by ScoringService::undoLastAction to put the right
                // players back on the mat, not just the right number of them.
                'lineup' => $pivots,
            ],
        ]);
    }

    /**
     * The counters as they were, in the same shape ScoringService uses so Undo
     * can restore either kind of action through one code path.
     *
     * @return array<string, mixed>
     */
    protected function counters(GameMatch $match): array
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

    protected function playerInMatch(GameMatch $match, int $playerId): Player
    {
        $player = Player::find($playerId);

        if (! $player || ! $match->involvesTeam((int) $player->team_id)) {
            throw ValidationException::withMessages([
                'player_id' => 'That player is not in this match.',
            ]);
        }

        return $player;
    }

    protected function assertOnMat(GameMatch $match, Player $player, string $field): void
    {
        if (! $this->pivotOf($match, $player)?->is_on_court) {
            throw ValidationException::withMessages([
                $field => $player->display_name.' is not on the mat.',
            ]);
        }
    }

    protected function assertOnBench(GameMatch $match, Player $player, string $field): void
    {
        $pivot = $this->pivotOf($match, $player);

        if (! $pivot || $pivot->is_on_court) {
            throw ValidationException::withMessages([
                $field => $player->display_name.' is already on the mat.',
            ]);
        }

        /*
         * Someone tackled out is waiting to be revived, not available as a
         * substitute — bringing them on would quietly undo their dismissal.
         */
        if ($pivot->status !== 'bench') {
            throw ValidationException::withMessages([
                $field => $player->display_name.' is out of play, not on the bench. Revive the side instead.',
            ]);
        }
    }

    protected function pivotOf(GameMatch $match, Player $player)
    {
        return $match->lineup()->where('ktms_players.id', $player->id)->first()?->pivot;
    }

    protected function teamName(GameMatch $match, int $teamId): string
    {
        return ($match->isHomeTeam($teamId) ? $match->homeTeam?->name : $match->awayTeam?->name) ?? 'Unknown';
    }
}
