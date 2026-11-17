{{--
  Fields shared by the new and edit player pages.

  @param $player  App\Models\Ktms\Player (unsaved when creating)
  @param $team    the squad they belong to
--}}
<div class="split-grid">
  <div class="panel-card">
    <h4>Player</h4>

    <div class="half-grid">
      <div class="field">
        <label>Name *</label>
        <input type="text" name="name" required maxlength="255"
               placeholder="e.g. Pardeep Narwal" value="{{ old('name', $player->name) }}">
      </div>
      <div class="field">
        <label>Jersey Number *</label>
        <input type="number" name="jersey_number" required min="0" max="999"
               value="{{ old('jersey_number', $player->jersey_number) }}">
        <div class="field-hint">Unique within {{ $team->name }}.</div>
      </div>
    </div>

    <div class="half-grid">
      <div class="field">
        <label>Role *</label>
        <select name="role" required>
          @foreach(\App\Models\Ktms\Player::ROLES as $value => $label)
            <option value="{{ $value }}" {{ old('role', $player->role) === $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label>Position</label>
        <select name="position">
          <option value="">Not set</option>
          @foreach(\App\Models\Ktms\Player::POSITIONS as $value => $label)
            <option value="{{ $value }}" {{ old('position', $player->position) === $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="field" style="margin-bottom:0">
      <label>Nationality</label>
      <input type="text" name="nationality" maxlength="100"
             value="{{ old('nationality', $player->nationality) }}">
    </div>
  </div>

  <div class="panel-card">
    <h4>Profile</h4>

    <div class="field">
      <label>Date of Birth</label>
      <input type="date" name="date_of_birth"
             value="{{ old('date_of_birth', $player->date_of_birth?->format('Y-m-d')) }}">
    </div>

    <div class="half-grid">
      <div class="field">
        <label>Height (cm)</label>
        <input type="number" name="height_cm" min="100" max="250"
               value="{{ old('height_cm', $player->height_cm) }}">
      </div>
      <div class="field">
        <label>Weight (kg)</label>
        <input type="number" name="weight_kg" min="30" max="200"
               value="{{ old('weight_kg', $player->weight_kg) }}">
      </div>
    </div>

    <div class="toggle-row">
      <span class="toggle-label">Captain <span style="color:var(--ink3)">(one per squad)</span></span>
      <input type="checkbox" name="is_captain" class="toggle-cb" value="1"
             {{ old('is_captain', $player->is_captain) ? 'checked' : '' }}>
    </div>

    <div class="toggle-row">
      <span class="toggle-label">Active <span style="color:var(--ink3)">(picked in lineups)</span></span>
      <input type="checkbox" name="is_active" class="toggle-cb" value="1"
             {{ old('is_active', $player->is_active) ? 'checked' : '' }}>
    </div>

    <div class="field-hint" style="margin-top:12px">
      Marking someone captain clears the flag from whoever held it before.
      Inactive players are left out when a lineup is built.
    </div>
  </div>
</div>
