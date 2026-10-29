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
     * no reachable WebSocket endpoint exists — in which case the screens fall
     * back to the HTTP reconcile and nothing breaks.
     *
     * Supports self-hosted Reverb and Pusher's hosted service, which speak the
     * same protocol. Pusher matters for shared hosting: it needs no long-running
     * process, only outbound HTTPS, so it works where `reverb:start` cannot.
     *
     * Only the app KEY goes to the browser. That value is a public client
     * identifier by design; the app SECRET is server-side only and must never
     * be rendered into a page.
     */
    private function socketConfig(TeamMatch $match): ?array
    {
        $endpoint = self::socketEndpoint();

        return $endpoint ? $endpoint + ['matchId' => $match->id] : null;
    }

    /**
     * The WebSocket endpoint the browser should dial, shared with the CSP so the
     * header and the client can never disagree about what is allowed.
     *
     * @return array{key: string, host: string, port: int, scheme: string}|null
     */
    public static function socketEndpoint(): ?array
    {
        $driver = config('broadcasting.default');

        if ($driver === 'pusher') {
            $pusher = config('broadcasting.connections.pusher');
            $cluster = $pusher['options']['cluster'] ?? null;

            if (empty($pusher['key']) || empty($cluster)) {
                return null;
            }

            return [
                'key'    => $pusher['key'],
                'host'   => 'ws-' . $cluster . '.pusher.com',
                'port'   => 443,
                'scheme' => 'https',
            ];
        }

        if ($driver !== 'reverb') {
            return null;
        }

        $reverb = config('broadcasting.connections.reverb');
        if (empty($reverb['key'])) {
            return null;
        }

        $host = (string) ($reverb['options']['host'] ?? '');

        /*
         * A loopback Reverb host only works when the browser is on the same
         * machine as the server. On shared hosting it never can be — and the
         * plan cannot run `reverb:start` anyway — so advertising it would leave
         * every screen retrying a socket that can never open, and the console
         * telling the operator to run a command they have no shell for.
         * Report no endpoint instead and let the HTTP reconcile do the work.
         */
        $loopback = ['127.0.0.1', 'localhost', '0.0.0.0', '::1'];

        if (in_array($host, $loopback, true)
            && ! in_array(request()?->getHost(), ['127.0.0.1', 'localhost', '::1'], true)) {
            return null;
        }

        return [
            'key'    => $reverb['key'],
            'host'   => $host,
            'port'   => (int) ($reverb['options']['port'] ?? 8080),
            'scheme' => (string) ($reverb['options']['scheme'] ?? 'http'),
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

        $payload['server_ms'] = $this->serverMs();

        /*
         * Never let anything cache this.
         *
         * It is a live clock: a copy even a few seconds old makes the boards
         * disagree with the operator's console. Laravel's own response cache
         * already skips authenticated admin routes, but shared hosting often
         * sits behind a server-level cache (LiteSpeed/LSCache on Hostinger, for
         * instance) that will happily cache a 200 JSON GET on its own. These
         * headers opt out of all of them, and the client adds a cache-busting
         * parameter as well for proxies that ignore headers.
         */
        return response()
            ->json($payload)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0')
            ->header('X-LiteSpeed-Cache-Control', 'no-cache')
            ->header('X-Accel-Expires', '0')
            ->header('X-Server-Ms', (string) $payload['server_ms']);
    }

    /**
     * Milliseconds this request spent inside the application.
     *
     * The browser can only see the total round trip, which on a server that
     * answers one request at a time is mostly spent waiting in line behind the
     * other screens. Reporting the application's own share separates "the
     * server is slow" from "the request queued", which have different fixes.
     */
    private function serverMs(): ?int
    {
        return defined('LARAVEL_START')
            ? (int) round((microtime(true) - LARAVEL_START) * 1000)
            : null;
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

    /**
     * Both sides are needed by every screen — load them once, here.
     *
     * loadMissing, not load: load() re-queries both teams every time it is
     * called, and a single scoring action calls this three times (once to name
     * the side in the feed description, then again when composing the reply).
     * That was four queries for two rows that cannot change mid-request.
     */
    private function loaded(TeamMatch $match): TeamMatch
    {
        return $match->loadMissing(['team1', 'team2']);
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
        /*
         * Re-read the match row so the reply reflects anything a second console
         * committed while this request was in flight — but carry the teams over
         * rather than fetching them again. fresh() returns a bare instance, so
         * the two team rows were being selected a second time for names and
         * crests that cannot have changed since the top of this request.
         */
        $fresh = $match->fresh();
        $fresh->setRelations($match->getRelations());

        $state = $this->loaded($fresh)->toScreenPayload();

        if ($ok) {
//            $this->push($fresh, $state);
        }

        if (request()->wantsJson()) {
            $feed = $this->feed($fresh);
            $serverMs = $this->serverMs();

            return response()->json([
                'ok'       => $ok,
                'message'  => $message,
                // Timing rides on the state copy the console applies, not on
                // the broadcast payload, which every screen shares.
                'state'    => $state + ['server_ms' => $serverMs],
                'feed'     => $feed,
                'can_undo' => $feed !== [],
            ])->header('X-Server-Ms', (string) $serverMs);
        }

        return back()->with($ok ? 'success' : 'error', $message);
    }

    /**
     * Push the new state to every open screen over WebSockets.
     *
     * Skipped entirely when no reachable endpoint exists. The only consumers of
     * a broadcast are browsers, so if they could never open the socket there is
     * nothing to push to — and attempting it anyway would add a connect timeout
     * plus a logged exception to every single scoring click, which on shared
     * hosting quietly fills the log with noise that hides real errors.
     *
     * Otherwise best-effort: an operator must never lose a point because the
     * WebSocket server is restarting. The screens still have the HTTP reconcile,
     * and the console already has this state in the response body.
     */
    private function push(TeamMatch $match, array $state): void
    {
        if (! self::socketEndpoint()) {
            return;
        }

        $matchId = $match->id;

        /*
         * Deferred until the response has been sent.
         *
         * Publishing is an outbound HTTPS call, and every web request is a
         * fresh process, so each one pays its own TLS handshake: measured at
         * 283–369 ms to Pusher's ap2 cluster. Inline, that was added to every
         * single tap the operator made.
         *
         * Running it from a terminating callback costs the screens nothing —
         * the frame goes out just as soon — while the console stops waiting for
         * it. Response::send() calls fastcgi_finish_request() where it exists,
         * so under PHP-FPM the browser is released before this runs at all.
         *
         * Still best effort: an operator must never lose a point because the
         * broadcast failed. The console already has this state in its response
         * body and hands it to the other screens in the same browser itself, so
         * a dropped publish only affects a screen in a separate browser — OBS —
         * which still has its own reconcile.
         */
        app()->terminating(function () use ($matchId, $state) {
            try {
                MatchStateUpdated::dispatch($matchId, $state);
            } catch (\Throwable $e) {
                report($e);
            }
        });
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
