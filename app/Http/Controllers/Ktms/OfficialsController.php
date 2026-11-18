<?php

namespace App\Http\Controllers\Ktms;

use App\Http\Controllers\Controller;
use App\Models\Ktms\GameMatch;
use App\Models\Ktms\MatchEvent;
use App\Services\Ktms\MatchStateService;
use App\Services\Ktms\OfficialsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The scoring console's officiating actions: substitutions, injuries, warnings,
 * cards and technical points.
 *
 * Separate from ScoringController because none of these are raids, but they
 * answer in exactly the same way — with the full snapshot — so the console
 * redraws from one source of truth whichever button was pressed.
 */
class OfficialsController extends Controller
{
    public function __construct(
        protected OfficialsService $officials,
        protected MatchStateService $state,
    ) {}

    public function substitution(Request $request, GameMatch $match): JsonResponse
    {
        $data = $request->validate([
            'out_player_id' => ['required', 'integer', 'exists:ktms_players,id'],
            'in_player_id' => ['required', 'integer', 'different:out_player_id', 'exists:ktms_players,id'],
        ], [
            'in_player_id.different' => 'Pick a different player to come on.',
        ]);

        $result = $this->officials->substitute(
            $match,
            (int) $data['out_player_id'],
            (int) $data['in_player_id'],
        );

        return $this->stateResponse($match, $result['messages']);
    }

    public function injury(Request $request, GameMatch $match): JsonResponse
    {
        $data = $request->validate([
            'player_id' => ['required', 'integer', 'exists:ktms_players,id'],
            // Optional: with nobody to bring on, the side plays a player short.
            'replacement_id' => ['nullable', 'integer', 'different:player_id', 'exists:ktms_players,id'],
        ], [
            'replacement_id.different' => 'The replacement cannot be the injured player.',
        ]);

        $result = $this->officials->injury(
            $match,
            (int) $data['player_id'],
            isset($data['replacement_id']) ? (int) $data['replacement_id'] : null,
        );

        return $this->stateResponse($match, $result['messages']);
    }

    /** Warnings and all three cards; the model knows what each one does. */
    public function sanction(Request $request, GameMatch $match): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(MatchEvent::SANCTIONS))],
            'player_id' => ['required', 'integer', 'exists:ktms_players,id'],
            'note' => ['nullable', 'string', 'max:160'],
        ], [
            'type.in' => 'That is not a sanction this console records.',
        ]);

        $result = $this->officials->sanction(
            $match,
            $data['type'],
            (int) $data['player_id'],
            $data['note'] ?? null,
        );

        return $this->stateResponse($match, $result['messages']);
    }

    public function technical(Request $request, GameMatch $match): JsonResponse
    {
        $data = $request->validate([
            // The side that receives the point, not the one that offended.
            'team_id' => ['required', 'integer'],
            'points' => ['nullable', 'integer', 'min:1', 'max:3'],
            'note' => ['nullable', 'string', 'max:160'],
        ]);

        $result = $this->officials->technicalPoint(
            $match,
            (int) $data['team_id'],
            (int) ($data['points'] ?? 1),
            $data['note'] ?? null,
        );

        return $this->stateResponse($match, $result['messages']);
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
