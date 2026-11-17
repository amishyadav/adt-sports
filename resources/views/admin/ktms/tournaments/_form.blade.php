{{--
  Fields shared by the new and edit tournament pages.

  @param $tournament  App\Models\Ktms\Tournament (unsaved when creating)
--}}
@php $t = $tournament; @endphp

<div class="split-grid">
  <div class="panel-card">
    <h4>Details</h4>

    <div class="field">
      <label>Name *</label>
      <input type="text" name="name" required maxlength="255"
             placeholder="e.g. National Kabaddi Championship"
             value="{{ old('name', $t->name) }}">
    </div>

    <div class="half-grid">
      <div class="field">
        <label>Season</label>
        <input type="text" name="season" maxlength="100" placeholder="e.g. 2026"
               value="{{ old('season', $t->season) }}">
      </div>
      <div class="field">
        <label>Status *</label>
        <select name="status" required>
          @foreach(\App\Models\Ktms\Tournament::STATUSES as $value => $label)
            <option value="{{ $value }}" {{ old('status', $t->status) === $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="half-grid">
      <div class="field">
        <label>Venue</label>
        <input type="text" name="venue" maxlength="255" placeholder="e.g. Indoor Stadium"
               value="{{ old('venue', $t->venue) }}">
        <div class="field-hint">New matches inherit this.</div>
      </div>
      <div class="field">
        <label>City</label>
        <input type="text" name="city" maxlength="255" value="{{ old('city', $t->city) }}">
      </div>
    </div>

    <div class="half-grid">
      <div class="field">
        <label>Start Date</label>
        <input type="date" name="start_date"
               value="{{ old('start_date', $t->start_date?->format('Y-m-d')) }}">
      </div>
      <div class="field">
        <label>End Date</label>
        <input type="date" name="end_date"
               value="{{ old('end_date', $t->end_date?->format('Y-m-d')) }}">
      </div>
    </div>

    <div class="field" style="margin-bottom:0">
      <label>Description</label>
      <textarea name="description" rows="3" maxlength="2000"
                placeholder="Optional notes about the competition…">{{ old('description', $t->description) }}</textarea>
    </div>
  </div>

  <div class="panel-card">
    <h4>Match Defaults</h4>
    <div class="field-hint" style="margin-bottom:14px">
      Every new match in this tournament starts from these. Changing them later
      does not alter matches that already exist.
    </div>

    <div class="field">
      <label>Half Duration (seconds) *</label>
      <input type="number" name="half_duration_seconds" required min="60" max="3600"
             value="{{ old('half_duration_seconds', $t->half_duration_seconds) }}">
      <div class="field-hint">1200 is a standard 20-minute half.</div>
    </div>

    <div class="field">
      <label>Raid Duration (seconds) *</label>
      <input type="number" name="raid_duration_seconds" required min="10" max="120"
             value="{{ old('raid_duration_seconds', $t->raid_duration_seconds) }}">
      <div class="field-hint">30 is the standard raid clock.</div>
    </div>

    <div class="field" style="margin-bottom:0">
      <label>Players Per Side *</label>
      <input type="number" name="players_per_side" required min="5" max="12"
             value="{{ old('players_per_side', $t->players_per_side) }}">
      <div class="field-hint">7 is standard. This also sets how many start on the mat.</div>
    </div>
  </div>
</div>
