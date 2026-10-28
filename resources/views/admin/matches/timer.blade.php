@extends('layouts.admin')
@section('title','Timer · '.$match->title)
@section('content')

@php
  $sides = [1 => $match->sideData(1), 2 => $match->sideData(2)];
  $resetMinutes = 20;

  // Court order for the headline. The raid panels stay in team order and are
  // re-ordered visually with CSS, so Raid In always posts for its own team.
  [$courtLeft, $courtRight] = $match->sides();

  /*
   * Countdown bed played on Raid In, tried in order.
   *
   * The 16-bit file comes first because Chrome will not decode 24-bit PCM WAV,
   * which is how the original was exported. Regenerate it after dropping in a
   * new recording:
   *
   *     php artisan raid:audio-convert "Your File.wav"
   */
  $raidAudioSources = array_values(array_filter([
      '/audio/raid-countdown.wav',
      '/audio/' . rawurlencode('Kabbadi Countodwn Final.wav'),
  ], fn ($path) => is_file(public_path(rawurldecode(ltrim($path, '/'))))));
@endphp

<div class="tc-title">Timer Controller</div>

<div class="tc-sublinks">
  <a href="{{ route('admin.matches.scoreboard',$match) }}" class="btn btn-success btn-sm"><i class="fa-solid fa-table-list"></i> Scoreboard</a>
  <a href="{{ route('admin.matches.screen',$match) }}" target="_blank" class="btn btn-danger btn-sm"><i class="fa-solid fa-tv"></i> Main Screen</a>
  <a href="{{ route('admin.matches.live',$match) }}" target="_blank" class="btn btn-ghost btn-sm"><i class="fa-solid fa-bolt"></i> Live Score</a>
  <a href="{{ route('admin.matches.index') }}" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i> All Matches</a>
  <span class="tc-match">
    <span id="tcTitleL">{{ $courtLeft['name'] }}</span> vs <span id="tcTitleR">{{ $courtRight['name'] }}</span>
  </span>
  <span class="ml-conn warn" id="mlConn" title="Live = pushed over WebSockets; Polling = WebSocket server unreachable, falling back to HTTP">Connecting</span>
  <span class="ml-conn bad" id="tcAudio" hidden></span>
</div>

<div class="ml-hint" id="mlHint" hidden>
  <i class="fa-solid fa-triangle-exclamation"></i>
  <span>
    Live push is down — the screens are on the slower HTTP fallback, so the main
    screen and live overlay will trail this console by a second or so.
    Start the WebSocket server with <code>php artisan reverb:start</code> and leave it running.
  </span>
</div>

{{-- ── Match clock ──────────────────────────────────────── --}}
<div class="tc-panel">
  <div class="tc-clockrow">
    <div class="tc-clockbox">
      <div class="tc-part" id="tcPart">{{ $match->period ?: 'No game part set' }}</div>
      <div class="tc-clock {{ $match->timer_running ? 'on' : '' }}" id="tcClock">{{ $match->clock }}</div>
    </div>

    <div class="tc-startstop">
      <form action="{{ route('admin.matches.timer.update',$match) }}" method="POST" data-live>
        @csrf @method('PUT')
        <input type="hidden" name="action" value="start">
        <button type="submit" class="tc-btn tc-start" id="tcStart">Start</button>
      </form>
      <form action="{{ route('admin.matches.timer.update',$match) }}" method="POST" data-live>
        @csrf @method('PUT')
        <input type="hidden" name="action" value="stop">
        <button type="submit" class="tc-btn tc-stop" id="tcStop">Stop</button>
      </form>
    </div>

    <div class="tc-setgroup">
      <form action="{{ route('admin.matches.timer.update',$match) }}" method="POST" class="tc-setform" data-live>
        @csrf @method('PUT')
        <input type="number" name="minutes" step="0.1" min="0.1" max="120" placeholder="Minutes" class="tc-minutes" aria-label="Half length in minutes">
        <button type="submit" name="action" value="set" class="tc-btn tc-set">Set</button>
        <button type="submit" name="action" value="set_start" class="tc-btn tc-setstart">Set &amp; Start</button>
      </form>
      <form action="{{ route('admin.matches.timer.update',$match) }}" method="POST" data-live>
        @csrf @method('PUT')
        <input type="hidden" name="action" value="reset">
        <button type="submit" class="tc-btn tc-reset">Reset {{ $resetMinutes }}:00</button>
      </form>
    </div>
  </div>

  <div class="field-hint tc-note">
    The clock counts down from the half length and is stored as banked seconds
    plus the current start time. Every screen derives the time from those two
    values against the server clock, so the match clock and the raid clock stay
    in step with each other and across all open screens.
  </div>
</div>

{{-- ── Raid clocks ──────────────────────────────────────── --}}
<div class="tc-grid {{ $match->courts_swapped ? 'swapped' : '' }}" data-court-grid>
  @foreach($sides as $side => $data)
    <div class="tc-card tc-t{{ $side }}">
      <div class="tc-team">{{ $data['name'] }}</div>

      <div class="tc-raidbtns">
        <form action="{{ route('admin.matches.raid',$match) }}" method="POST" data-live>
          @csrf @method('PUT')
          <input type="hidden" name="action" value="in">
          <input type="hidden" name="team" value="{{ $side }}">
          <button type="submit" class="tc-raid tc-raid-in {{ $data['raiding'] ? 'active' : '' }}" data-raid-in="{{ $side }}">Raid In</button>
        </form>
        <form action="{{ route('admin.matches.raid',$match) }}" method="POST" data-live>
          @csrf @method('PUT')
          <input type="hidden" name="action" value="out">
          <button type="submit" class="tc-raid tc-raid-out {{ $data['raiding'] ? '' : 'active' }}" data-raid-out="{{ $side }}">Raid Out</button>
        </form>
      </div>

      <div class="tc-raidclock" id="tcRaid{{ $side }}">{{ $data['raiding'] ? $match->raid_remaining : $match->raid_duration_seconds }}</div>
      <div class="tc-raidstate" id="tcRaidState{{ $side }}">{{ $data['raiding'] ? 'In' : 'Out' }}</div>
    </div>
  @endforeach
</div>
@endsection

@push('styles')
<style>
  :root{--t1:#F5A524;--t2:#2E7DF6}

  .tc-title{text-align:center;font-size:15px;font-weight:700;letter-spacing:2px;text-transform:uppercase;margin-bottom:14px}
  .tc-sublinks{display:flex;gap:8px;flex-wrap:wrap;align-items:center;justify-content:center;margin-bottom:20px}
  .tc-match{font-size:12px;color:var(--ink3)}

  .tc-panel{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:18px;margin-bottom:18px}
  .tc-clockrow{display:flex;align-items:center;gap:18px;flex-wrap:wrap}

  .tc-clockbox{background:var(--panel);border:1px solid var(--border);border-radius:10px;padding:14px 26px;text-align:center;min-width:200px}
  .tc-part{font-size:10px;font-weight:600;letter-spacing:1.6px;text-transform:uppercase;color:var(--ink3);margin-bottom:6px}
  .tc-clock{font-size:34px;line-height:1.1;font-weight:800;letter-spacing:1px;font-variant-numeric:tabular-nums;white-space:nowrap}
  .tc-clock.on{color:var(--brand)}

  .tc-startstop,.tc-setgroup{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
  .tc-setgroup{margin-left:auto}
  .tc-setform{display:flex;align-items:center;gap:8px}

  .tc-btn{border:1px solid transparent;border-radius:7px;padding:9px 16px;font-size:13px;font-weight:600;
          cursor:pointer;font-family:inherit;color:#fff;transition:filter .12s,background .12s}
  .tc-btn:hover:not(:disabled){filter:brightness(1.12)}
  .tc-btn:disabled{opacity:.42;cursor:not-allowed}
  .tc-start{background:#16A34A}
  .tc-stop{background:#DC2626}
  .tc-set{background:#6B7280}
  .tc-setstart{background:#2563EB}
  .tc-reset{background:transparent;border-color:var(--ink2);color:var(--ink)}
  .tc-reset:hover{background:rgba(255,255,255,.06)}

  .tc-minutes{width:170px;background:#fff;border:1px solid var(--border);border-radius:7px;padding:9px 12px;
              font-size:13px;color:#0B0F14;outline:none;font-family:inherit}
  .tc-minutes::placeholder{color:#94A3B8}
  .tc-note{margin-top:14px;max-width:820px}

  .tc-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(330px,1fr));gap:18px}

  /* Change of ends. Only the painted order moves — each panel keeps its own
     team and so keeps posting to the right side. */
  .tc-grid.swapped > .tc-t1{order:2}
  .tc-grid.swapped > .tc-t2{order:1}
  .tc-card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:22px 18px;text-align:center}
  .tc-team{font-size:26px;font-weight:800;letter-spacing:1px;text-transform:uppercase;margin-bottom:16px;overflow-wrap:break-word}
  .tc-t1 .tc-team{color:var(--t1)}
  .tc-t2 .tc-team{color:var(--t2)}

  .tc-raidbtns{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:18px}
  .tc-raidbtns form{display:block}
  .tc-raid{width:100%;border:1px solid var(--border);border-radius:7px;padding:10px 8px;font-size:13px;font-weight:700;
           background:transparent;color:var(--ink);cursor:pointer;font-family:inherit;transition:all .12s}
  .tc-raid:hover{border-color:var(--ink2)}
  .tc-t1 .tc-raid-in.active{background:var(--t1);border-color:var(--t1);color:#0B0F14}
  .tc-t2 .tc-raid-in.active{background:var(--t2);border-color:var(--t2);color:#fff}
  .tc-raid-out.active{border-color:var(--ink2);background:rgba(255,255,255,.05)}

  .tc-raidclock{font-size:52px;line-height:1;font-weight:800;font-variant-numeric:tabular-nums}
  .tc-raidclock.warn{color:#F87171}
  .tc-raidstate{font-size:12px;font-weight:600;letter-spacing:5px;text-transform:uppercase;color:var(--ink3);margin-top:8px}
  .tc-t1 .tc-raidstate{color:var(--t1)}
  .tc-t2 .tc-raidstate{color:var(--t2)}
</style>
@endpush

@push('scripts')
@include('admin.matches._engine')
<script src="/js/raid-audio.js"></script>
<script>
(function () {
  var clockEl = document.getElementById('tcClock');

  /* ── Raid countdown audio ───────────────────────────────────────────────
     Plays on Raid In, stops on Raid Out. Add ?mute=1 to the page URL to
     silence it, or ?vol=0.5 to trim the level. */
  var params = new URLSearchParams(window.location.search);
  var audioOn = params.get('mute') !== '1';

  RaidAudio.configure({
    sources: @json($raidAudioSources),
    raidSeconds: {{ $match->raid_duration_seconds }},
    volume: params.has('vol') ? parseFloat(params.get('vol')) : 0.85
  });
  RaidAudio.setEnabled(audioOn);

  /* Only speaks up when something is actually wrong — a missing or undecodable
     file would otherwise fail silently. */
  function refreshAudioStatus() {
    var chip = document.getElementById('tcAudio');
    if (!chip) { return; }

    if (!audioOn) {
      chip.hidden = false;
      chip.className = 'ml-conn warn';
      chip.textContent = 'Audio muted';
      return;
    }

    if (RaidAudio.status() === 'failed') {
      chip.hidden = false;
      chip.className = 'ml-conn bad';
      chip.textContent = 'Audio file missing';
      return;
    }

    chip.hidden = true;
  }

  setInterval(refreshAudioStatus, 2000);
  refreshAudioStatus();

  MatchLive.init({
    url: '{{ route('admin.matches.state', $match) }}',
    state: @json($match->toScreenPayload()),
    socket: @json($socket),

    /* Optimistic update: start the raid or the clock on the click rather than
       after the round trip. Both clocks are derived from timestamps, so writing
       a start time locally makes them begin counting immediately; the server's
       own frame overwrites it a moment later and any mismatch self-corrects. */
    onSend: function (form, body) {
      var act = body.get('action');

      if (act === 'in' || act === 'out') {
        var side = act === 'in' ? Number(body.get('team')) : null;

        /* Countdown bed, driven straight off the button. Running here means we
           are still inside the click, which satisfies the browser's autoplay
           rule — no "click to enable" step is ever needed. Raid In on the other
           side mid-raid restarts the bed from the top. */
        if (act === 'in') {
          RaidAudio.play();
        } else {
          RaidAudio.stop();
        }
        refreshAudioStatus();

        MatchLive.patch(function (s, now) {
          s.raid_team = side;
          s.clocks.raid.running = (side !== null);
          s.clocks.raid.started_at = (side !== null) ? now : null;
          if (s.teams) {
            s.teams.forEach(function (t) { t.raiding = (t.side === side); });
          }
        });
        return;
      }

      if (['start', 'stop', 'reset', 'set', 'set_start'].indexOf(act) !== -1) {
        var mins = parseFloat(body.get('minutes'));
        MatchLive.patch(function (s, now) {
          var t = s.clocks.timer;

          if (act === 'start') {
            if (!t.running && (t.duration - t.elapsed) > 0) { t.running = true; t.started_at = now; }
          } else if (act === 'stop') {
            if (t.running && t.started_at) {
              t.elapsed += Math.floor((now - t.started_at) / 1000);   // bank it
            }
            t.running = false;
            t.started_at = null;
          } else if (act === 'reset') {
            t.duration = 1200;
            t.elapsed = 0;
            t.running = false;
            t.started_at = null;
          } else if (!isNaN(mins) && mins > 0) {
            t.duration = Math.round(mins * 60);
            t.elapsed = 0;
            t.running = (act === 'set_start');
            t.started_at = t.running ? now : null;
          }
        });
      }
    },

    // Both clocks are recomputed from the same server-derived instant here, so
    // the raid countdown and the match clock tick over together.
    onPaint: function (t) {
      clockEl.textContent = t.matchClock;
      clockEl.classList.toggle('on', t.matchRunning);

      [1, 2].forEach(function (side) {
        var active = t.raidTeam === side;
        var val = document.getElementById('tcRaid' + side);
        val.textContent = active ? t.raidSeconds : t.state.clocks.raid.duration;
        val.classList.toggle('warn', active && t.raidSeconds <= 10);
        document.getElementById('tcRaidState' + side).textContent = active ? 'In' : 'Out';
      });

      // Start is pointless on a running or expired clock; Stop on a stopped one.
      document.getElementById('tcStart').disabled = t.matchRunning || t.matchSeconds === 0;
      document.getElementById('tcStop').disabled = !t.matchRunning;
    },

    onState: function (s) {
      if (!s) { return; }
      document.getElementById('tcPart').textContent = s.period || 'No game part set';

      // Change of ends, applied without a reload.
      document.querySelectorAll('[data-court-grid]').forEach(function (grid) {
        grid.classList.toggle('swapped', !!s.courts_swapped);
      });
      document.getElementById('tcTitleL').textContent = s.left.name;
      document.getElementById('tcTitleR').textContent = s.right.name;

      document.querySelectorAll('[data-raid-in]').forEach(function (b) {
        b.classList.toggle('active', Number(b.dataset.raidIn) === s.raid_team);
      });
      document.querySelectorAll('[data-raid-out]').forEach(function (b) {
        b.classList.toggle('active', Number(b.dataset.raidOut) !== s.raid_team);
      });
    }
  });

  MatchLive.bindForms();
})();
</script>
@endpush
