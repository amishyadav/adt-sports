<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $match->title }} · Main Screen</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  :root{--brand:#D4420A;--green:#128A1E;--ink:#fff}
  html,body{height:100%;overflow:hidden}
  body{background:#000;color:var(--ink);font-family:'Inter',system-ui,sans-serif}

  /* The centre track has to be wide enough for the clock at its largest, or
     mm:ss wraps and shoves the raid box out of the (hidden) overflow. */
  .board{height:100%;display:grid;grid-template-columns:1fr minmax(260px,23vw) 1fr;
         gap:clamp(6px,1vw,16px);padding:clamp(10px,2vh,28px)}

  /* The two courts, angled toward each other like the mat they represent. */
  .court{background:var(--brand);display:flex;flex-direction:column;align-items:center;
         justify-content:center;gap:clamp(8px,2vh,26px);padding:clamp(12px,2vw,40px);min-width:0}
  .court-l{clip-path:polygon(0 0,94% 0,100% 50%,94% 100%,0 100%)}
  .court-r{clip-path:polygon(6% 0,100% 0,100% 100%,6% 100%,0 50%)}

  .logo{width:clamp(44px,6vw,86px);height:clamp(44px,6vw,86px);border-radius:50%;object-fit:cover;
        background:rgba(0,0,0,.2);border:3px solid rgba(255,255,255,.55)}
  .name{font-size:clamp(20px,3.4vw,54px);font-weight:800;text-align:center;line-height:1.1;
        overflow-wrap:break-word;max-width:100%}
  .score{font-size:clamp(56px,11vw,170px);line-height:.85;font-weight:900;font-variant-numeric:tabular-nums}

  .squad{display:flex;gap:clamp(2px,.5vw,7px);flex-wrap:wrap;justify-content:center;min-height:1em}
  .pip{width:clamp(13px,1.7vw,24px);height:clamp(13px,1.7vw,24px);border-radius:50%;background:var(--green);
       color:#fff;display:flex;align-items:center;justify-content:center;font-size:clamp(7px,.85vw,12px);
       border:1px solid rgba(0,0,0,.25)}
  .pip.spent{background:rgba(0,0,0,.28);color:rgba(255,255,255,.35)}

  .mid{display:flex;flex-direction:column;align-items:center;justify-content:center;
       gap:clamp(10px,2.2vh,26px);min-width:0}
  .part{background:var(--brand);border-radius:999px;padding:clamp(5px,.9vh,11px) clamp(12px,1.6vw,26px);
        font-size:clamp(11px,1.25vw,20px);font-weight:800;letter-spacing:1px;text-transform:uppercase;
        text-align:center;white-space:nowrap;max-width:100%;overflow:hidden;text-overflow:ellipsis}
  .part.empty{visibility:hidden}

  /* Clock and raid are capped against viewport HEIGHT as well as width, so a
     short-and-wide projector can't scale them past the board. */
  .clockbox{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.14);
            border-radius:clamp(8px,1vw,16px);padding:clamp(8px,1.6vh,20px) clamp(14px,2vw,32px);
            text-align:center;width:100%}
  .cap{font-size:clamp(8px,.78vw,12px);letter-spacing:3px;text-transform:uppercase;
       color:rgba(255,255,255,.5);margin-bottom:4px;white-space:nowrap}
  .clock{font-size:min(clamp(32px,4.4vw,78px),11vh);line-height:1;font-weight:900;
         font-variant-numeric:tabular-nums;white-space:nowrap}
  .clock.on{color:#FFD166}

  .raid{background:var(--green);border-radius:clamp(8px,1vw,16px);
        padding:clamp(8px,1.6vh,22px) clamp(16px,2.2vw,38px);text-align:center;width:100%}
  .raid .val{font-size:min(clamp(30px,4.2vw,72px),10.5vh);line-height:1;font-weight:900;
             font-variant-numeric:tabular-nums;white-space:nowrap}
  .raid.idle{background:#1F2937}
  .raid.idle .val,.raid.idle .cap{color:rgba(255,255,255,.5)}
  .raid.warn{background:#C21515;animation:flash 1s steps(2,end) infinite}
  @keyframes flash{50%{opacity:.55}}

  .status{font-size:clamp(9px,.95vw,14px);letter-spacing:3px;text-transform:uppercase;
          color:#6B7280;text-align:center;white-space:nowrap}

  /* Transport readout. Near-invisible while healthy, obvious when it is not,
     so a lagging board can be diagnosed from across the hall. */
  .age{position:fixed;right:10px;bottom:8px;font-size:11px;font-weight:700;letter-spacing:2px;
       color:rgba(255,255,255,.18);white-space:nowrap}
  .age.late{color:#FCA5A5}
  .offline{position:fixed;left:10px;bottom:8px;font-size:11px;font-weight:700;letter-spacing:2px;
           text-transform:uppercase;color:#FCA5A5;opacity:0;transition:opacity .3s}
  .offline.on{opacity:1}

  @media (max-width:760px){
    html,body{overflow:auto}
    .board{grid-template-columns:1fr;gap:12px}
    .court-l,.court-r{clip-path:none;border-radius:14px}
    .mid{order:3}
  }
</style>
</head>
<body>
@php [$left, $right] = $match->sides(); @endphp
<div class="board">

  <div class="court court-l" id="sideL">
    @if($left['logo'])<img src="{{ $left['logo'] }}" alt="" class="logo" id="logoL">@endif
    <div class="name" id="nameL">{{ $left['name'] }}</div>
    <div class="score" id="scoreL">{{ $left['score'] }}</div>
    <div class="squad" id="squadL"></div>
  </div>

  <div class="mid">
    <div class="part {{ $match->period ? '' : 'empty' }}" id="part">{{ $match->period ?: '—' }}</div>

    <div class="clockbox">
      <div class="cap">Match Clock</div>
      <div class="clock {{ $match->timer_running ? 'on' : '' }}" id="clock">{{ $match->clock }}</div>
    </div>

    <div class="raid {{ $match->raid_running ? '' : 'idle' }}" id="raid">
      <div class="cap">Raid</div>
      <div class="val" id="raidVal">{{ $match->raid_remaining }}</div>
    </div>

    <div class="status" id="status">{{ $match->status_label }}</div>
  </div>

  <div class="court court-r" id="sideR">
    @if($right['logo'])<img src="{{ $right['logo'] }}" alt="" class="logo" id="logoR">@endif
    <div class="name" id="nameR">{{ $right['name'] }}</div>
    <div class="score" id="scoreR">{{ $right['score'] }}</div>
    <div class="squad" id="squadR"></div>
  </div>

</div>

<div class="offline" id="offline">Reconnecting</div>
<div class="age" id="mlAge"></div>

@include('admin.matches._engine')
<script>
(function () {
  var FULL = {{ \App\Models\TeamMatch::FULL_SQUAD }};
  var clockEl = document.getElementById('clock');
  var raidEl = document.getElementById('raid');
  var raidValEl = document.getElementById('raidVal');

  // One pip per player: filled for those still on the mat, dimmed for the rest,
  // so squad strength reads at a glance from the back of a hall.
  function squad(el, left) {
    var html = '';
    for (var i = 1; i <= FULL; i++) {
      html += '<span class="pip' + (i <= left ? '' : ' spent') + '"><i class="fa-solid fa-user"></i></span>';
    }
    el.innerHTML = html;
  }

  function side(prefix, data) {
    document.getElementById('name' + prefix).textContent = data.name;
    document.getElementById('score' + prefix).textContent = data.score;
    squad(document.getElementById('squad' + prefix), data.players);
    var logo = document.getElementById('logo' + prefix);
    if (logo && data.logo) { logo.src = data.logo; }
  }

  MatchLive.init({
    url: '{{ route('admin.matches.state', $match) }}',
    state: @json($match->toScreenPayload()),
    socket: @json($socket),

    onPaint: function (t) {
      clockEl.textContent = t.matchClock;
      clockEl.classList.toggle('on', t.matchRunning);

      raidValEl.textContent = t.raidSeconds;
      raidEl.classList.toggle('idle', !t.raidRunning);
      // Flash the last ten seconds of a raid — what the hall watches for.
      raidEl.classList.toggle('warn', t.raidRunning && t.raidSeconds <= 10);
    },

    onState: function (d) {
      if (!d) { return; }
      side('L', d.left);
      side('R', d.right);

      var part = document.getElementById('part');
      part.textContent = d.period || '—';
      part.classList.toggle('empty', !d.period);

      document.getElementById('status').textContent = d.label;
    }
  });
})();
</script>
</body>
</html>
