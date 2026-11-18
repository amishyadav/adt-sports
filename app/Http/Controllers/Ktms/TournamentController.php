<?php

namespace App\Http\Controllers\Ktms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ktms\TournamentRequest;
use App\Models\ActivityLog;
use App\Models\Ktms\GameMatch;
use App\Models\Ktms\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TournamentController extends Controller
{
    /**
     * Live first, then half time, then what is still to come, then the archive.
     *
     * A CASE expression rather than MySQL's FIELD(), because this project also
     * runs on SQLite, which has no FIELD().
     */
    public const STATUS_ORDER = "CASE status
            WHEN 'live' THEN 0
            WHEN 'half_time' THEN 1
            WHEN 'scheduled' THEN 2
            ELSE 3 END";

    public function index(Request $request): View
    {
        $tournaments = Tournament::query()
            ->withCount(['teams', 'matches'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(fn ($inner) => $inner->where('name', 'like', $term)->orWhere('city', 'like', $term));
            })
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('admin.ktms.tournaments.index', compact('tournaments'));
    }

    public function create(): View
    {
        return view('admin.ktms.tournaments.create', ['tournament' => new Tournament()]);
    }

    public function store(TournamentRequest $request): RedirectResponse
    {
        $tournament = Tournament::create($request->validated());

        ActivityLog::record('ktms.tournament.created', null, 'KTMS: created tournament "'.$tournament->name.'"');

        return redirect()
            ->route('admin.ktms.tournaments.show', $tournament)
            ->with('success', "Tournament \"{$tournament->name}\" created.");
    }

    public function show(Tournament $tournament): View
    {
        $tournament->load(['teams' => fn ($q) => $q->withCount('players')->orderBy('name')]);

        $matches = $tournament->matches()
            ->with(['homeTeam', 'awayTeam'])
            ->orderByRaw(self::STATUS_ORDER)
            ->orderBy('match_number')
            ->get();

        $leaders = $tournament->playerStatistics()
            ->with('player.team')
            ->orderByDesc('total_points')
            ->limit(5)
            ->get();

        return view('admin.ktms.tournaments.show', compact('tournament', 'matches', 'leaders'));
    }

    public function edit(Tournament $tournament): View
    {
        return view('admin.ktms.tournaments.edit', compact('tournament'));
    }

    public function update(TournamentRequest $request, Tournament $tournament): RedirectResponse
    {
        $tournament->update($request->validated());

        ActivityLog::record('ktms.tournament.updated', null, 'KTMS: updated tournament "'.$tournament->name.'"');

        return redirect()
            ->route('admin.ktms.tournaments.show', $tournament)
            ->with('success', 'Tournament updated.');
    }

    public function destroy(Tournament $tournament): RedirectResponse
    {
        // Refuse while a match is in progress: deleting would cascade away a
        // live scoreboard with no way back.
        $liveMatches = $tournament->matches()
            ->whereIn('status', [GameMatch::STATUS_LIVE, GameMatch::STATUS_HALF_TIME])
            ->count();

        if ($liveMatches > 0) {
            return redirect()
                ->route('admin.ktms.tournaments.show', $tournament)
                ->with('error', 'Finish or reset the live matches before deleting this tournament.');
        }

        $name = $tournament->name;
        $tournament->delete();

        ActivityLog::record('ktms.tournament.deleted', null, 'KTMS: deleted tournament "'.$name.'"');

        return redirect()
            ->route('admin.ktms.tournaments.index')
            ->with('success', "Tournament \"{$name}\" deleted.");
    }
}
