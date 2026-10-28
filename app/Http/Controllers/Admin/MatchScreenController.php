<?php

namespace App\Http\Controllers\Admin;

use App\Events\MatchStateUpdated;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\MatchEvent;
use App\Models\TeamMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The match-day screens reachable from the Action column of the matches list:
 *
 *   Scoreboard  — scoring console: points, raid presets, players left, game part
 *   Timer       — match clock plus the per-team 30-second raid clock
 *   Main Screen — full-bleed board for a venue display or projector
 *   Live Score  — broadcast lower-third strip
 *
 * The two boards hold no state of their own; they poll state() and render what
 * the consoles have written. Every scoring action is recorded as a MatchEvent
 * carrying the prior state, which is what makes Undo exact.
 */
class MatchScreenController extends Controller
{
    /** Match clock presets, in minutes, offered on the timer console. */
    private const DEFAULT_HALF_MINUTES = 20;

    /* ── Screens ───────────────────────────────────────── */

    /** How many feed entries the console shows (and the undo stack depth it hints at). */
    private const FEED_LIMIT = 12;

    public function scoreboard(TeamMatch $match)
    {
        return view('admin.matches.scoreboard', [
            'match'  => $this->loaded($match),
            'events' => $match->events()->orderByDesc('id')->limit(self::FEED_LIMIT)->get(),
            'socket' => $this->socketConfig($match),
        ]);
    }

    public function timer(TeamMatch $match)
    {
        return view('admin.matches.timer', [
            'match'  => $this->loaded($match),
            'socket' => $this->socketConfig($match),
        ]);
    }

    public function mainScreen(TeamMatch $match)
    {
        return view('admin.matches.screen', [
            'match'  => $this->loaded($match),
            'socket' => $this->socketConfig($match),
        ]);
    }

    public function liveScore(TeamMatch $match)
    {
        return view('admin.matches.live', [
            'match'  => $this->loaded($match),
            'socket' => $this->socketConfig($match),
        ]);
    }

    /**
     * What the browser needs to subscribe to this match's channel, or null when
     * broadcasting is switched off — in which case the screens silently fall
     * back to the HTTP reconcile and nothing breaks.
     *
     * Only the app KEY goes to the browser. That value is a public client
     * identifier by design (same role as a Pusher app key); the app SECRET is
     * server-side only and must never be rendered into a page.
     */
    private function socketConfig(TeamMatch $match): ?array
    {
        $reverb = config('broadcasting.connections.reverb');

        if (config('broadcasting.default') !== 'reverb' || empty($reverb['key'])) {
            return null;
        }

        return [
            'matchId' => $match->id,
            'key'     => $reverb['key'],
            'host'    => $reverb['options']['host'],
            'port'    => (int) $reverb['options']['port'],
            'scheme'  => $reverb['options']['scheme'],
        ];
    }

    /**
     * Polling endpoint shared by every screen.
     *
     * The boards only need the scoreline, so the feed is opt-in via ?feed=1 —
     * that keeps the 2-second poll from a venue display down to a single query.
     */
    public function state(Request $request, TeamMatch $match)
    {
        $match = $this->loaded($match);
        $payload = $match->toScreenPayload();

        if ($request->boolean('feed')) {
            // can_undo is derivable from the feed — any recorded event can be
            // rewound — so this stays one query rather than two on a hot path.
            $payload['feed'] = $this->feed($match);
            $payload['can_undo'] = $payload['feed'] !== [];
        }

        return response()->json($payload);
    }

    /* ── Scoring ───────────────────────────────────────── */

    /**
     * The − / + buttons beside each score. Takes a signed delta rather than an
     * absolute value, so two operators clicking at once can't overwrite each
     * other's points.
     */
    public function updateScore(Request $request, TeamMatch $match)
    {
        $data = $request->validate([
            'team'  => ['required', Rule::in(['1', '2'])],
            'delta' => 'required|integer|min:-10|max:10',
        ]);

        $side = (int) $data['team'];
        $delta = (int) $data['delta'];

        $this->applyWithEvent($match, 'point', $side, $delta, function (TeamMatch $m) use ($side, $delta) {
            $this->addPoints($m, $side, $delta);
        }, ($delta >= 0 ? '+' : '') . $delta . ' to ' . $this->sideName($match, $side));

        return $this->respond($match, 'Score updated.');
    }

    /** Adjust a score by an arbitrary amount — the MINUS / PLUS inputs. */
    public function adjustScore(Request $request, TeamMatch $match)
    {
        $data = $request->validate([
            'team'      => ['required', Rule::in(['1', '2'])],
            'direction' => ['required', Rule::in(['plus', 'minus'])],
            'amount'    => 'required|integer|min:1|max:99',
        ]);

        $side = (int) $data['team'];
        $delta = $data['direction'] === 'minus' ? -(int) $data['amount'] : (int) $data['amount'];

        $this->applyWithEvent($match, 'adjust', $side, $delta, function (TeamMatch $m) use ($side, $delta) {
            $this->addPoints($m, $side, $delta);
        }, 'Adjusted ' . $this->sideName($match, $side) . ' by ' . ($delta >= 0 ? '+' : '') . $delta);

        return $this->respond($match, 'Score adjusted.');
    }

    /**
     * Raid presets. Safe/super/do-or-die raids credit the raiding side; an all
     * out credits the OPPOSING side two points and puts both teams back to full
     * strength, which is how a Kabaddi all out actually resolves.
     */
    public function recordEvent(Request $request, TeamMatch $match)
    {
        $data = $request->validate([
            'team' => ['required', Rule::in(['1', '2'])],
            'type' => ['required', Rule::in(array_keys(MatchEvent::PRESETS))],
        ]);

        $side = (int) $data['team'];
        $type = $data['type'];
        $points = MatchEvent::PRESETS[$type]['points'];
        $label = MatchEvent::PRESETS[$type]['label'];

        // An all out is recorded against the side that was wiped out, but the
        // points go the other way.
        $scoringSide = $type === 'all_out' ? ($side === 1 ? 2 : 1) : $side;

        if ($points === 0) {
            // A marker such as Do or Die: feed entry and on-air sting only, no
            // points, so the description must not read "+0 to ...".
            $description = $label . ' raid — ' . $this->sideName($match, $side);
        } elseif ($type === 'all_out') {
            $description = $this->sideName($match, $side) . ' all out — +' . $points . ' to ' . $this->sideName($match, $scoringSide);
        } else {
            $description = $label . ' — +' . $points . ' to ' . $this->sideName($match, $side);
        }

        $this->applyWithEvent($match, $type, $side, $points, function (TeamMatch $m) use ($type, $scoringSide, $points) {
            $this->addPoints($m, $scoringSide, $points);

            if ($type === 'all_out') {
                $m->players_left_1 = TeamMatch::FULL_SQUAD;
                $m->players_left_2 = TeamMatch::FULL_SQUAD;
            }
        }, $description);

        return $this->respond($match, $label . ' recorded.');
    }

    /** The 1–7 players-left selector under each team. */
    public function updatePlayers(Request $request, TeamMatch $match)
    {
        $data = $request->validate([
            'team'  => ['required', Rule::in(['1', '2'])],
            'count' => 'required|integer|min:0|max:' . TeamMatch::FULL_SQUAD,
        ]);

        $side = (int) $data['team'];
        $count = (int) $data['count'];

        $this->applyWithEvent($match, 'players_left', $side, 0, function (TeamMatch $m) use ($side, $count) {
            $m->{$side === 1 ? 'players_left_1' : 'players_left_2'} = $count;
        }, $this->sideName($match, $side) . ' down to ' . $count . ' on the mat');

        return $this->respond($match, 'Players left updated.');
    }

    /** The free-text game part / half label shown on every screen. */
    public function updateGamePart(Request $request, TeamMatch $match)
    {
        $data = $request->validate(['period' => 'nullable|string|max:30']);

        $this->applyWithEvent($match, 'game_part', null, 0, function (TeamMatch $m) use ($data) {
            $m->period = $data['period'] ?: null;
        }, 'Game part set to "' . ($data['period'] ?: '—') . '"');

        return $this->respond($match, 'Game part updated.');
    }

    /** Flip which end each team is shown on, for the half-time change of ends. */
    public function swapCourts(TeamMatch $match)
    {
        $this->applyWithEvent($match, 'swap_courts', null, 0, function (TeamMatch $m) {
            $m->courts_swapped = ! $m->courts_swapped;
        }, 'Swapped courts');

        return $this->respond($match, 'Courts swapped.');
    }

    public function updateStatus(Request $request, TeamMatch $match)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(TeamMatch::STATUSES)],
        ]);

        $this->applyWithEvent($match, 'status', null, 0, function (TeamMatch $m) use ($data) {
            $m->status = $data['status'];
        }, 'Match marked ' . $data['status']);

        return $this->respond($match, 'Match marked as ' . $data['status'] . '.');
    }

    /* ── Clocks ────────────────────────────────────────── */

    /**
     * Start, stop, set or reset the match clock.
     *
     * Stopping banks the seconds from the current run into timer_elapsed_seconds
     * and clears timer_started_at; starting only records the new start time. The
     * stored state stays additive, so a reload mid-half loses nothing.
     *
     * Clock actions are deliberately not pushed onto the undo stack — they have
     * their own explicit controls, and Undo is there for scoring mistakes.
     */
    public function updateTimer(Request $request, TeamMatch $match)
    {
        $data = $request->validate([
            'action'  => ['required', Rule::in(['start', 'stop', 'set', 'set_start', 'reset'])],
            'minutes' => 'required_if:action,set,set_start|nullable|numeric|min:0.1|max:120',
        ], [
            'minutes.required_if' => 'Enter the number of minutes first.',
        ]);

        $minutes = $data['minutes'] ?? null;

        switch ($data['action']) {
            case 'start':
                if (! $match->timer_running && $match->remaining_seconds > 0) {
                    $match->timer_started_at = now();
                }
                break;

            case 'stop':
                $this->bankClock($match);
                break;

            case 'set':
            case 'set_start':
                $match->timer_duration_seconds = (int) round($minutes * 60);
                $match->timer_elapsed_seconds = 0;
                $match->timer_started_at = $data['action'] === 'set_start' ? now() : null;
                break;

            case 'reset':
                $match->timer_duration_seconds = self::DEFAULT_HALF_MINUTES * 60;
                $match->timer_elapsed_seconds = 0;
                $match->timer_started_at = null;
                break;
        }

        $match->save();

        ActivityLog::record('match.timer', $match, ucfirst(str_replace('_', ' ', $data['action'])) . ' clock · ' . $this->loaded($match)->title);

        return $this->respond($match, 'Clock updated.');
    }

    /** Raid In / Raid Out — the 30-second raid clock for one side. */
    public function updateRaid(Request $request, TeamMatch $match)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['in', 'out'])],
            'team'   => ['required_if:action,in', 'nullable', Rule::in(['1', '2'])],
        ]);

        if ($data['action'] === 'in') {
            $match->raid_team = (int) $data['team'];
            $match->raid_started_at = now();
        } else {
            $match->raid_team = null;
            $match->raid_started_at = null;
        }

        $match->save();

        return $this->respond($match, $data['action'] === 'in' ? 'Raid started.' : 'Raid ended.');
    }

    /* ── Undo & reset ──────────────────────────────────── */

    /** Restore the state captured before the most recent scoring action. */
    public function undo(TeamMatch $match)
    {
        $event = $match->events()->orderByDesc('id')->first();

        if (! $event) {
            return $this->respond($match, 'Nothing left to undo.', false);
        }

        DB::transaction(function () use ($match, $event) {
            $match->fill($event->snapshot)->save();
            $event->delete();
        });

        ActivityLog::record('match.undo', $match, 'Undid "' . $event->description . '"');

        return $this->respond($match, 'Undid: ' . $event->description);
    }

    /**
     * Back to a fresh match: scores and squads reset, clocks cleared, feed wiped.
     * Not undoable, hence the confirm on the button.
     */
    public function resetMatch(TeamMatch $match)
    {
        DB::transaction(function () use ($match) {
            $match->update([
                'team_1_score'           => 0,
                'team_2_score'           => 0,
                'players_left_1'         => TeamMatch::FULL_SQUAD,
                'players_left_2'         => TeamMatch::FULL_SQUAD,
                'timer_started_at'       => null,
                'timer_elapsed_seconds'  => 0,
                'timer_duration_seconds' => self::DEFAULT_HALF_MINUTES * 60,
                'raid_team'              => null,
                'raid_started_at'        => null,
                'courts_swapped'         => false,
            ]);

            $match->events()->delete();
        });

        ActivityLog::record('match.reset', $match, 'Reset match ' . $this->loaded($match)->title);

        return $this->respond($match, 'Match reset.');
    }

    /* ── Internals ─────────────────────────────────────── */

    /**
     * Run a state change inside a transaction, recording the prior state as a
     * MatchEvent first so Undo has something exact to roll back to.
     */
    private function applyWithEvent(
        TeamMatch $match,
        string $type,
        ?int $team,
        int $points,
        callable $mutate,
        string $description,
    ): void {
        DB::transaction(function () use ($match, $type, $team, $points, $mutate, $description) {
            MatchEvent::create([
                'team_match_id' => $match->id,
                'user_id'       => Auth::id(),
                'type'          => $type,
                'team'          => $team,
                'points'        => $points,
                'description'   => $description,
                'snapshot'      => $match->scoreState(),
                'created_at'    => now(),
            ]);

            $mutate($match);
            $match->save();
        });
    }

    /** Add (or subtract) points on a side, clamped to the column's range. */
    private function addPoints(TeamMatch $match, int $side, int $delta): void
    {
        $column = $side === 1 ? 'team_1_score' : 'team_2_score';
        $match->{$column} = max(0, min(9999, $match->{$column} + $delta));
    }

    /** Move the current run's seconds into the banked total and stop the clock. */
    private function bankClock(TeamMatch $match): void
    {
        if ($match->timer_running) {
            $match->timer_elapsed_seconds = $match->elapsed_seconds;
            $match->timer_started_at = null;
        }
    }

    private function sideName(TeamMatch $match, int $side): string
    {
        return $this->loaded($match)->sideData($side)['name'];
    }

    /** Both sides are needed by every screen — load them once, here. */
    private function loaded(TeamMatch $match): TeamMatch
    {
        return $match->load(['team1', 'team2']);
    }

    /**
     * Answer a console action.
     *
     * The consoles post over fetch and repaint in place, so for a JSON request
     * we hand back the fresh state and feed rather than a redirect — that is
     * what stops the page reloading on every point. A normal form post still
     * gets the redirect, so the controls keep working without JavaScript.
     */
    private function respond(TeamMatch $match, string $message, bool $ok = true)
    {
        $fresh = $this->loaded($match->fresh());
        $state = $fresh->toScreenPayload();

        if ($ok) {
            $this->push($fresh, $state);
        }

        if (request()->wantsJson()) {
            $feed = $this->feed($fresh);

            return response()->json([
                'ok'       => $ok,
                'message'  => $message,
                'state'    => $state,
                'feed'     => $feed,
                'can_undo' => $feed !== [],
            ]);
        }

        return back()->with($ok ? 'success' : 'error', $message);
    }

    /**
     * Push the new state to every open screen over WebSockets.
     *
     * Deliberately best-effort: a scoreboard operator must never lose a point
     * because the WebSocket server is down or restarting. If the push fails we
     * report it and carry on — the screens still have their HTTP reconcile poll
     * as a fallback, and the operator's own console already has this state in
     * the response body.
     */
    private function push(TeamMatch $match, array $state): void
    {
        try {
            MatchStateUpdated::dispatch($match->id, $state);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** The newest entries in the match feed, shaped for the console. */
    private function feed(TeamMatch $match): array
    {
        return $match->events()
            ->orderByDesc('id')
            ->limit(self::FEED_LIMIT)
            ->get()
            ->map(fn (MatchEvent $e) => [
                'description' => $e->description,
                'team'        => $e->team,
                'time'        => $e->created_at?->format('H:i'),
            ])
            ->all();
    }
}
