<?php

namespace App\Http\Controllers\Ktms;

use App\Http\Controllers\Controller;
use App\Models\Ktms\GameMatch;
use App\Services\Ktms\MatchService;
use App\Services\Ktms\MatchStateService;
use App\Services\Ktms\ScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The timer operator's half of the dual-operator setup: the raid clock, the
 * game clock, the half toggle and the court headcount.
 */
class TimerController extends Controller
{
    public function __construct(
        protected MatchService $matches,
        protected MatchStateService $state,
        protected ScoringService $scoring,
    ) {}

    public function console(GameMatch $match): View
    {
        $match->load(['tournament', 'homeTeam', 'awayTeam']);

        $snapshot = $this->state->snapshot($match);

        return view('admin.ktms.live.timer', compact('match', 'snapshot'));
    }

    /* ─── Game clock ───────────────────────────────────────── */

    public function startGameClock(GameMatch $match): JsonResponse
    {
        $this->matches->startGameClock($match);

        return $this->stateResponse($match);
    }

    public function pauseGameClock(GameMatch $match): JsonResponse
    {
        $this->matches->pauseGameClock($match);

        return $this->stateResponse($match);
    }

    public function resetGameClock(GameMatch $match): JsonResponse
    {
        $this->matches->resetGameClock($match);

        return $this->stateResponse($match);
    }

    public function adjustGameClock(Request $request, GameMatch $match): JsonResponse
    {
        $data = $request->validate([
            'seconds' => ['required', 'integer', 'between:-600,600'],
        ]);

        $this->matches->adjustGameClock($match, (int) $data['seconds']);

        return $this->stateResponse($match);
    }

    /* ─── Raid clock ───────────────────────────────────────── */

    public function startRaidClock(GameMatch $match): JsonResponse
    {
        $this->matches->startRaidClock($match);

        return $this->stateResponse($match);
    }

    public function pauseRaidClock(GameMatch $match): JsonResponse
    {
        $this->matches->pauseRaidClock($match);

        return $this->stateResponse($match);
    }

    /** Put the raid clock back to its full duration for the next raid. */
    public function resetRaidClock(Request $request, GameMatch $match): JsonResponse
    {
        $data = $request->validate(['auto_start' => ['nullable', 'boolean']]);

        $this->matches->resetRaidClock($match, (bool) ($data['auto_start'] ?? false));

        return $this->stateResponse($match);
    }

    /* ─── Half & court ─────────────────────────────────────── */

    public function setHalf(Request $request, GameMatch $match): JsonResponse
    {
        $data = $request->validate(['half' => ['required', 'integer', 'in:1,2']]);

        $this->matches->setHalf($match, (int) $data['half']);

        return $this->stateResponse($match);
    }

    public function setCourt(Request $request, GameMatch $match): JsonResponse
    {
        $data = $request->validate([
            'team_id' => ['required', 'integer'],
            // Bounded by this match's squad size rather than silently clamped, so
            // a bad value is reported back instead of being quietly accepted.
            'count' => ['required', 'integer', 'min:0', 'max:'.$match->playersPerSide()],
        ]);

        $this->scoring->setPlayersOnCourt($match, (int) $data['team_id'], (int) $data['count']);

        return $this->stateResponse($match);
    }

    /**
     * @param  array<int, string>  $messages
     */
    protected function stateResponse(GameMatch $match, array $messages = []): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'messages' => array_values(array_filter($messages)),
            'state' => $this->state->snapshot($match->refresh()),
        ]);
    }
}
