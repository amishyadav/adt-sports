@extends('layouts.admin')
@section('title','Scoring Console')
@section('content')

@php
  use App\Models\Ktms\DefensiveAction;
  use App\Services\Ktms\ScoringService;

  $home = $match->homeTeam;
  $away = $match->awayTeam;

  /*
   * Squad identity for the client. The player lists themselves are NOT taken
   * from here — they come from the live snapshot, because players leave the mat
   * as the match goes on and a list captured at page load goes stale.
   *
   * Built in a @php block rather than inline in @json below: Blade parses a
   * directive's argument by matching brackets, and an arrow function returning
   * an array inside it trips that up.
   */
  $squadPayload = [];

  foreach (['home' => $home, 'away' => $away] as $side => $team) {
      $squadPayload[$side] = [
          'id' => (int) $team?->id,
          'name' => $team?->name,
          'short' => $team?->short_name ?: $team?->initials,
          'initials' => $team?->initials,
          'color' => $team?->primary_color,
      ];
  }

  // Sent across so the modal's preview cannot drift from the engine's rules.
  $rulePayload = [
      'superTackleAt' => ScoringService::SUPER_TACKLE_THRESHOLD,
      'superRaidAt' => ScoringService::SUPER_RAID_POINTS,
      'allOutPoints' => ScoringService::ALL_OUT_POINTS,
  ];

  // Super tackle is left out: the engine decides that one from the court count.
  $tackleTypes = collect(DefensiveAction::ACTION_TYPES)
      ->reject(fn ($label, $value) => $value === DefensiveAction::TYPE_SUPER_TACKLE);
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
    <a href="{{ route('admin.ktms.matches.show', $match) }}" class="btn btn-ghost">
      <i class="fa-solid fa-arrow-left"></i> Back to match
    </a>
  </div>
</div>

<div class="ktms-console">
  @include('admin.ktms.live._board', ['match' => $match])

  {{-- The engine records do-or-die as a flag; it does not send the raider out
       by itself, so this says what is true rather than what the rulebook does. --}}

  <div class="ktms-console-grid">
    <div>
      {{-- Record a raid --}}
      <div class="panel-card" id="raidPanel">
        <h4>Record A Raid</h4>

        <div class="field" style="margin-bottom:0">
          <label>Tap the raider</label>
          <div data-raider-picks>
            @include('admin.ktms.live._squad-picks', ['field' => 'raider_id'])
          </div>
          <div class="field-hint">
            That opens the raid sheet with the other squad listed, where you mark
            who was touched or who made the tackle. The raiding side is taken
            from whoever you tap, so the two can never disagree.
          </div>
        </div>
      </div>

      {{-- Standalone defensive action --}}
      <div class="panel-card" id="defensePanel">
        <h4>Log A Defensive Action</h4>
        <div class="field-hint" style="margin-bottom:12px">
          For a tackle recorded on its own, outside a raid.
        </div>

        <div class="field">
          <label>Defender</label>
          @include('admin.ktms.live._squad-picks', ['field' => 'defender_id'])
        </div>

        <div class="field">
          <label>Action</label>
          <div class="ktms-opts">
            @foreach($tackleTypes as $value => $label)
              @php $isDefault = $value === DefensiveAction::TYPE_TACKLE; @endphp
              <label class="ktms-pick {{ $isDefault ? 'is-picked' : '' }}">
                <input type="radio" name="defense_action_type" value="{{ $value }}"
                       data-default="{{ $isDefault ? '1' : '0' }}" {{ $isDefault ? 'checked' : '' }}>
                <span class="ktms-pick-name">{{ $label }}</span>
              </label>
            @endforeach
          </div>
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
          Worth one point, or two when that side is down to {{ ScoringService::SUPER_TACKLE_THRESHOLD }} or fewer.
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

      {{-- Officiating --}}
      <div class="panel-card">
        <h4>Substitutions &amp; Sanctions</h4>
        <div class="ktms-official-grid">
          <button type="button" class="ktms-official" data-official="substitution">
            <i class="fa-solid fa-right-left"></i> Substitution
          </button>
          <button type="button" class="ktms-official" data-official="injury">
            <i class="fa-solid fa-kit-medical ktms-tone-red"></i> Injury
          </button>
          <button type="button" class="ktms-official" data-official="warning">
            <i class="fa-solid fa-bell ktms-tone-amber"></i> Warning
          </button>
          <button type="button" class="ktms-official" data-official="card_green">
            <i class="fa-solid fa-square ktms-tone-green"></i> Green card
          </button>
          <button type="button" class="ktms-official" data-official="card_yellow">
            <i class="fa-solid fa-square ktms-tone-amber"></i> Yellow card
          </button>
          <button type="button" class="ktms-official" data-official="card_red">
            <i class="fa-solid fa-square ktms-tone-red"></i> Red card
          </button>
          <button type="button" class="ktms-official ktms-official-wide" data-official="technical">
            <i class="fa-solid fa-gavel ktms-tone-amber"></i> Technical point
          </button>
        </div>
        <div class="field-hint" style="margin-top:10px">
          A yellow or red card takes the player off the mat and the side plays
          short — a suspension cannot be substituted. No point is given with a
          card; record one as a technical point if the competition calls for it.
        </div>
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

{{--
  The raid sheet.

  Opens on tapping a raider, lists the defending squad, and asks the one
  question that matters: what happened. Each outcome reveals only its own
  controls, and the footer shows the consequence before it is committed.
--}}
<div class="ktms-modal" id="raidSheet" hidden>
  <div class="ktms-modal-backdrop" data-sheet-close></div>

  <div class="ktms-modal-card" role="dialog" aria-modal="true" aria-labelledby="raidSheetTitle">
    <div class="ktms-modal-head">
      <span class="ktms-pick-no ktms-modal-no" data-sheet-no>—</span>
      <div style="min-width:0">
        <div class="ktms-modal-title" id="raidSheetTitle" data-sheet-name>Raider</div>
        <div class="ktms-modal-sub">
          <span data-sheet-raiding>—</span>
          <i class="fa-solid fa-arrow-right" style="font-size:9px;opacity:.6"></i>
          <span data-sheet-defending>—</span>
        </div>
      </div>
      <button type="button" class="ktms-modal-x" data-sheet-close aria-label="Close">&times;</button>
    </div>

{{--    <div class="ktms-modal-dod" data-sheet-dod hidden>--}}
{{--      <i class="fa-solid fa-triangle-exclamation"></i>--}}
{{--      Do-or-die raid — this side must score--}}
{{--    </div>--}}

    <div class="ktms-modal-body">
      <div class="field">
        <label>What happened?</label>
        <div class="ktms-outcomes">
          <button type="button" class="ktms-outcome" data-outcome="successful">
            <i class="fa-solid fa-check"></i>
            <span>Scored</span>
            <small>Touched a defender or took the bonus</small>
          </button>
          <button type="button" class="ktms-outcome" data-outcome="unsuccessful">
            <i class="fa-solid fa-hand"></i>
            <span>Tackled</span>
            <small>The defence stopped him</small>
          </button>
          <button type="button" class="ktms-outcome" data-outcome="empty">
            <i class="fa-solid fa-circle-minus"></i>
            <span>Empty</span>
            <small>Back with nothing</small>
          </button>
        </div>
      </div>

      {{-- Scored --}}
      <div data-step="successful" hidden>
        <div class="field">
          <label>Who did he touch?</label>
          <div data-sheet-touched></div>
          <div class="field-hint">
            One point each, and each one leaves the mat. Tap again to undo.
          </div>
        </div>

        <div class="field">
          <label class="ktms-pick ktms-pick-wide" data-sheet-bonus-label>
            <input type="checkbox" data-sheet-bonus>
            <i class="fa-solid fa-star" style="font-size:10px"></i>
            <span class="ktms-pick-name">Bonus point taken</span>
          </label>
        </div>

        <details class="ktms-modal-more">
          <summary>Defenders who tried and were beaten <span class="ktms-modal-opt">optional</span></summary>
          <div style="padding-top:10px">
            <div data-sheet-beaten></div>
            <div class="field-hint">
              Recorded as a failed defence against them. They stay on the mat
              unless you also marked them as touched.
            </div>
          </div>
        </details>
      </div>

      {{-- Tackled --}}
      <div data-step="unsuccessful" hidden>
        <div class="field">
          <label>Who made the tackle?</label>
          <div data-sheet-tacklers></div>
          <div class="field-hint">
            Tap more than one for a chain tackle — the points are shared between them.
          </div>
        </div>

        <div class="field">
          <label>Tackle type</label>
          <div class="ktms-opts" data-sheet-type>
            @foreach($tackleTypes as $value => $label)
              @php $isDefault = $value === DefensiveAction::TYPE_TACKLE; @endphp
              <label class="ktms-pick {{ $isDefault ? 'is-picked' : '' }}">
                <input type="radio" name="sheet_action_type" value="{{ $value }}"
                       data-default="{{ $isDefault ? '1' : '0' }}" {{ $isDefault ? 'checked' : '' }}>
                <span class="ktms-pick-name">{{ $label }}</span>
              </label>
            @endforeach
          </div>
          <div class="field-hint">
            A super tackle is applied automatically when the defence is down to
            {{ ScoringService::SUPER_TACKLE_THRESHOLD }} or fewer.
          </div>
        </div>

        <div class="field">
          <label class="ktms-pick ktms-pick-wide">
            <input type="checkbox" data-sheet-bonus-tackled>
            <i class="fa-solid fa-star" style="font-size:10px"></i>
            <span class="ktms-pick-name">He still took the bonus</span>
          </label>
        </div>
      </div>

      {{-- Empty --}}
      <div data-step="empty" hidden>
        <div class="field-hint" style="padding:4px 0">
          Nobody scored and nobody leaves the mat. Two empty raids in a row by
          the same side make the next one do-or-die.
        </div>
        <div class="ktms-modal-note" data-sheet-empty-dod hidden>
          <i class="fa-solid fa-circle-info"></i>
          This was a do-or-die raid. If he failed to score he is out — record it
          as <strong>Tackled</strong> instead so he leaves the mat.
        </div>
      </div>
    </div>

    <div class="ktms-modal-foot">
      <div class="ktms-modal-result" data-sheet-summary>Pick an outcome.</div>
      <div class="ktms-btn-row">
        <button type="button" class="btn btn-ghost" data-sheet-close>Cancel</button>
        <button type="button" class="btn btn-primary" data-sheet-confirm disabled>
          <i class="fa-solid fa-check"></i> Record Raid
        </button>
      </div>
    </div>
  </div>
</div>

{{--
  The officiating sheet.

  One sheet for all seven actions rather than seven dialogs: they ask the same
  questions in different combinations — which squad, which player, who replaces
  him — so the markup is shared and a mode decides which parts are shown and
  what the labels say. The mode table lives in the script below, next to the
  validation that depends on it.
--}}
<div class="ktms-modal" id="officialSheet" hidden>
  <div class="ktms-modal-backdrop" data-official-close></div>

  <div class="ktms-modal-card" role="dialog" aria-modal="true" aria-labelledby="officialSheetTitle">
    <div class="ktms-modal-head">
      <span class="ktms-pick-no ktms-modal-no" data-official-badge aria-hidden="true">
        <i class="fa-solid fa-gavel"></i>
      </span>
      <div style="min-width:0">
        <div class="ktms-modal-title" id="officialSheetTitle" data-official-title>Officiating</div>
        <div class="ktms-modal-sub" data-official-sub>&mdash;</div>
      </div>
      <button type="button" class="ktms-modal-x" data-official-close aria-label="Close">&times;</button>
    </div>

    <div class="ktms-modal-body">
      <div class="field">
        <label data-official-squad-label>Which squad?</label>
        <div class="ktms-opts" data-official-squads>
          @foreach([['home', $home], ['away', $away]] as [$side, $team])
            <button type="button" class="ktms-pick" data-official-side="{{ $side }}">
              <span class="ktms-pick-name">{{ $team?->name ?: ucfirst($side) }}</span>
            </button>
          @endforeach
        </div>
      </div>

      {{-- The player the decision is about. --}}
      <div class="field" data-ostep="player" hidden>
        <label data-official-player-label>Player</label>
        <div data-official-player-list></div>
        <div class="field-hint" data-official-player-hint></div>
      </div>

      {{-- Only substitutions and injuries bring somebody on. --}}
      <div class="field" data-ostep="bench" hidden>
        <label data-official-bench-label>Who comes on?</label>
        <div data-official-bench-list></div>
        <div class="field-hint" data-official-bench-hint></div>
      </div>

      {{-- Technical points only. --}}
      <div class="field" data-ostep="points" hidden>
        <label>How many points?</label>
        <div class="ktms-opts" data-official-points>
          @foreach([1, 2, 3] as $value)
            <button type="button" class="ktms-pick {{ $value === 1 ? 'is-picked' : '' }}"
                    data-official-pts="{{ $value }}">
              <span class="ktms-pick-name">{{ $value }} {{ $value === 1 ? 'point' : 'points' }}</span>
            </button>
          @endforeach
        </div>
      </div>

      <div class="field" data-ostep="note" hidden>
        <label>Note <span class="ktms-modal-opt">optional</span></label>
        <input type="text" maxlength="160" data-official-note
               placeholder="The reason, as it should read on the timeline">
        <div class="field-hint">Added to the end of the timeline entry. 160 characters.</div>
      </div>

      <div class="ktms-modal-note" data-official-warn hidden>
        <i class="fa-solid fa-circle-info"></i>
        <span data-official-warn-text></span>
      </div>
    </div>

    <div class="ktms-modal-foot">
      <div class="ktms-modal-result" data-official-summary>&mdash;</div>
      <div class="ktms-btn-row">
        <button type="button" class="btn btn-ghost" data-official-close>Cancel</button>
        <button type="button" class="btn btn-primary" data-official-confirm disabled>
          <i class="fa-solid fa-check"></i> <span data-official-confirm-text>Record</span>
        </button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/ktms-live.js') }}"></script>
<script>
(function () {
  var base = @json(url('admin/ktms/matches/' . $match->id . '/scoring'));
  var officialsBase = @json(url('admin/ktms/matches/' . $match->id . '/officials'));
  var homeId = @json((int) $match->home_team_id);
  var squads = @json($squadPayload);
  var rules = @json($rulePayload);

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

    document.querySelectorAll('[data-side]').forEach(function (node) {
      var side = node.dataset.side;
      node.classList.toggle('ktms-raiding', Boolean(state.teams[side] && state.teams[side].is_raiding));
    });

    var dod = document.querySelector('[data-dod]');
    if (dod) { dod.classList.toggle('is-on', Boolean(state.next_raid_is_do_or_die)); }

    var dodTeam = document.querySelector('[data-dod-team]');
    if (dodTeam) {
      var raiding = sideOfTeam(state.match.raiding_team_id);
      dodTeam.textContent = raiding ? squads[raiding].name : 'the raiding side';
    }

    dimPlayersOffCourt(state);
    paintTimeline(state.timeline);

    // Keep an open sheet honest if the match moved on underneath it.
    if (sheetOpen()) { refreshSheetLists(); }
    if (officialOpen()) { refreshOfficialLists(); }
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

  /*
   * Grey out anyone who is off the mat.
   *
   * The pick grids are rendered once, server-side, but players leave the mat as
   * the match goes on — so without this the console keeps offering people who
   * are sitting out.
   */
  function dimPlayersOffCourt(state) {
    var onCourtIds = {};

    ['home', 'away'].forEach(function (side) {
      ((state.teams[side] && state.teams[side].lineup) || []).forEach(function (p) {
        if (p.is_on_court) { onCourtIds[p.id] = true; }
      });
    });

    document.querySelectorAll('#raidPanel .ktms-pick input, #defensePanel .ktms-pick input')
      .forEach(function (input) {
        if (!input.value || isNaN(Number(input.value))) { return; }

        var out = !onCourtIds[Number(input.value)];
        input.disabled = out;
        input.closest('.ktms-pick').classList.toggle('is-off', out);
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
        + '<span class="ktms-event-icon"><i class="fa-solid ' + escapeHtml(e.icon)
        + ' ' + toneClass(e.tone) + '"></i></span>'
        + '<span class="ktms-event-text">' + escapeHtml(e.description) + '</span>'
        + '<span class="ktms-event-pts">' + (e.points ? '+' + e.points : '') + '</span>'
        + '</div>';
    }).join('');
  }

  // Descriptions and names are server data, so escape before writing as HTML.
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /*
   * The colour an event is drawn in. Whitelisted rather than interpolated,
   * because it is written straight into a class attribute.
   */
  var TONES = ['green', 'amber', 'red', 'info', 'brand'];

  function toneClass(tone) {
    return TONES.indexOf(String(tone || '')) === -1 ? 'ktms-tone-brand' : 'ktms-tone-' + tone;
  }

  /* Validated to #rrggbb server side; re-checked because it goes into a style
     attribute. */
  function safeColor(value) {
    return /^#[0-9A-Fa-f]{6}$/.test(String(value || '')) ? value : '#4A4640';
  }

  function sideOfTeam(teamId) {
    if (!teamId) { return null; }

    return Number(teamId) === homeId ? 'home' : 'away';
  }

  /** A side's whole squad for this match, read from the live snapshot. */
  function lineupOf(side) {
    return (session.state && session.state.teams[side] && session.state.teams[side].lineup) || [];
  }

  /** Whoever is standing for a side, read from the live snapshot. */
  function onCourt(side) {
    return lineupOf(side).filter(function (p) { return p.is_on_court; });
  }

  /*
   * Who can actually be brought on.
   *
   * Only players whose status is 'bench'. Somebody tackled out is waiting to be
   * revived, not available — the server refuses them, and offering them here
   * would invite a rejection the operator cannot explain.
   */
  function benchOf(side) {
    return lineupOf(side).filter(function (p) {
      return !p.is_on_court && p.status === 'bench';
    });
  }

  function playerAmong(players, id) {
    return players.filter(function (p) { return p.id === id; })[0] || null;
  }

  function squadHead(side, tag) {
    var squad = squads[side];
    var colour = safeColor(squad.color);

    return '<div class="ktms-squad-head" style="--squad:' + colour + '">'
      + '<span class="ktms-crest ktms-crest-sm ktms-crest-ph" style="background:' + colour + '">'
      + escapeHtml(squad.initials) + '</span>'
      + '<span class="ktms-squad-name">' + escapeHtml(squad.name) + '</span>'
      + '<span class="ktms-squad-side">' + escapeHtml(tag) + '</span>'
      + '</div>';
  }

  /* ── The raid sheet ────────────────────────────────────── */

  var sheet = document.getElementById('raidSheet');

  // Everything the sheet is currently holding, reset on each open.
  var raid = {
    raiderId: null,
    raiderName: '',
    side: null,      // the raiding side
    defSide: null,   // the side being raided
    outcome: null,
    touched: [],
    tacklers: [],
    beaten: [],
  };

  /*
   * The bonus is read from whichever checkbox belongs to the chosen outcome
   * rather than kept in `raid`. Holding it in one place meant ticking it under
   * Scored and then switching to Tackled left the flag set while the visible
   * box was clear.
   */
  function currentBonus() {
    if (raid.outcome === 'successful') {
      return document.querySelector('[data-sheet-bonus]').checked;
    }

    if (raid.outcome === 'unsuccessful') {
      return document.querySelector('[data-sheet-bonus-tackled]').checked;
    }

    return false;
  }

  function sheetOpen() {
    return sheet && !sheet.hasAttribute('hidden');
  }

  function openSheet(playerId, teamId) {
    var side = sideOfTeam(teamId);
    if (!side) { return; }

    var player = onCourt(side).filter(function (p) { return p.id === playerId; })[0];

    if (!player) {
      session.toast('That player is not on the mat.', 'bad');
      return;
    }

    raid = {
      raiderId: playerId,
      raiderName: player.name,
      side: side,
      defSide: side === 'home' ? 'away' : 'home',
      outcome: null,
      touched: [],
      tacklers: [],
      beaten: [],
    };

    document.querySelector('[data-sheet-no]').textContent = player.jersey_number;
    document.querySelector('[data-sheet-name]').textContent = player.name;
    document.querySelector('[data-sheet-raiding]').textContent = squads[raid.side].name;
    document.querySelector('[data-sheet-defending]').textContent = squads[raid.defSide].name;

    var isDod = Boolean(session.state && session.state.next_raid_is_do_or_die);
    toggleHidden('[data-sheet-dod]', !isDod);
    toggleHidden('[data-sheet-empty-dod]', !isDod);

    // Back to the default tackle type on every open.
    document.querySelectorAll('[data-sheet-type] input').forEach(function (input) {
      input.checked = input.dataset.default === '1';
      input.closest('.ktms-pick').classList.toggle('is-picked', input.checked);
    });

    clearBonusBoxes();

    selectOutcome(null);
    refreshSheetLists();

    sheet.removeAttribute('hidden');
    document.body.classList.add('ktms-sheet-open');

    // Focus the first outcome so the sheet is usable from the keyboard.
    var first = sheet.querySelector('[data-outcome]');
    if (first) { first.focus(); }
  }

  function closeSheet() {
    if (!sheet) { return; }

    sheet.setAttribute('hidden', '');
    document.body.classList.remove('ktms-sheet-open');

    // Drop the raider selection so the grid does not look mid-action.
    document.querySelectorAll('input[name="raider_id"]').forEach(function (input) {
      input.checked = false;
      input.closest('.ktms-pick').classList.remove('is-picked');
    });
  }

  function toggleHidden(selector, hide) {
    document.querySelectorAll(selector).forEach(function (node) {
      if (hide) { node.setAttribute('hidden', ''); } else { node.removeAttribute('hidden'); }
    });
  }

  function clearBonusBoxes() {
    ['[data-sheet-bonus]', '[data-sheet-bonus-tackled]'].forEach(function (selector) {
      var box = document.querySelector(selector);
      if (!box) { return; }

      box.checked = false;
      box.closest('.ktms-pick').classList.remove('is-picked');
    });
  }

  function selectOutcome(outcome) {
    raid.outcome = outcome;

    // Switching outcome starts that outcome's bonus from clear, so a tick made
    // under one never carries silently into another.
    clearBonusBoxes();

    sheet.querySelectorAll('[data-outcome]').forEach(function (button) {
      button.classList.toggle('is-picked', button.dataset.outcome === outcome);
    });

    ['successful', 'unsuccessful', 'empty'].forEach(function (step) {
      toggleHidden('[data-step="' + step + '"]', step !== outcome);
    });

    updateSummary();
  }

  /** Redraw the three defender lists from the current snapshot. */
  function refreshSheetLists() {
    if (!raid.defSide) { return; }

    var defenders = onCourt(raid.defSide);
    var available = defenders.map(function (p) { return p.id; });

    /*
     * Drop anyone who has left the mat since they were tapped — the other
     * operator may have recorded something, and submitting an id that is no
     * longer standing would name the wrong person on the timeline.
     */
    ['touched', 'tacklers', 'beaten'].forEach(function (bucket) {
      raid[bucket] = raid[bucket].filter(function (id) { return available.indexOf(id) !== -1; });
    });

    renderSheetList('[data-sheet-touched]', defenders, raid.touched, 'touched');
    renderSheetList('[data-sheet-tacklers]', defenders, raid.tacklers, 'tacklers');
    renderSheetList('[data-sheet-beaten]', defenders, raid.beaten, 'beaten');

    updateSummary();
  }

  function renderSheetList(selector, defenders, chosen, bucket) {
    var host = document.querySelector(selector);
    if (!host) { return; }

    if (!defenders.length) {
      host.innerHTML = '<div class="field-hint" style="margin:0">'
        + escapeHtml(squads[raid.defSide].name) + ' have nobody left on the mat.</div>';
      return;
    }

    var picks = defenders.map(function (p) {
      var on = chosen.indexOf(p.id) !== -1;

      return '<button type="button" class="ktms-pick' + (on ? ' is-picked' : '') + '"'
        + ' data-bucket="' + bucket + '" data-player="' + p.id + '">'
        + '<span class="ktms-pick-no">' + escapeHtml(p.jersey_number) + '</span>'
        + '<span class="ktms-pick-name">' + escapeHtml(p.name) + '</span>'
        + '</button>';
    }).join('');

    host.innerHTML = '<div class="ktms-squad">'
      + squadHead(raid.defSide, 'defending')
      + '<div class="ktms-picks">' + picks + '</div>'
      + '</div>';
  }

  // One delegated handler, because these lists are rebuilt on every snapshot.
  sheet.addEventListener('click', function (event) {
    var pick = event.target.closest('[data-bucket]');
    if (!pick) { return; }

    var bucket = pick.dataset.bucket;
    var id = Number(pick.dataset.player);
    var list = raid[bucket];
    var at = list.indexOf(id);

    if (at === -1) { list.push(id); } else { list.splice(at, 1); }

    pick.classList.toggle('is-picked', at === -1);
    updateSummary();
  });

  sheet.querySelectorAll('[data-outcome]').forEach(function (button) {
    button.addEventListener('click', function () {
      selectOutcome(button.dataset.outcome);
    });
  });

  [['[data-sheet-bonus]', 'successful'], ['[data-sheet-bonus-tackled]', 'unsuccessful']]
    .forEach(function (pair) {
      var box = document.querySelector(pair[0]);
      if (!box) { return; }

      box.addEventListener('change', function () {
        box.closest('.ktms-pick').classList.toggle('is-picked', box.checked);
        updateSummary();
      });
    });

  sheet.querySelectorAll('[data-sheet-type] input').forEach(function (input) {
    input.addEventListener('change', function () {
      sheet.querySelectorAll('[data-sheet-type] .ktms-pick').forEach(function (label) {
        label.classList.toggle('is-picked', label.querySelector('input').checked);
      });
    });
  });

  sheet.querySelectorAll('[data-sheet-close]').forEach(function (node) {
    node.addEventListener('click', closeSheet);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && sheetOpen()) { closeSheet(); }
  });

  /**
   * Spell out what recording this will do, before it is recorded.
   *
   * The thresholds come from the server so this preview cannot drift away from
   * what the engine actually applies.
   */
  function updateSummary() {
    var out = document.querySelector('[data-sheet-summary]');
    var confirm = document.querySelector('[data-sheet-confirm]');
    if (!out || !confirm) { return; }

    var raiderSquad = raid.side ? squads[raid.side].short : '';
    var defSquad = raid.defSide ? squads[raid.defSide].short : '';
    var standing = (session.state && session.state.teams[raid.defSide])
      ? session.state.teams[raid.defSide].players_on_court
      : 0;

    var parts = [];
    var ok = false;

    var bonus = currentBonus();

    if (raid.outcome === 'successful') {
      var points = raid.touched.length + (bonus ? 1 : 0);
      ok = points > 0;

      if (!ok) {
        parts.push('Tap at least one defender, or mark the bonus.');
      } else {
        parts.push(raiderSquad + ' +' + points);

        if (points >= rules.superRaidAt) { parts.push('SUPER RAID'); }

        if (raid.touched.length) {
          parts.push(raid.touched.length
            + (raid.touched.length === 1 ? ' defender leaves' : ' defenders leave') + ' the mat');

          if (standing - raid.touched.length <= 0) {
            parts.push('ALL OUT — ' + raiderSquad + ' take ' + rules.allOutPoints + ' more');
          }
        } else {
          parts.push('bonus only, nobody leaves the mat');
        }
      }
    } else if (raid.outcome === 'unsuccessful') {
      var isSuper = standing > 0 && standing <= rules.superTackleAt;
      ok = true;

      parts.push(defSquad + ' +' + (isSuper ? 2 : 1));
      if (isSuper) { parts.push('SUPER TACKLE'); }
      if (bonus) { parts.push(raiderSquad + ' +1 bonus'); }
      parts.push(raid.raiderName + ' is out');

      if (!raid.tacklers.length) {
        parts.push('nobody credited — tap a tackler');
      }
    } else if (raid.outcome === 'empty') {
      ok = true;
      parts.push('No points, nobody leaves the mat');
    } else {
      parts.push('Pick an outcome.');
    }

    out.textContent = parts.join(' · ');
    out.classList.toggle('is-blocked', raid.outcome !== null && !ok);
    confirm.disabled = !ok;
  }

  document.querySelector('[data-sheet-confirm]').addEventListener('click', function () {
    if (!raid.raiderId || !raid.outcome) { return; }

    var button = this;
    button.disabled = true;

    session.act(base + '/raid', {
      raider_id: raid.raiderId,
      result: raid.outcome,
      touch_points: raid.outcome === 'successful' ? raid.touched.length : 0,
      is_bonus: currentBonus(),
      // On a successful raid the people touched are exactly the people out, so
      // one set of taps drives both the court count and who is named.
      defender_out_ids: raid.outcome === 'successful' ? raid.touched : [],
      tackler_ids: raid.outcome === 'unsuccessful' ? raid.tacklers : [],
      beaten_defender_ids: raid.outcome === 'successful' ? raid.beaten : [],
      action_type: pickedValue('sheet_action_type', 'tackle'),
    }).then(function (state) {
      button.disabled = false;

      if (state) { closeSheet(); } else { updateSummary(); }
    });
  });

  /* ── Opening the sheet from the raider grid ────────────── */
  document.querySelectorAll('#raidPanel input[name="raider_id"]').forEach(function (input) {
    input.addEventListener('change', function () {
      syncPickClasses(input);
      openSheet(Number(input.value), Number(input.closest('.ktms-pick').dataset.team));
    });
  });

  /* ── The standalone defensive action panel ─────────────── */
  function syncPickClasses(input) {
    if (input.type === 'radio' && input.name) {
      document.querySelectorAll('input[name="' + input.name + '"]').forEach(function (other) {
        other.closest('.ktms-pick').classList.toggle('is-picked', other.checked);
      });
    } else {
      input.closest('.ktms-pick').classList.toggle('is-picked', input.checked);
    }
  }

  document.querySelectorAll('#defensePanel .ktms-pick input').forEach(function (input) {
    input.addEventListener('change', function () { syncPickClasses(input); });
  });

  /** The selected value of a radio group, or the fallback if nothing is set. */
  function pickedValue(name, fallback) {
    var picked = document.querySelector('input[name="' + name + '"]:checked');

    return picked ? picked.value : fallback;
  }

  document.querySelectorAll('[data-defense]').forEach(function (button) {
    button.addEventListener('click', function () {
      var picked = document.querySelector('input[name="defender_id"]:checked');

      if (!picked) {
        session.toast('Pick a defender first.', 'bad');
        return;
      }

      session.act(base + '/defense', {
        defender_id: Number(picked.value),
        action_type: pickedValue('defense_action_type', 'tackle'),
        is_successful: button.dataset.defense === '1',
      }).then(function (state) {
        if (state) {
          picked.checked = false;
          picked.closest('.ktms-pick').classList.remove('is-picked');
        }
      });
    });
  });

  /* ── The officiating sheet ─────────────────────────────── */

  var officialSheet = document.getElementById('officialSheet');

  /*
   * What each of the seven actions asks for, and what it does.
   *
   * One table rather than seven handlers: the questions are the same in
   * different combinations, and keeping the labels beside the rules they
   * describe means a wording change cannot drift away from the behaviour.
   *
   * `pool` is who may be picked — 'court' for the actions that only make sense
   * against a player who is standing, 'squad' for a warning or green card,
   * which the server accepts for anyone in the squad.
   */
  var OFFICIALS = {
    substitution: {
      title: 'Substitution',
      sub: 'One off, one on — the headcount holds',
      icon: 'fa-right-left', tone: 'info',
      endpoint: '/substitution', confirm: 'Record Substitution',
      squadLabel: 'Which squad is substituting?',
      playerLabel: 'Who comes off?', pool: 'court',
      playerHint: 'Only players on the mat can be taken off.',
      bench: 'required', benchLabel: 'Who comes on?',
      benchHint: 'Only players on the bench. Anyone tackled out has to be revived, not substituted.',
    },
    injury: {
      title: 'Injury',
      sub: 'Off the mat, with or without a replacement',
      icon: 'fa-kit-medical', tone: 'red',
      endpoint: '/injury', confirm: 'Record Injury',
      squadLabel: 'Which squad?',
      playerLabel: 'Who is injured?', pool: 'court',
      bench: 'optional', benchLabel: 'Who comes on in their place?',
      benchHint: 'Leave this empty if there is nobody to bring on — the side then plays a player short.',
    },
    warning: {
      title: 'Warning',
      sub: 'Recorded on the timeline only',
      icon: 'fa-bell', tone: 'amber',
      endpoint: '/sanction', type: 'warning', confirm: 'Record Warning',
      squadLabel: 'Which squad?',
      playerLabel: 'Who is being warned?', pool: 'squad',
      playerHint: 'Anyone in the squad, on the mat or not.',
      note: true,
    },
    card_green: {
      title: 'Green card',
      sub: 'A first caution — nobody leaves the mat',
      icon: 'fa-square', tone: 'green',
      endpoint: '/sanction', type: 'card_green', confirm: 'Record Green Card',
      squadLabel: 'Which squad?',
      playerLabel: 'Who is being carded?', pool: 'squad',
      playerHint: 'Anyone in the squad, on the mat or not.',
      note: true,
    },
    card_yellow: {
      title: 'Yellow card',
      sub: 'A suspension — the player leaves the mat',
      icon: 'fa-square', tone: 'amber',
      endpoint: '/sanction', type: 'card_yellow', confirm: 'Record Yellow Card',
      squadLabel: 'Which squad?',
      playerLabel: 'Who is being carded?', pool: 'court',
      note: true,
      warn: 'A suspended player cannot be replaced, so the side plays short. No point is '
        + 'given with the card — record one as a technical point if the rules call for it.',
    },
    card_red: {
      title: 'Red card',
      sub: 'An expulsion — out for the rest of the match',
      icon: 'fa-square', tone: 'red',
      endpoint: '/sanction', type: 'card_red', confirm: 'Record Red Card',
      squadLabel: 'Which squad?',
      playerLabel: 'Who is being sent off?', pool: 'court',
      note: true,
      warn: 'The side plays short for the rest of the match. An expelled player does not '
        + 'come back on at the next all-out either. No point is given with the card.',
    },
    technical: {
      title: 'Technical point',
      sub: 'Awarded to the side that did not offend',
      icon: 'fa-gavel', tone: 'amber',
      endpoint: '/technical', confirm: 'Award Point',
      squadLabel: 'Which side receives the point?',
      points: true, note: true,
    },
  };

  // Reset on every open, so nothing carries from the last decision.
  var official = { mode: null, side: null, playerId: null, benchId: null, points: 1 };

  function officialOpen() {
    return officialSheet && !officialSheet.hasAttribute('hidden');
  }

  function officialText(selector, value) {
    var node = officialSheet.querySelector(selector);
    if (node) { node.textContent = value == null ? '' : value; }
  }

  function openOfficial(mode) {
    var cfg = OFFICIALS[mode];
    if (!cfg) { return; }

    official = { mode: mode, side: null, playerId: null, benchId: null, points: 1 };

    /* Icons and tones come from the table above, never from the server, so they
       are safe to write as markup. */
    var badge = officialSheet.querySelector('[data-official-badge]');
    badge.className = 'ktms-pick-no ktms-modal-no ' + toneClass(cfg.tone);
    badge.innerHTML = '<i class="fa-solid ' + cfg.icon + '"></i>';

    officialText('[data-official-title]', cfg.title);
    officialText('[data-official-sub]', cfg.sub);
    officialText('[data-official-squad-label]', cfg.squadLabel);
    officialText('[data-official-player-label]', cfg.playerLabel || 'Player');
    officialText('[data-official-player-hint]', cfg.playerHint || '');
    officialText('[data-official-bench-label]', cfg.benchLabel || 'Who comes on?');
    officialText('[data-official-bench-hint]', cfg.benchHint || '');
    officialText('[data-official-confirm-text]', cfg.confirm);
    officialText('[data-official-warn-text]', cfg.warn || '');

    toggleHidden('[data-ostep="player"]', !cfg.pool);
    toggleHidden('[data-ostep="bench"]', !cfg.bench);
    toggleHidden('[data-ostep="points"]', !cfg.points);
    toggleHidden('[data-ostep="note"]', !cfg.note);
    toggleHidden('[data-official-warn]', !cfg.warn);
    toggleHidden('[data-official-player-hint]', !cfg.playerHint);
    toggleHidden('[data-official-bench-hint]', !cfg.benchHint);

    officialSheet.querySelectorAll('[data-official-side], [data-official-pts]')
      .forEach(function (button) {
        var isDefault = button.dataset.officialPts === '1';
        button.classList.toggle('is-picked', isDefault);
      });

    var note = officialSheet.querySelector('[data-official-note]');
    if (note) { note.value = ''; }

    refreshOfficialLists();

    officialSheet.removeAttribute('hidden');
    document.body.classList.add('ktms-sheet-open');

    var first = officialSheet.querySelector('[data-official-side]');
    if (first) { first.focus(); }
  }

  function closeOfficial() {
    if (!officialSheet) { return; }

    officialSheet.setAttribute('hidden', '');
    document.body.classList.remove('ktms-sheet-open');
    official.mode = null;
  }

  /**
   * Redraw both player lists from the current snapshot.
   *
   * Called on every poll while the sheet is open: the other operator may have
   * recorded something, and a list captured when the sheet opened would offer
   * people who have since left the mat.
   */
  function refreshOfficialLists() {
    var cfg = official.mode ? OFFICIALS[official.mode] : null;
    if (!cfg) { return; }

    var people = [];
    var bench = [];

    if (official.side) {
      people = cfg.pool === 'squad' ? lineupOf(official.side) : onCourt(official.side);
      bench = benchOf(official.side);
    }

    /* Drop a selection that is no longer offered, rather than submitting an id
       the server will reject. */
    if (official.playerId && !playerAmong(people, official.playerId)) { official.playerId = null; }
    if (official.benchId && !playerAmong(bench, official.benchId)) { official.benchId = null; }

    if (cfg.pool) {
      renderOfficialList('[data-official-player-list]', people, official.playerId, 'player',
        'nobody available');
    }

    if (cfg.bench) {
      renderOfficialList('[data-official-bench-list]', bench, official.benchId, 'bench',
        'nobody on the bench');
    }

    updateOfficialSummary();
  }

  function renderOfficialList(selector, players, chosenId, bucket, emptyWord) {
    var host = officialSheet.querySelector(selector);
    if (!host) { return; }

    if (!official.side) {
      host.innerHTML = '<div class="ktms-official-empty">Pick a squad first.</div>';
      return;
    }

    if (!players.length) {
      host.innerHTML = '<div class="ktms-official-empty">'
        + escapeHtml(squads[official.side].name) + ' have ' + escapeHtml(emptyWord) + '.</div>';
      return;
    }

    var picks = players.map(function (p) {
      var on = p.id === chosenId;

      /* Dimmed but still clickable: a warning may be given to someone off the
         mat, and saying so beats hiding them. */
      return '<button type="button" class="ktms-pick' + (on ? ' is-picked' : '')
        + (p.is_on_court ? '' : ' is-off') + '"'
        + ' data-obucket="' + bucket + '" data-oplayer="' + p.id + '">'
        + '<span class="ktms-pick-no">' + escapeHtml(p.jersey_number) + '</span>'
        + '<span class="ktms-pick-name">' + escapeHtml(p.name) + '</span>'
        + '</button>';
    }).join('');

    host.innerHTML = '<div class="ktms-squad">'
      + squadHead(official.side, official.side)
      + '<div class="ktms-picks">' + picks + '</div>'
      + '</div>';
  }

  /** Spell out what confirming will do, before it is recorded. */
  function updateOfficialSummary() {
    var out = officialSheet.querySelector('[data-official-summary]');
    var confirm = officialSheet.querySelector('[data-official-confirm]');
    var cfg = official.mode ? OFFICIALS[official.mode] : null;
    if (!out || !confirm || !cfg) { return; }

    var parts = [];
    var ok = false;

    if (!official.side) {
      parts.push('Pick a squad.');
    } else {
      // A squad with neither a short name nor initials still has to read as
      // something, so the full name is the fallback.
      var squad = { short: squads[official.side].short || squads[official.side].name };

      var standing = (session.state && session.state.teams[official.side])
        ? session.state.teams[official.side].players_on_court
        : 0;

      var pool = cfg.pool === 'squad' ? lineupOf(official.side) : onCourt(official.side);
      var player = official.playerId ? playerAmong(pool, official.playerId) : null;
      var replacement = official.benchId ? playerAmong(benchOf(official.side), official.benchId) : null;

      if (cfg.points) {
        ok = true;
        parts.push(squad.short + ' +' + official.points
          + (official.points === 1 ? ' technical point' : ' technical points'));
      } else if (!player) {
        parts.push('Pick a player.');
      } else if (official.mode === 'substitution') {
        ok = Boolean(replacement);

        if (!ok) {
          parts.push('Pick who comes on for ' + player.name + '.');
        } else {
          parts.push(squad.short + ': ' + replacement.name + ' on for ' + player.name);
          parts.push('still ' + standing + ' on the mat');
        }
      } else if (official.mode === 'injury') {
        ok = true;
        parts.push(squad.short + ': ' + player.name + ' off injured');

        if (replacement) {
          parts.push(replacement.name + ' on, still ' + standing + ' on the mat');
        } else {
          parts.push('no replacement — ' + squad.short + ' down to ' + Math.max(standing - 1, 0));
        }
      } else {
        // A warning or one of the three cards.
        ok = true;
        parts.push(cfg.title + ' to ' + player.name + ' (' + squad.short + ')');

        if (official.mode === 'card_yellow' || official.mode === 'card_red') {
          parts.push('off the mat, ' + squad.short + ' down to ' + Math.max(standing - 1, 0));

          if (standing - 1 <= 0) {
            parts.push('that leaves nobody standing — revive the side');
          }
        } else {
          parts.push('no points, nobody leaves the mat');
        }
      }
    }

    out.textContent = parts.join(' · ');
    out.classList.toggle('is-blocked', !ok);
    confirm.disabled = !ok;
  }

  /* One delegated handler: the player lists are rebuilt on every snapshot. */
  officialSheet.addEventListener('click', function (event) {
    var side = event.target.closest('[data-official-side]');

    if (side) {
      // Changing squad invalidates both picks, which belonged to the other one.
      official.side = side.dataset.officialSide;
      official.playerId = null;
      official.benchId = null;

      officialSheet.querySelectorAll('[data-official-side]').forEach(function (button) {
        button.classList.toggle('is-picked', button === side);
      });

      refreshOfficialLists();
      return;
    }

    var pts = event.target.closest('[data-official-pts]');

    if (pts) {
      official.points = Number(pts.dataset.officialPts);

      officialSheet.querySelectorAll('[data-official-pts]').forEach(function (button) {
        button.classList.toggle('is-picked', button === pts);
      });

      updateOfficialSummary();
      return;
    }

    var pick = event.target.closest('[data-oplayer]');
    if (!pick) { return; }

    var id = Number(pick.dataset.oplayer);
    var bucket = pick.dataset.obucket;

    // Tapping the chosen player again clears it, which is how the bench pick
    // is emptied again after an injury with no replacement.
    official[bucket === 'bench' ? 'benchId' : 'playerId'] =
      official[bucket === 'bench' ? 'benchId' : 'playerId'] === id ? null : id;

    refreshOfficialLists();
  });

  officialSheet.querySelectorAll('[data-official-close]').forEach(function (node) {
    node.addEventListener('click', closeOfficial);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && officialOpen()) { closeOfficial(); }
  });

  document.querySelectorAll('[data-official]').forEach(function (button) {
    button.addEventListener('click', function () {
      openOfficial(button.dataset.official);
    });
  });

  officialSheet.querySelector('[data-official-confirm]').addEventListener('click', function () {
    var cfg = official.mode ? OFFICIALS[official.mode] : null;
    if (!cfg || !official.side) { return; }

    var button = this;
    var note = officialSheet.querySelector('[data-official-note]');
    var body = {};

    if (cfg.endpoint === '/substitution') {
      body.out_player_id = official.playerId;
      body.in_player_id = official.benchId;
    } else if (cfg.endpoint === '/injury') {
      body.player_id = official.playerId;
      body.replacement_id = official.benchId;
    } else if (cfg.endpoint === '/sanction') {
      body.type = cfg.type;
      body.player_id = official.playerId;
      body.note = note ? note.value : null;
    } else {
      body.team_id = squads[official.side].id;
      body.points = official.points;
      body.note = note ? note.value : null;
    }

    button.disabled = true;

    session.act(officialsBase + cfg.endpoint, body).then(function (state) {
      button.disabled = false;

      if (state) { closeOfficial(); } else { updateOfficialSummary(); }
    });
  });

  /* ── Everything else ───────────────────────────────────── */
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
