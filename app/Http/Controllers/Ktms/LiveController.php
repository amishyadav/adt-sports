<?php

namespace App\Http\Controllers\Ktms;

use App\Http\Controllers\Controller;
use App\Models\Ktms\GameMatch;
use App\Services\Ktms\MatchStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * The broadcast scoreboard, plus the shared state endpoint every live screen
 * polls.
 */
class LiveController extends Controller
{
    public function __construct(protected MatchStateService $state) {}

    /** Full-screen scoreboard, for a projector or a second display. */
    public function show(GameMatch $match): View
    {
        $match->load(['tournament', 'homeTeam', 'awayTeam']);

        $snapshot = $this->state->snapshot($match);

        return view('admin.ktms.live.display', compact('match', 'snapshot'));
    }

    /** Single source of truth for the scoring console, timer console and display. */
    public function state(GameMatch $match): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'state' => $this->state->snapshot($match),
        ]);
    }
}
