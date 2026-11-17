@extends('layouts.admin')
@section('title','Timer Console')
@section('content')

<div class="page-hd">
  <div>
    <h1>Timer Console</h1>
    <div class="page-hd-sub">
      {{ $match->title }} ·
      <a href="{{ route('admin.ktms.matches.show', $match) }}" style="color:var(--brand)">match page</a>
    </div>
  </div>
  <div class="ktms-btn-row">
    <a href="{{ route('admin.ktms.scoring.console', $match) }}" class="btn btn-ghost">
      <i class="fa-solid fa-gamepad"></i> Scoring
    </a>
    <a href="{{ route('admin.ktms.matches.live', $match) }}" target="_blank" rel="noopener" class="btn btn-ghost">
      <i class="fa-solid fa-tv"></i> Display
    </a>
  </div>
</div>

<div class="ktms-console">
  @include('admin.ktms.live._board', ['match' => $match])

  <div class="ktms-console-grid">
    <div>
      {{-- Game clock --}}
      <div class="panel-card">
        <h4>Game Clock</h4>
        <div class="ktms-btn-row" style="margin-bottom:12px">
          <button type="button" class="btn btn-success btn-ktms-xl" data-act="game/start">
            <i class="fa-solid fa-play"></i> Start
          </button>
          <button type="button" class="btn btn-amber btn-ktms-xl" data-act="game/pause">
            <i class="fa-solid fa-pause"></i> Pause
          </button>
          <button type="button" class="btn btn-ghost btn-ktms-xl" data-act="game/reset"
                  data-confirm="Reset the game clock to a full half?">
            <i class="fa-solid fa-arrows-rotate"></i> Reset
          </button>
        </div>

        <div class="ktms-btn-grid">
          <button type="button" class="btn btn-ghost" data-act="game/adjust" data-seconds="-60">−60s</button>
          <button type="button" class="btn btn-ghost" data-act="game/adjust" data-seconds="-10">−10s</button>
          <button type="button" class="btn btn-ghost" data-act="game/adjust" data-seconds="10">+10s</button>
          <button type="button" class="btn btn-ghost" data-act="game/adjust" data-seconds="60">+60s</button>
        </div>
        <div class="field-hint" style="margin-top:10px">
          Corrections are clamped to the half length, so the clock can never read
          more than {{ intdiv($match->half_duration_seconds, 60) }} minutes.
        </div>
      </div>

      {{-- Half --}}
      <div class="panel-card">
        <h4>Half</h4>
        <div class="ktms-btn-row">
          <button type="button" class="btn btn-ghost btn-ktms-xl" data-act="half" data-half="1"
                  data-confirm="Set up the first half? Both sides return to full strength and the clock resets.">
            First Half
          </button>
          <button type="button" class="btn btn-ghost btn-ktms-xl" data-act="half" data-half="2"
                  data-confirm="Set up the second half? Both sides return to full strength and the clock resets.">
            Second Half
          </button>
        </div>
        <div class="field-hint" style="margin-top:10px">
          A new half gives a full game clock, a fresh raid clock, and both sides
          back at {{ $match->playersPerSide() }} on the mat.
        </div>
      </div>
    </div>

    <div>
      {{-- Raid clock --}}
      <div class="ktms-raid-clock" style="margin-bottom:14px">
        <div class="ktms-tile-label">Raid Clock</div>
        <div class="ktms-raid-value" data-raid-clock>
          {{ \App\Models\Ktms\GameMatch::formatClock($match->effectiveRaidClockRemaining()) }}
        </div>
        <div class="ktms-raid-bar"><span data-raid-bar style="width:100%"></span></div>
      </div>

      <div class="ktms-dod" data-dod>
        <i class="fa-solid fa-triangle-exclamation"></i> Next raid is do-or-die
      </div>

      <div class="panel-card">
        <h4>Raid Clock</h4>
        <button type="button" class="btn btn-primary btn-ktms-xl" style="width:100%;margin-bottom:8px"
                data-act="raid/reset" data-auto-start="1">
          <i class="fa-solid fa-rotate-right"></i> New Raid ({{ $match->raid_duration_seconds }}s)
        </button>
        <div class="ktms-btn-grid">
          <button type="button" class="btn btn-success" data-act="raid/start"><i class="fa-solid fa-play"></i> Start</button>
          <button type="button" class="btn btn-amber" data-act="raid/pause"><i class="fa-solid fa-pause"></i> Pause</button>
          <button type="button" class="btn btn-ghost" data-act="raid/reset"><i class="fa-solid fa-stop"></i> Reset</button>
        </div>
      </div>

      {{-- Court counts --}}
      <div class="panel-card">
        <h4>Players On The Mat</h4>
        @foreach([['home', $match->homeTeam], ['away', $match->awayTeam]] as [$side, $team])
          <div class="field">
            <label>{{ $team?->name }}</label>
            <div class="ktms-btn-grid">
              @for($i = 0; $i <= $match->playersPerSide(); $i++)
                <button type="button" class="btn btn-ghost btn-sm"
                        data-act="court" data-team="{{ $team?->id }}" data-count="{{ $i }}">{{ $i }}</button>
              @endfor
            </div>
          </div>
        @endforeach
        <div class="field-hint">
          Sets the count directly. The scoring console changes it automatically as
          points are recorded, so this is for corrections.
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
  var base = @json(url('admin/ktms/matches/' . $match->id . '/timer'));

  var session = window.KtmsLive.session({
    stateUrl: @json(route('admin.ktms.matches.state', $match)),
    initialState: @json($snapshot),
    pollInterval: 2000,
  });

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

    var dod = document.querySelector('[data-dod]');
    if (dod) { dod.classList.toggle('is-on', Boolean(state.next_raid_is_do_or_die)); }

    paintTimeline(state.timeline);
  });

  /* ── Smooth clocks between polls ───────────────────────── */
  session.onTick(function (s) {
    window.KtmsLive.setText('[data-game-clock]', s.gameClock.formatted());

    var raid = document.querySelector('[data-raid-clock]');
    if (raid) {
      var value = s.raidClock.value();
      raid.textContent = window.KtmsLive.formatClock(value);
      // Red for the closing stretch of the raid.
      raid.classList.toggle('is-low', value <= 10);
    }

    var bar = document.querySelector('[data-raid-bar]');
    if (bar) { bar.style.width = (s.raidClock.fraction() * 100).toFixed(1) + '%'; }
  });

  function paintPips(selector, on, total) {
    var host = document.querySelector(selector);
    if (!host) { return; }

    // Rebuild only when the squad size changed; otherwise just toggle.
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

  // Timeline descriptions are server-built from squad and player names, so they
  // are escaped before being written as HTML.
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ── Buttons ───────────────────────────────────────────── */
  document.querySelectorAll('[data-act]').forEach(function (button) {
    button.addEventListener('click', function () {
      var confirmText = button.dataset.confirm;
      if (confirmText && !window.confirm(confirmText)) { return; }

      var body = {};
      if (button.dataset.seconds)   { body.seconds = Number(button.dataset.seconds); }
      if (button.dataset.half)      { body.half = Number(button.dataset.half); }
      if (button.dataset.autoStart) { body.auto_start = true; }
      if (button.dataset.team)      { body.team_id = Number(button.dataset.team); }
      if (button.dataset.count !== undefined && button.dataset.count !== '') {
        body.count = Number(button.dataset.count);
      }

      session.act(base + '/' + button.dataset.act, body);
    });
  });

  session.start();
})();
</script>
@endpush
