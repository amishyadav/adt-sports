@extends('layouts.admin')
@section('title','Scoring Console')
@section('content')

@php
  $home = $match->homeTeam;
  $away = $match->awayTeam;

  /*
   * Everyone currently on the mat, by side, so the dependent pick lists
   * (defenders out, tacklers, beaten defenders) can be rebuilt client-side for
   * whichever side is defending once a raider is chosen.
   *
   * Built here rather than inline in @json below: Blade parses a directive's
   * argument by matching brackets, and an arrow function returning an array
   * inside it trips that up.
   */
  $squadPayload = [];

  foreach (['home' => $match->home_team_id, 'away' => $match->away_team_id] as $side => $teamId) {
      $squadPayload[$side] = [
          'id' => (int) $teamId,
          'players' => $lineups[$side]['on_court']
              ->map(fn ($p) => ['id' => $p->id, 'no' => $p->jersey_number, 'name' => $p->name])
              ->values()
              ->all(),
      ];
  }
@endphp

<div class="page-hd">
  <div>
    <h1>Scoring Console</h1>
    <div class="page-hd-sub">
      {{ $match->title }} ·
      <a href="{{ route('admin.ktms.matches.show', $match) }}" style="color:var(--brand)">match page</a>
    </div>
  </div>
  <div class="ktms-btn-row">
    <a href="{{ route('admin.ktms.timer.console', $match) }}" class="btn btn-ghost">
      <i class="fa-solid fa-stopwatch"></i> Timer
    </a>
    <a href="{{ route('admin.ktms.matches.live', $match) }}" target="_blank" rel="noopener" class="btn btn-ghost">
      <i class="fa-solid fa-tv"></i> Display
    </a>
  </div>
</div>

<div class="ktms-console">
  @include('admin.ktms.live._board', ['match' => $match])

  <div class="ktms-dod" data-dod>
    <i class="fa-solid fa-triangle-exclamation"></i>
    Next raid is do-or-die — an empty raid puts the raider out
  </div>

  <div class="ktms-console-grid">
    <div>
      {{-- Record a raid --}}
      <div class="panel-card">
        <h4>Record A Raid</h4>

        <div class="field">
          <label>Raider *</label>
          <div class="ktms-picks" data-raider-picks>
            @foreach(['home' => $home, 'away' => $away] as $side => $team)
              @foreach($lineups[$side]['on_court'] as $player)
                <label class="ktms-pick" data-team="{{ $team?->id }}">
                  <input type="radio" name="raider_id" value="{{ $player->id }}">
                  <span class="ktms-pick-no">{{ $player->jersey_number }}</span>
                  {{ Str::limit($player->name, 10) }}
                </label>
              @endforeach
            @endforeach
          </div>
          <div class="field-hint">
            The raiding side is taken from whoever you pick, so the two can never disagree.
          </div>
        </div>

        <div class="field">
          <label>Touch Points</label>
          <div class="ktms-btn-grid" data-touch-picks>
            @for($i = 0; $i <= 5; $i++)
              <button type="button" class="btn btn-ghost" data-touch="{{ $i }}">{{ $i }}</button>
            @endfor
          </div>
          <div class="field-hint">One point per defender touched. Each point also takes one defender off the mat.</div>
        </div>

        <div class="field">
          <label class="ktms-pick" style="display:inline-flex;padding:8px 14px">
            <input type="checkbox" id="isBonus"> Bonus point taken
          </label>
        </div>

        <div class="field">
          <label>Defenders Out <span style="font-weight:400;text-transform:none;letter-spacing:0">(optional — names who is sitting out)</span></label>
          <div class="ktms-picks" data-out-picks></div>
          <div class="field-hint">
            The court count comes from the touch points either way; naming people
            only lets the display show <em>who</em> is out.
          </div>
        </div>

        <div class="field">
          <label>Tacklers <span style="font-weight:400;text-transform:none;letter-spacing:0">(for a tackled raid)</span></label>
          <div class="ktms-picks" data-tackler-picks></div>
        </div>

        <div class="field">
          <label>Beaten Defenders <span style="font-weight:400;text-transform:none;letter-spacing:0">(tried and failed)</span></label>
          <div class="ktms-picks" data-beaten-picks></div>
        </div>

        <div class="field">
          <label>Tackle Type</label>
          <select id="actionType">
            @foreach(\App\Models\Ktms\DefensiveAction::ACTION_TYPES as $value => $label)
              @continue($value === 'super_tackle')
              <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
          </select>
          <div class="field-hint">A super tackle is detected automatically when the defence is down to three or fewer.</div>
        </div>

        <div class="ktms-btn-row">
          <button type="button" class="btn btn-success btn-ktms-xl" data-raid="successful">
            <i class="fa-solid fa-check"></i> Successful
          </button>
          <button type="button" class="btn btn-danger btn-ktms-xl" data-raid="unsuccessful">
            <i class="fa-solid fa-hand"></i> Tackled
          </button>
          <button type="button" class="btn btn-ghost btn-ktms-xl" data-raid="empty">
            <i class="fa-solid fa-circle-minus"></i> Empty
          </button>
        </div>
      </div>

      {{-- Standalone defensive action --}}
      <div class="panel-card">
        <h4>Log A Defensive Action</h4>
        <div class="field">
          <label>Defender</label>
          <div class="ktms-picks">
            @foreach(['home' => $home, 'away' => $away] as $side => $team)
              @foreach($lineups[$side]['on_court'] as $player)
                <label class="ktms-pick">
                  <input type="radio" name="defender_id" value="{{ $player->id }}">
                  <span class="ktms-pick-no">{{ $player->jersey_number }}</span>
                  {{ Str::limit($player->name, 10) }}
                </label>
              @endforeach
            @endforeach
          </div>
        </div>
        <div class="field">
          <label>Action</label>
          <select id="defenseType">
            @foreach(\App\Models\Ktms\DefensiveAction::ACTION_TYPES as $value => $label)
              @continue($value === 'super_tackle')
              <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="ktms-btn-row">
          <button type="button" class="btn btn-success" data-defense="1">
            <i class="fa-solid fa-shield-halved"></i> Stopped the raider
          </button>
          <button type="button" class="btn btn-ghost" data-defense="0">
            <i class="fa-solid fa-xmark"></i> Was beaten
          </button>
        </div>
        <div class="field-hint" style="margin-top:10px">
          Worth one point, or two when that side is down to three or fewer.
        </div>
      </div>
    </div>

    <div>
      {{-- Raid toggle --}}
      <div class="panel-card">
        <h4>Raid In Progress</h4>
        <div class="ktms-btn-row">
          <button type="button" class="btn btn-primary btn-ktms-xl" style="flex:1"
                  data-toggle-raid="1" data-team="{{ $home?->id }}">
            {{ $home?->short_name ?: 'Home' }} raiding
          </button>
          <button type="button" class="btn btn-primary btn-ktms-xl" style="flex:1"
                  data-toggle-raid="1" data-team="{{ $away?->id }}">
            {{ $away?->short_name ?: 'Away' }} raiding
          </button>
        </div>
        <button type="button" class="btn btn-ghost" style="width:100%;justify-content:center;margin-top:8px"
                data-toggle-raid="0">
          <i class="fa-solid fa-stop"></i> Stop raid
        </button>
        <div class="field-hint" style="margin-top:10px">
          Starting a raid arms a fresh {{ $match->raid_duration_seconds }}-second clock.
        </div>
      </div>

      {{-- Manual score --}}
      <div class="panel-card">
        <h4>Adjust Score By Hand</h4>
        @foreach([['home', $home], ['away', $away]] as [$side, $team])
          <div class="field">
            <label>{{ $team?->name }}</label>
            <div class="ktms-btn-grid">
              <button type="button" class="btn btn-ghost" data-score data-team="{{ $team?->id }}" data-delta="-2">−2</button>
              <button type="button" class="btn btn-ghost" data-score data-team="{{ $team?->id }}" data-delta="-1">−1</button>
              <button type="button" class="btn btn-ghost" data-score data-team="{{ $team?->id }}" data-delta="1">+1</button>
              <button type="button" class="btn btn-ghost" data-score data-team="{{ $team?->id }}" data-delta="2">+2</button>
            </div>
          </div>
        @endforeach
        <div class="field-hint">
          These move only the score. Use the court controls below if the number
          standing needs to change too.
        </div>
      </div>

      {{-- Court --}}
      <div class="panel-card">
        <h4>Players On The Mat</h4>
        @foreach([['home', $home], ['away', $away]] as [$side, $team])
          <div class="field">
            <label>{{ $team?->name }}</label>
            <div class="ktms-btn-grid">
              <button type="button" class="btn btn-ghost" data-court data-team="{{ $team?->id }}" data-delta="-1">−1</button>
              <button type="button" class="btn btn-ghost" data-court data-team="{{ $team?->id }}" data-delta="1">+1</button>
              <button type="button" class="btn btn-ghost" data-revive data-team="{{ $team?->id }}">
                <i class="fa-solid fa-heart-pulse"></i> All back
              </button>
            </div>
          </div>
        @endforeach
      </div>

      {{-- Match control --}}
      <div class="panel-card">
        <h4>Match</h4>
        <button type="button" class="btn btn-amber btn-ktms-xl" style="width:100%;margin-bottom:8px" data-undo>
          <i class="fa-solid fa-rotate-left"></i> Undo Last Action
        </button>
        <div class="ktms-btn-grid">
          <button type="button" class="btn btn-ghost btn-sm" data-status="live">Live</button>
          <button type="button" class="btn btn-ghost btn-sm" data-status="half_time">Half time</button>
          <button type="button" class="btn btn-ghost btn-sm" data-status="completed"
                  data-confirm="Mark this match as finished? The winner is decided from the score and the clocks stop.">Full time</button>
        </div>
        <div class="field-hint" style="margin-top:10px">
          Undo reverses the last raid, tackle, score change or court correction,
          and rebuilds the affected players' statistics.
        </div>
      </div>
    </div>
  </div>

  {{-- Timeline --}}
  <div class="table-wrap">
    <div class="table-hd"><h3>Timeline</h3></div>
    <div style="padding:10px" class="ktms-timeline" data-timeline></div>
  </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/ktms-live.js') }}"></script>
<script>
(function () {
  var base = @json(url('admin/ktms/matches/' . $match->id . '/scoring'));
  var homeId = @json((int) $match->home_team_id);

  // Who is on the mat for each side; see the @php block at the top of this view.
  var squads = @json($squadPayload);

  var session = window.KtmsLive.session({
    stateUrl: @json(route('admin.ktms.matches.state', $match)),
    initialState: @json($snapshot),
    pollInterval: 2000,
  });

  var touchPoints = 0;

  /* ── Paint from each snapshot ──────────────────────────── */
  session.onState(function (state) {
    window.KtmsLive.setText('[data-home-score]', state.teams.home.score);
    window.KtmsLive.setText('[data-away-score]', state.teams.away.score);
    window.KtmsLive.setText('[data-half]', state.match.current_half);
    window.KtmsLive.setText('[data-raid-count]', state.match.raid_count);

    var status = document.querySelector('[data-status]');
    if (status) {
      status.textContent = state.match.status_label;
      status.className = 'badge ' + state.match.status_badge;
    }

    paintPips('[data-home-pips]', state.teams.home.players_on_court, state.match.players_per_side);
    paintPips('[data-away-pips]', state.teams.away.players_on_court, state.match.players_per_side);

    // Ring the side currently raiding.
    document.querySelectorAll('[data-side]').forEach(function (node) {
      var side = node.dataset.side;
      node.classList.toggle('ktms-raiding', Boolean(state.teams[side] && state.teams[side].is_raiding));
    });

    var dod = document.querySelector('[data-dod]');
    if (dod) { dod.classList.toggle('is-on', Boolean(state.next_raid_is_do_or_die)); }

    paintTimeline(state.timeline);
  });

  session.onTick(function (s) {
    window.KtmsLive.setText('[data-game-clock]', s.gameClock.formatted());
  });

  function paintPips(selector, on, total) {
    var host = document.querySelector(selector);
    if (!host) { return; }

    if (host.children.length !== total) {
      host.innerHTML = '';
      for (var i = 0; i < total; i++) {
        host.appendChild(document.createElement('span')).className = 'ktms-pip';
      }
    }

    Array.prototype.forEach.call(host.children, function (pip, index) {
      pip.classList.toggle('on', index < on);
    });
  }

  function paintTimeline(events) {
    var host = document.querySelector('[data-timeline]');
    if (!host) { return; }

    if (!events || !events.length) {
      host.innerHTML = '<div class="empty-state"><i class="fa-solid fa-clock-rotate-left"></i>Nothing has happened yet</div>';
      return;
    }

    host.innerHTML = events.map(function (e) {
      return '<div class="ktms-event">'
        + '<span class="ktms-event-clock">' + escapeHtml(e.clock) + '</span>'
        + '<span class="ktms-event-icon"><i class="fa-solid ' + escapeHtml(e.icon) + '"></i></span>'
        + '<span class="ktms-event-text">' + escapeHtml(e.description) + '</span>'
        + '<span class="ktms-event-pts">' + (e.points ? '+' + e.points : '') + '</span>'
        + '</div>';
    }).join('');
  }

  // Descriptions are server-built from squad and player names, so escape before
  // writing them as HTML.
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ── Raider selection drives the dependent lists ───────── */
  function selectedRaider() {
    return document.querySelector('input[name="raider_id"]:checked');
  }

  function defendingSide() {
    var picked = selectedRaider();
    if (!picked) { return null; }

    var teamId = Number(picked.closest('.ktms-pick').dataset.team);

    return teamId === homeId ? 'away' : 'home';
  }

  function renderDefenderLists() {
    var side = defendingSide();

    ['[data-out-picks]', '[data-tackler-picks]', '[data-beaten-picks]'].forEach(function (selector) {
      var host = document.querySelector(selector);
      if (!host) { return; }

      if (!side) {
        host.innerHTML = '<div class="field-hint" style="margin:0">Pick a raider first.</div>';
        return;
      }

      host.innerHTML = squads[side].players.map(function (p) {
        return '<label class="ktms-pick">'
          + '<input type="checkbox" value="' + p.id + '">'
          + '<span class="ktms-pick-no">' + p.no + '</span>'
          + escapeHtml(p.name.length > 10 ? p.name.slice(0, 10) + '…' : p.name)
          + '</label>';
      }).join('');

      wirePicks(host);
    });
  }

  function wirePicks(root) {
    root.querySelectorAll('.ktms-pick input').forEach(function (input) {
      input.addEventListener('change', function () {
        syncPickClasses(input);
      });
    });
  }

  function syncPickClasses(input) {
    if (input.type === 'radio' && input.name) {
      document.querySelectorAll('input[name="' + input.name + '"]').forEach(function (other) {
        other.closest('.ktms-pick').classList.toggle('is-picked', other.checked);
      });
    } else {
      input.closest('.ktms-pick').classList.toggle('is-picked', input.checked);
    }
  }

  document.querySelectorAll('.ktms-pick input').forEach(function (input) {
    input.addEventListener('change', function () {
      syncPickClasses(input);

      if (input.name === 'raider_id') {
        renderDefenderLists();
      }
    });
  });

  renderDefenderLists();

  /* ── Touch point picker ────────────────────────────────── */
  document.querySelectorAll('[data-touch]').forEach(function (button) {
    button.addEventListener('click', function () {
      touchPoints = Number(button.dataset.touch);

      document.querySelectorAll('[data-touch]').forEach(function (other) {
        other.classList.toggle('btn-primary', other === button);
        other.classList.toggle('btn-ghost', other !== button);
      });
    });
  });

  function checkedValues(selector) {
    return Array.prototype.map.call(
      document.querySelectorAll(selector + ' input:checked'),
      function (input) { return Number(input.value); }
    );
  }

  function resetRaidForm() {
    touchPoints = 0;
    document.getElementById('isBonus').checked = false;

    document.querySelectorAll('[data-touch]').forEach(function (b) {
      b.classList.remove('btn-primary');
      b.classList.add('btn-ghost');
    });

    document.querySelectorAll('.ktms-pick input').forEach(function (input) {
      input.checked = false;
      input.closest('.ktms-pick').classList.remove('is-picked');
    });

    renderDefenderLists();
  }

  /* ── Actions ───────────────────────────────────────────── */
  document.querySelectorAll('[data-raid]').forEach(function (button) {
    button.addEventListener('click', function () {
      var picked = selectedRaider();

      if (!picked) {
        session.toast('Pick a raider first.', 'bad');
        return;
      }

      var result = button.dataset.raid;

      session.act(base + '/raid', {
        raider_id: Number(picked.value),
        result: result,
        touch_points: result === 'successful' ? touchPoints : 0,
        is_bonus: document.getElementById('isBonus').checked,
        defender_out_ids: checkedValues('[data-out-picks]'),
        tackler_ids: checkedValues('[data-tackler-picks]'),
        beaten_defender_ids: checkedValues('[data-beaten-picks]'),
        action_type: document.getElementById('actionType').value,
      }).then(function (state) {
        if (state) { resetRaidForm(); }
      });
    });
  });

  document.querySelectorAll('[data-defense]').forEach(function (button) {
    button.addEventListener('click', function () {
      var picked = document.querySelector('input[name="defender_id"]:checked');

      if (!picked) {
        session.toast('Pick a defender first.', 'bad');
        return;
      }

      session.act(base + '/defense', {
        defender_id: Number(picked.value),
        action_type: document.getElementById('defenseType').value,
        is_successful: button.dataset.defense === '1',
      }).then(function (state) {
        if (state) {
          picked.checked = false;
          picked.closest('.ktms-pick').classList.remove('is-picked');
        }
      });
    });
  });

  document.querySelectorAll('[data-toggle-raid]').forEach(function (button) {
    button.addEventListener('click', function () {
      var body = { active: button.dataset.toggleRaid === '1' };
      if (button.dataset.team) { body.raiding_team_id = Number(button.dataset.team); }

      session.act(base + '/raid/toggle', body);
    });
  });

  document.querySelectorAll('[data-score]').forEach(function (button) {
    button.addEventListener('click', function () {
      session.act(base + '/score', {
        team_id: Number(button.dataset.team),
        delta: Number(button.dataset.delta),
      });
    });
  });

  document.querySelectorAll('[data-court]').forEach(function (button) {
    button.addEventListener('click', function () {
      session.act(base + '/court', {
        team_id: Number(button.dataset.team),
        delta: Number(button.dataset.delta),
      });
    });
  });

  document.querySelectorAll('[data-revive]').forEach(function (button) {
    button.addEventListener('click', function () {
      session.act(base + '/revive', { team_id: Number(button.dataset.team) });
    });
  });

  var undo = document.querySelector('[data-undo]');
  if (undo) {
    undo.addEventListener('click', function () {
      session.act(base + '/undo', {});
    });
  }

  document.querySelectorAll('[data-status]:not(.badge)').forEach(function (button) {
    button.addEventListener('click', function () {
      var confirmText = button.dataset.confirm;
      if (confirmText && !window.confirm(confirmText)) { return; }

      session.act(base + '/status', { status: button.dataset.status });
    });
  });

  session.start();
})();
</script>
@endpush
