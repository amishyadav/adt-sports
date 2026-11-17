{{--
  Fields shared by the new and edit match pages.

  @param $match       App\Models\Ktms\GameMatch (unsaved when creating)
  @param $tournament  the tournament hosting it
  @param $teams       squads registered in that tournament
--}}
<div class="split-grid">
  <div class="panel-card">
    <h4>Fixture</h4>

    <div class="half-grid">
      <div class="field">
        <label>Home Squad *</label>
        <select name="home_team_id" required>
          <option value="">— Select —</option>
          @foreach($teams as $team)
            <option value="{{ $team->id }}" {{ (int) old('home_team_id', $match->home_team_id) === $team->id ? 'selected' : '' }}>
              {{ $team->name }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label>Away Squad *</label>
        <select name="away_team_id" required>
          <option value="">— Select —</option>
          @foreach($teams as $team)
            <option value="{{ $team->id }}" {{ (int) old('away_team_id', $match->away_team_id) === $team->id ? 'selected' : '' }}>
              {{ $team->name }}
            </option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="half-grid">
      <div class="field">
        <label>Match Number</label>
        <input type="number" name="match_number" min="1" value="{{ old('match_number', $match->match_number) }}">
      </div>
      <div class="field">
        <label>Round</label>
        <input type="text" name="round" maxlength="100" placeholder="e.g. Group / Semi-final"
               value="{{ old('round', $match->round) }}">
      </div>
    </div>

    <div class="half-grid">
      <div class="field">
        <label>Venue</label>
        <input type="text" name="venue" maxlength="255" value="{{ old('venue', $match->venue) }}">
      </div>
      <div class="field">
        <label>Scheduled For</label>
        <input type="datetime-local" name="scheduled_at"
               value="{{ old('scheduled_at', $match->scheduled_at?->format('Y-m-d\TH:i')) }}">
      </div>
    </div>

    @if($match->exists)
      <div class="field" style="margin-bottom:0">
        <label>Status</label>
        <select name="status">
          @foreach(\App\Models\Ktms\GameMatch::STATUSES as $value => $label)
            <option value="{{ $value }}" {{ old('status', $match->status) === $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
        <div class="field-hint">
          The consoles set this as the match runs. Changing it here does not touch the score.
        </div>
      </div>
    @endif
  </div>

  <div class="panel-card">
    <h4>Rules For This Match</h4>
    <div class="field-hint" style="margin-bottom:14px">
      Taken from {{ $tournament->name }}, and adjustable per match.
    </div>

    <div class="field">
      <label>Half Duration (seconds) *</label>
      <input type="number" name="half_duration_seconds" required min="60" max="3600"
             value="{{ old('half_duration_seconds', $match->half_duration_seconds) }}">
    </div>

    <div class="field">
      <label>Raid Duration (seconds) *</label>
      <input type="number" name="raid_duration_seconds" required min="10" max="120"
             value="{{ old('raid_duration_seconds', $match->raid_duration_seconds) }}">
    </div>

    <div class="field" style="margin-bottom:0">
      <label>Players Per Side *</label>
      <input type="number" name="players_per_side" required min="5" max="12"
             value="{{ old('players_per_side', $match->players_per_side) }}">
      @if($match->exists)
        <div class="field-hint">
          Changing this, or either squad, rebuilds the lineup from scratch.
        </div>
      @else
        <div class="field-hint">
          The lineup is seated automatically: the lowest shirt numbers start on the mat.
        </div>
      @endif
    </div>
  </div>
</div>
