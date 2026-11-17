<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $match->title }} — Live</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
<style>
/*
 * Broadcast scoreboard. A standalone full-screen page rather than part of the
 * admin shell: it goes on a projector or a second screen, so there is no
 * sidebar, no topbar and nothing to click.
 *
 * The palette matches the admin panel so the two look related.
 */
:root{
  --bg:#0F0E0D;--panel:#161412;--card:#1C1917;--border:rgba(255,255,255,.08);
  --ink:#F5F0EB;--ink2:#A8A09A;--ink3:#6B6560;--brand:#D4420A;
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
body{
  font-family:'Inter',sans-serif;background:var(--bg);color:var(--ink);
  min-height:100vh;display:flex;flex-direction:column;overflow:hidden;
  -webkit-font-smoothing:antialiased;
}

.d-top{
  display:flex;align-items:center;justify-content:space-between;gap:16px;
  padding:18px 28px;border-bottom:1px solid var(--border);background:var(--panel);
}
.d-tournament{font-size:clamp(13px,1.4vw,18px);font-weight:600;letter-spacing:.5px;color:var(--ink2)}
.d-meta{font-size:clamp(11px,1.1vw,14px);color:var(--ink3);letter-spacing:1px;text-transform:uppercase}

.d-status{
  display:inline-flex;align-items:center;gap:8px;padding:5px 14px;border-radius:20px;
  font-size:clamp(11px,1.1vw,14px);font-weight:700;letter-spacing:1px;text-transform:uppercase;
}
.d-status.is-live{background:rgba(220,38,38,.18);color:#FCA5A5}
.d-status.is-half{background:rgba(217,119,6,.18);color:#FCD34D}
.d-status.is-done{background:rgba(255,255,255,.08);color:var(--ink2)}
.d-status.is-soon{background:rgba(14,165,233,.16);color:#7DD3FC}
.d-status::before{content:'';width:9px;height:9px;border-radius:50%;background:currentColor}
.d-status.is-live::before{animation:d-pulse 1.4s ease-in-out infinite}
@keyframes d-pulse{0%,100%{opacity:1}50%{opacity:.25}}
@media (prefers-reduced-motion:reduce){.d-status.is-live::before{animation:none}}

/* The scoreboard fills whatever is left. */
.d-board{
  flex:1;display:grid;grid-template-columns:1fr auto 1fr;align-items:center;
  gap:clamp(16px,3vw,56px);padding:clamp(16px,3vh,44px) clamp(20px,4vw,64px);
}
.d-side{display:grid;justify-items:center;gap:clamp(10px,1.6vh,22px);min-width:0}
.d-crest{
  width:clamp(72px,9vw,150px);height:clamp(72px,9vw,150px);border-radius:50%;
  object-fit:cover;background:var(--card);border:3px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  font-size:clamp(22px,2.6vw,46px);font-weight:800;color:#fff;letter-spacing:1px;
}
.d-name{
  font-size:clamp(17px,2.4vw,40px);font-weight:700;text-align:center;letter-spacing:.5px;
  max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
}
.d-score{
  font-size:clamp(68px,13vw,210px);font-weight:900;line-height:.92;
  font-variant-numeric:tabular-nums;letter-spacing:-2px;
}

/* The side currently raiding. */
.d-side.is-raiding .d-crest{border-color:var(--brand);box-shadow:0 0 0 6px rgba(212,66,10,.18)}
.d-raid-tag{
  font-size:clamp(10px,1vw,14px);font-weight:800;letter-spacing:2px;text-transform:uppercase;
  color:var(--brand);visibility:hidden;
}
.d-side.is-raiding .d-raid-tag{visibility:visible}

/* Players still standing. */
.d-pips{display:flex;gap:clamp(4px,.6vw,9px)}
.d-pip{
  width:clamp(10px,1.1vw,17px);height:clamp(10px,1.1vw,17px);border-radius:50%;
  background:rgba(255,255,255,.14);
}
.d-pip.on{background:var(--brand)}

/* Centre column: clocks. */
.d-mid{display:grid;justify-items:center;gap:clamp(8px,1.4vh,18px);min-width:clamp(140px,16vw,280px)}
.d-clock{
  font-size:clamp(34px,5.4vw,96px);font-weight:800;line-height:1;
  font-variant-numeric:tabular-nums;letter-spacing:1px;
}
.d-clock-label{font-size:clamp(9px,.9vw,13px);letter-spacing:2.5px;text-transform:uppercase;color:var(--ink3)}

.d-raid{
  display:grid;justify-items:center;gap:6px;padding:clamp(10px,1.5vh,20px) clamp(16px,2vw,32px);
  background:var(--card);border:1px solid var(--border);border-radius:14px;min-width:100%;
}
.d-raid-value{
  font-size:clamp(30px,4.4vw,76px);font-weight:900;line-height:1;font-variant-numeric:tabular-nums;
}
.d-raid-value.is-low{color:#FCA5A5}
.d-raid-bar{width:100%;height:5px;border-radius:3px;background:rgba(255,255,255,.1);overflow:hidden}
.d-raid-bar span{display:block;height:100%;background:var(--brand);transition:width .1s linear}

.d-dod{
  padding:6px 14px;border-radius:20px;font-size:clamp(10px,1vw,14px);font-weight:800;
  letter-spacing:1.5px;text-transform:uppercase;
  background:rgba(217,119,6,.16);border:1px solid rgba(217,119,6,.4);color:#FCD34D;
  display:none;
}
.d-dod.is-on{display:block}

/* Last action ticker. */
.d-ticker{
  padding:14px 28px;border-top:1px solid var(--border);background:var(--panel);
  display:flex;align-items:center;gap:14px;min-height:56px;
}
.d-ticker i{color:var(--brand);font-size:clamp(13px,1.3vw,18px);flex-shrink:0}
.d-ticker-text{
  font-size:clamp(13px,1.5vw,21px);font-weight:600;color:var(--ink);
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
}
.d-conn{margin-left:auto;font-size:9px;flex-shrink:0}
.d-conn.ktms-ok{color:#86EFAC}
.d-conn.ktms-bad{color:#FCA5A5}

@media (max-width:820px){
  body{overflow:auto}
  .d-board{grid-template-columns:1fr;gap:20px;padding:20px}
  .d-mid{order:-1;min-width:0;width:100%}
  .d-score{font-size:clamp(56px,20vw,110px)}
}
</style>
</head>
<body>

<div class="d-top">
  <div>
    <div class="d-tournament">{{ $match->tournament?->name }}</div>
    <div class="d-meta">
      @if($match->match_number)Match {{ $match->match_number }}@endif
      @if($match->round) · {{ $match->round }}@endif
      @if($match->venue) · {{ $match->venue }}@endif
    </div>
  </div>
  <div style="display:flex;align-items:center;gap:14px">
    <span class="d-dod" data-dod>Do or Die</span>
    <span class="d-status" data-status>{{ $match->status_label }}</span>
  </div>
</div>

<div class="d-board">
  @foreach([['home', $match->homeTeam], ['away', $match->awayTeam]] as $index => [$side, $team])
    @if($index === 1)
      {{-- Centre column sits between the two sides. --}}
      <div class="d-mid">
        <div style="display:grid;justify-items:center;gap:2px">
          <div class="d-clock-label">Half <span data-half>{{ $match->current_half }}</span></div>
          <div class="d-clock" data-game-clock>
            {{ \App\Models\Ktms\GameMatch::formatClock($match->effectiveGameClockRemaining()) }}
          </div>
        </div>
        <div class="d-raid">
          <div class="d-clock-label">Raid</div>
          <div class="d-raid-value" data-raid-clock>
            {{ \App\Models\Ktms\GameMatch::formatClock($match->effectiveRaidClockRemaining()) }}
          </div>
          <div class="d-raid-bar"><span data-raid-bar style="width:100%"></span></div>
        </div>
      </div>
    @endif

    <div class="d-side" data-side="{{ $side }}">
      <div class="d-raid-tag">Raiding</div>
      @if($team?->logo_url)
        <img src="{{ $team->logo_url }}" alt="{{ $team->name }}" class="d-crest">
      @else
        <div class="d-crest" style="background:{{ $team?->primary_color ?? '#4A4640' }}">{{ $team?->initials }}</div>
      @endif
      <div class="d-name">{{ $team?->name ?? 'TBD' }}</div>
      <div class="d-score" data-{{ $side }}-score>{{ $side === 'home' ? $match->home_score : $match->away_score }}</div>
      <div class="d-pips" data-{{ $side }}-pips>
        @for($i = 1; $i <= $match->playersPerSide(); $i++)
          <span class="d-pip {{ $i <= ($side === 'home' ? $match->home_players_on_court : $match->away_players_on_court) ? 'on' : '' }}"></span>
        @endfor
      </div>
    </div>
  @endforeach
</div>

<div class="d-ticker">
  <i class="fa-solid fa-circle-dot" data-ticker-icon></i>
  <span class="d-ticker-text" data-ticker>Waiting for the first action…</span>
  <i class="fa-solid fa-circle d-conn" data-connection title="Connecting…"></i>
</div>

<script src="{{ asset('js/ktms-live.js') }}"></script>
<script>
(function () {
  var session = window.KtmsLive.session({
    stateUrl: @json(route('admin.ktms.matches.state', $match)),
    initialState: @json($snapshot),
    // A display has nobody waiting on it, so it can poll a little more slowly.
    pollInterval: 2500,
  });

  var statusClass = {
    live: 'is-live', half_time: 'is-half', completed: 'is-done', scheduled: 'is-soon',
  };

  session.onState(function (state) {
    window.KtmsLive.setText('[data-home-score]', state.teams.home.score);
    window.KtmsLive.setText('[data-away-score]', state.teams.away.score);
    window.KtmsLive.setText('[data-half]', state.match.current_half);

    var status = document.querySelector('[data-status]');
    if (status) {
      status.textContent = state.match.status_label;
      status.className = 'd-status ' + (statusClass[state.match.status] || 'is-soon');
    }

    paintPips('[data-home-pips]', state.teams.home.players_on_court, state.match.players_per_side);
    paintPips('[data-away-pips]', state.teams.away.players_on_court, state.match.players_per_side);

    document.querySelectorAll('[data-side]').forEach(function (node) {
      var side = node.dataset.side;
      node.classList.toggle('is-raiding', Boolean(state.teams[side] && state.teams[side].is_raiding));
    });

    var dod = document.querySelector('[data-dod]');
    if (dod) { dod.classList.toggle('is-on', Boolean(state.next_raid_is_do_or_die)); }

    // Most recent action, as a ticker line.
    var latest = (state.timeline || [])[0];
    if (latest) {
      window.KtmsLive.setText('[data-ticker]', latest.description);
      var icon = document.querySelector('[data-ticker-icon]');
      if (icon) { icon.className = 'fa-solid ' + latest.icon; }
    }
  });

  session.onTick(function (s) {
    window.KtmsLive.setText('[data-game-clock]', s.gameClock.formatted());

    var raid = document.querySelector('[data-raid-clock]');
    if (raid) {
      var value = s.raidClock.value();
      raid.textContent = window.KtmsLive.formatClock(value);
      raid.classList.toggle('is-low', value <= 10);
    }

    var bar = document.querySelector('[data-raid-bar]');
    if (bar) { bar.style.width = (s.raidClock.fraction() * 100).toFixed(1) + '%'; }
  });

  function paintPips(selector, on, total) {
    var host = document.querySelector(selector);
    if (!host) { return; }

    if (host.children.length !== total) {
      host.innerHTML = '';
      for (var i = 0; i < total; i++) {
        host.appendChild(document.createElement('span')).className = 'd-pip';
      }
    }

    Array.prototype.forEach.call(host.children, function (pip, index) {
      pip.classList.toggle('on', index < on);
    });
  }

  session.start();
})();
</script>
</body>
</html>
