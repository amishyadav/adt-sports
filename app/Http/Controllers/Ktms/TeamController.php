<?php

namespace App\Http\Controllers\Ktms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ktms\TeamRequest;
use App\Models\ActivityLog;
use App\Models\Ktms\Team;
use App\Models\Ktms\Tournament;
use App\Support\ImageUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TeamController extends Controller
{
    /**
     * Crests go through this project's ImageUpload helper, which re-encodes via
     * GD (stripping EXIF) and writes into public/uploads — so there is no
     * storage symlink to set up, unlike the original KTMS which used the
     * public disk.
     */
    private const LOGO_DIR = 'uploads/ktms-teams';

    public function create(Tournament $tournament): View
    {
        $team = new Team(['tournament_id' => $tournament->id]);

        return view('admin.ktms.teams.create', compact('tournament', 'team'));
    }

    public function store(TeamRequest $request, Tournament $tournament): RedirectResponse
    {
        $data = $request->validated();
        unset($data['logo'], $data['remove_logo']);

        $data['logo_path'] = $request->hasFile('logo')
            ? ImageUpload::store($request->file('logo'), self::LOGO_DIR)
            : null;

        $team = $tournament->teams()->create($data);

        ActivityLog::record('ktms.team.created', null, 'KTMS: added squad "'.$team->name.'" to "'.$tournament->name.'"');

        return redirect()
            ->route('admin.ktms.teams.show', $team)
            ->with('success', "Squad \"{$team->name}\" added.");
    }

    public function show(Team $team): View
    {
        $team->load(['tournament', 'players']);

        // Season aggregates for the squad, keyed by player for lookup in the view.
        $statistics = $team->tournament
            ->playerStatistics()
            ->whereIn('player_id', $team->players->pluck('id'))
            ->get()
            ->keyBy('player_id');

        $matches = $team->matches()
            ->with(['homeTeam', 'awayTeam'])
            ->orderByDesc('id')
            ->get();

        return view('admin.ktms.teams.show', compact('team', 'statistics', 'matches'));
    }

    public function edit(Team $team): View
    {
        return view('admin.ktms.teams.edit', [
            'team' => $team,
            'tournament' => $team->tournament,
        ]);
    }

    public function update(TeamRequest $request, Team $team): RedirectResponse
    {
        $data = $request->validated();
        $removeLogo = (bool) ($data['remove_logo'] ?? false);
        unset($data['logo'], $data['remove_logo']);

        // Captured before the row changes, so the old file is only binned once
        // the record pointing at it has been saved.
        $oldLogo = $team->logo_path;

        if ($request->hasFile('logo')) {
            $data['logo_path'] = ImageUpload::store($request->file('logo'), self::LOGO_DIR);
        } elseif ($removeLogo) {
            $data['logo_path'] = null;
        }

        $team->update($data);

        if (array_key_exists('logo_path', $data) && $oldLogo !== $team->logo_path) {
            ImageUpload::delete($oldLogo);
        }

        ActivityLog::record('ktms.team.updated', null, 'KTMS: updated squad "'.$team->name.'"');

        return redirect()
            ->route('admin.ktms.teams.show', $team)
            ->with('success', 'Squad updated.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        $tournament = $team->tournament;
        $name = $team->name;
        $logo = $team->logo_path;

        $team->delete();
        ImageUpload::delete($logo);

        ActivityLog::record('ktms.team.deleted', null, 'KTMS: removed squad "'.$name.'"');

        return redirect()
            ->route('admin.ktms.tournaments.show', $tournament)
            ->with('success', "Squad \"{$name}\" removed.");
    }
}
