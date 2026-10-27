<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Team;
use App\Models\TeamMatch;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeamMatchController extends Controller
{
    public function index(Request $request)
    {
        $query = TeamMatch::with(['team1', 'team2'])
            ->orderByRaw('scheduled_at IS NULL')   // dated fixtures first, undated last
            ->orderBy('scheduled_at')
            ->latest('id');

        if ($request->filled('search')) {
            $query->search($request->search);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $matches = $query->paginate(20)->withQueryString();

        // Every team is listed, not just active ones: a fixture can legitimately
        // reference a team that was deactivated later, and dropping it from the
        // options would blank the select when that match is edited. Inactive
        // teams are flagged in the dropdown instead.
        $teams = Team::orderBy('name')->get();

        return view('admin.matches.index', compact('matches', 'teams'));
    }

    public function store(Request $request)
    {
        $data = $this->validateMatch($request);

        $match = TeamMatch::create($data);
        $match->load(['team1', 'team2']);

        ActivityLog::record('match.created', $match, 'Created match ' . $match->title);

        return back()->with('success', 'Match created.');
    }

    public function update(Request $request, TeamMatch $match)
    {
        $data = $this->validateMatch($request);

        $match->update($data);
        $match->load(['team1', 'team2']);

        ActivityLog::record('match.updated', $match, 'Updated match ' . $match->title);

        return back()->with('success', 'Match updated.');
    }

    public function destroy(TeamMatch $match)
    {
        $title = $match->load(['team1', 'team2'])->title;
        $match->delete();

        ActivityLog::record('match.deleted', null, 'Deleted match ' . $title);

        return back()->with('success', 'Match deleted.');
    }

    /**
     * Shared rules for store/update. `different` stops a team being pitted
     * against itself; scores default to 0 so a blank field doesn't null the column.
     */
    private function validateMatch(Request $request): array
    {
        $data = $request->validate([
            'team_1'       => 'required|integer|exists:teams,id',
            'team_2'       => 'required|integer|exists:teams,id|different:team_1',
            'status'       => ['required', Rule::in(TeamMatch::STATUSES)],
            'team_1_score' => 'nullable|integer|min:0|max:9999',
            'team_2_score' => 'nullable|integer|min:0|max:9999',
            'scheduled_at' => 'nullable|date',
            'venue'        => 'nullable|string|max:150',
        ], [
            'team_2.different' => 'Team 2 must be a different team from Team 1.',
        ]);

        $data['team_1_score'] = $data['team_1_score'] ?? 0;
        $data['team_2_score'] = $data['team_2_score'] ?? 0;

        return $data;
    }
}
