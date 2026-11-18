<?php

namespace App\Http\Controllers\Ktms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ktms\MatchRequest;
use App\Models\ActivityLog;
use App\Models\Ktms\GameMatch;
use App\Models\Ktms\Tournament;
use App\Services\Ktms\MatchService;
use App\Services\Ktms\StatisticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MatchController extends Controller
{
    public function __construct(
        protected MatchService $matches,
        protected StatisticsService $statistics,
    ) {}

    public function index(Request $request): View
    {
        $matches = GameMatch::query()
            ->with(['tournament', 'homeTeam', 'awayTeam'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('tournament'), fn ($q) => $q->where('tournament_id', $request->integer('tournament')))
            ->orderByRaw(TournamentController::STATUS_ORDER)
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $tournaments = Tournament::orderBy('name')->get();

        return view('admin.ktms.matches.index', compact('matches', 'tournaments'));
    }

    public function create(Tournament $tournament): View
    {
        $teams = $this->matches->selectableTeams($tournament);

        $match = new GameMatch([
            'tournament_id' => $tournament->id,
            'half_duration_seconds' => $tournament->half_duration_seconds,
            'raid_duration_seconds' => $tournament->raid_duration_seconds,
            'players_per_side' => $tournament->players_per_side,
            'venue' => $tournament->venue,
            'match_number' => (int) $tournament->matches()->max('match_number') + 1,
        ]);

        return view('admin.ktms.matches.create', compact('tournament', 'teams', 'match'));
    }

    public function store(MatchRequest $request, Tournament $tournament): RedirectResponse
    {
        if ($tournament->teams()->count() < 2) {
            return back()->with('error', 'Add at least two squads before creating a match.');
        }

        $match = $this->matches->createForTournament($tournament, $request->validated());

        ActivityLog::record('ktms.match.created', null, 'KTMS: created match '.$match->title);

        return redirect()
            ->route('admin.ktms.matches.show', $match)
            ->with('success', "Match created: {$match->title}.");
    }

    public function show(GameMatch $match): View
    {
        $match->load(['tournament', 'homeTeam.players', 'awayTeam.players', 'winnerTeam']);

        $raids = $match->raids()
            ->with(['raider', 'raidingTeam', 'defensiveActions.defender'])
            ->orderByDesc('raid_number')
            ->get();

        $timeline = $match->events()
            ->with(['team', 'player'])
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        $topPerformers = $match->tournament
            ->playerStatistics()
            ->with('player.team')
            ->whereIn('player_id', $match->raids()->select('raider_id'))
            ->orderByDesc('total_points')
            ->limit(5)
            ->get();

        return view('admin.ktms.matches.show', compact('match', 'raids', 'timeline', 'topPerformers'));
    }

    public function edit(GameMatch $match): View
    {
        $tournament = $match->tournament;

        return view('admin.ktms.matches.edit', [
            'match' => $match,
            'tournament' => $tournament,
            'teams' => $this->matches->selectableTeams($tournament),
        ]);
    }

    public function update(MatchRequest $request, GameMatch $match): RedirectResponse
    {
        $data = $request->validated();

        $lineupAffected = (int) $data['players_per_side'] !== (int) $match->players_per_side
            || (int) $data['home_team_id'] !== (int) $match->home_team_id
            || (int) $data['away_team_id'] !== (int) $match->away_team_id;

        $match->update($data);

        // If the fixture or squad size changed, the stored lineup no longer applies.
        if ($lineupAffected) {
            $match->refresh();
            $this->matches->initialiseLineup($match);
        }

        ActivityLog::record('ktms.match.updated', null, 'KTMS: updated match '.$match->title);

        return redirect()
            ->route('admin.ktms.matches.show', $match)
            ->with('success', 'Match updated.');
    }

    public function destroy(GameMatch $match): RedirectResponse
    {
        $tournament = $match->tournament;
        $title = $match->title;

        // Collect the squads before deleting: the lineup rows cascade away with
        // the match, and their totals still need rebuilding afterwards.
        $playerIds = DB::table('ktms_match_player')->where('match_id', $match->id)->pluck('player_id');

        $match->delete();

        $this->statistics->recalculatePlayers($playerIds, (int) $tournament->id);

        ActivityLog::record('ktms.match.deleted', null, 'KTMS: deleted match '.$title);

        return redirect()
            ->route('admin.ktms.tournaments.show', $tournament)
            ->with('success', "Match \"{$title}\" deleted.");
    }

    /** Clear all scoring for a match and return it to its pre-match state. */
    public function reset(GameMatch $match): RedirectResponse
    {
        $this->matches->resetLiveState($match);

        ActivityLog::record('ktms.match.reset', null, 'KTMS: reset match '.$match->title);

        return redirect()
            ->route('admin.ktms.matches.show', $match)
            ->with('success', 'Match reset. Scores, raids and timers are back to their starting values.');
    }
}
