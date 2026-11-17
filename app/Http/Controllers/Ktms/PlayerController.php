<?php

namespace App\Http\Controllers\Ktms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ktms\PlayerRequest;
use App\Models\ActivityLog;
use App\Models\Ktms\Player;
use App\Models\Ktms\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PlayerController extends Controller
{
    public function create(Team $team): View
    {
        $player = new Player([
            'team_id' => $team->id,
            // Suggest the next free shirt number.
            'jersey_number' => (int) $team->players()->max('jersey_number') + 1,
        ]);

        return view('admin.ktms.players.create', compact('team', 'player'));
    }

    public function store(PlayerRequest $request, Team $team): RedirectResponse
    {
        $player = DB::transaction(function () use ($request, $team) {
            $player = $team->players()->create($request->validated());

            $this->enforceSingleCaptain($player);

            // Open the aggregate row immediately so reports list every squad
            // member, even before their first match.
            $player->statisticFor((int) $team->tournament_id);

            return $player;
        });

        ActivityLog::record('ktms.player.created', null, 'KTMS: added '.$player->name.' to "'.$team->name.'"');

        return redirect()
            ->route('admin.ktms.teams.show', $team)
            ->with('success', "{$player->name} added to {$team->name}.");
    }

    public function show(Player $player): View
    {
        $player->load(['team.tournament']);

        $statistic = $player->statisticFor((int) $player->team->tournament_id);

        $raids = $player->raids()
            ->with(['match.homeTeam', 'match.awayTeam', 'defendingTeam'])
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $defenses = $player->defensiveActions()
            ->with(['match.homeTeam', 'match.awayTeam', 'raid.raider'])
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        return view('admin.ktms.players.show', compact('player', 'statistic', 'raids', 'defenses'));
    }

    public function edit(Player $player): View
    {
        return view('admin.ktms.players.edit', [
            'player' => $player,
            'team' => $player->team,
        ]);
    }

    public function update(PlayerRequest $request, Player $player): RedirectResponse
    {
        DB::transaction(function () use ($request, $player) {
            $player->update($request->validated());
            $this->enforceSingleCaptain($player);
        });

        ActivityLog::record('ktms.player.updated', null, 'KTMS: updated player '.$player->name);

        return redirect()
            ->route('admin.ktms.players.show', $player)
            ->with('success', 'Player updated.');
    }

    public function destroy(Player $player): RedirectResponse
    {
        $team = $player->team;
        $name = $player->name;

        $player->delete();

        ActivityLog::record('ktms.player.deleted', null, 'KTMS: removed player '.$name);

        return redirect()
            ->route('admin.ktms.teams.show', $team)
            ->with('success', "{$name} removed from the squad.");
    }

    /** A squad can only have one captain at a time. */
    protected function enforceSingleCaptain(Player $player): void
    {
        if (! $player->is_captain) {
            return;
        }

        Player::where('team_id', $player->team_id)
            ->whereKeyNot($player->id)
            ->where('is_captain', true)
            ->update(['is_captain' => false]);
    }
}
