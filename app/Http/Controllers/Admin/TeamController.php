<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Team;
use App\Models\TeamMatch;
use App\Support\ImageUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    /** Logos live alongside the media library uploads, in their own folder. */
    private const LOGO_DIR = 'uploads/teams';

    public function index(Request $request)
    {
        $query = Team::withCount(['homeMatches', 'awayMatches'])->orderBy('name');

        if ($request->filled('search')) {
            $query->search($request->search);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status === 'active');
        }

        $teams = $query->paginate(20)->withQueryString();

        return view('admin.teams.index', compact('teams'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100|unique:teams,name',
            'short_name' => 'nullable|string|max:10',
            'logo'       => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
        ]);

        $team = Team::create([
            'name'       => $data['name'],
            'slug'       => $this->uniqueSlug($data['name']),
            'short_name' => $data['short_name'] ?? null,
            'logo'       => $request->hasFile('logo')
                ? ImageUpload::store($request->file('logo'), self::LOGO_DIR)
                : null,
            // Unchecked toggles aren't submitted at all, so absence means "off".
            // The create form ships the toggle pre-checked to match that.
            'status'     => $request->boolean('status'),
        ]);

        ActivityLog::record('team.created', $team, 'Created team "' . $team->name . '"');

        return back()->with('success', 'Team created.');
    }

    public function update(Request $request, Team $team)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100|unique:teams,name,' . $team->id,
            'short_name' => 'nullable|string|max:10',
            'logo'       => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
        ]);

        $attributes = [
            'name'       => $data['name'],
            'short_name' => $data['short_name'] ?? null,
            'status'     => $request->boolean('status'),
        ];

        // Keep the slug in step with the name, but only when the name changed —
        // re-slugging an unchanged name would needlessly bump the -2 suffix.
        if ($data['name'] !== $team->name) {
            $attributes['slug'] = $this->uniqueSlug($data['name'], $team->id);
        }

        $oldLogo = $team->logo;
        if ($request->hasFile('logo')) {
            $attributes['logo'] = ImageUpload::store($request->file('logo'), self::LOGO_DIR);
        } elseif ($request->boolean('remove_logo')) {
            $attributes['logo'] = null;
        }

        $team->update($attributes);

        // Only bin the old file once the row pointing at it is safely updated.
        if (array_key_exists('logo', $attributes) && $oldLogo !== $team->logo) {
            ImageUpload::delete($oldLogo);
        }

        ActivityLog::record('team.updated', $team, 'Updated team "' . $team->name . '"');

        return back()->with('success', 'Team updated.');
    }

    public function destroy(Team $team)
    {
        $fixtures = TeamMatch::forTeam($team->id)->count();
        if ($fixtures > 0) {
            return back()->with('error', "Cannot delete: team is in {$fixtures} match(es). Remove those matches first.");
        }

        $name = $team->name;
        $logo = $team->logo;
        $team->delete();
        ImageUpload::delete($logo);

        ActivityLog::record('team.deleted', null, 'Deleted team "' . $name . '"');

        return back()->with('success', 'Team deleted.');
    }

    /** Flip the active toggle straight from the list row. */
    public function toggleStatus(Team $team)
    {
        $team->update(['status' => ! $team->status]);

        $state = $team->status ? 'Activated' : 'Deactivated';
        ActivityLog::record('team.updated', $team, $state . ' team "' . $team->name . '"');

        return back()->with('success', $state . ' ' . $team->name . '.');
    }

    /** Slugify a name, suffixing -2, -3… until it's free. */
    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'team';
        $slug = $base;
        $suffix = 2;

        while (Team::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
