<?php

namespace App\Http\Controllers\Ktms;

use App\Http\Controllers\Controller;
use App\Models\Ktms\GameMatch;
use App\Services\Ktms\MatchStateService;
use Illuminate\Http\JsonResponse;

/**
 * The shared match-state endpoint.
 *
 * This is what the scoring console polls, and what every scoring action replies
 * with, so the operator's screen always redraws from one source of truth.
 *
 * It used to serve a full-screen broadcast scoreboard as well. That screen and
 * the separate timer console have both been removed, but the endpoint stays:
 * the scoring console depends on it.
 */
class LiveController extends Controller
{
    public function __construct(protected MatchStateService $state) {}

    public function state(GameMatch $match): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'state' => $this->state->snapshot($match),
        ]);
    }
}
