<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $match->title }} · Live Score</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">
<style>
/* ──────────────────────────────────────────────────────────────
   LIVE SCORE BAR — built for an OBS / vMix browser source.

   OBS: add a Browser source, point it at this URL, size 1920 × 1080.
   The graphic is drawn on a fixed 1920-wide canvas and scales itself to the
   source size, so it stays pixel-consistent at 1920, 1280 or 854 wide. The
   page background is transparent and scrollbars are suppressed, so no custom
   CSS is needed in OBS.

   The bar is anchored to the TOP of the canvas, clear of YouTube's player
   controls. To move it to the bottom instead, swap the two marked lines in
   .stage and .rail.
   ────────────────────────────────────────────────────────────── */

  *{margin:0;padding:0;box-sizing:border-box}
  :root{
    /* ADT palette — deliberately not the reference's purple/magenta/blue. */
    --rail:#070A0F;          /* gap colour showing between segments */
    --seg:#161E2A;           /* dark segment fill */
    --seg2:#1E2835;
    --band:#F2F5F8;          /* name band */
    --bandink:#0C1219;
    --brand:#E8590C;         /* ADT orange */
    --brand2:#FF8038;
    --green:#15A02A;
    --amber:#D97706;
    --ink:#fff;
  }
  html,body{width:100%;height:100%;background:transparent;overflow:hidden}
  body{font-family:'Inter',system-ui,-apple-system,sans-serif;-webkit-font-smoothing:antialiased}

  /* Fixed design canvas, pinned top-left and scaled to the source width. */
  .stage{position:fixed;left:0;top:0;width:1920px;height:1080px;
         transform-origin:top left}            /* ← bottom left, if moving down */

  .rail{position:absolute;left:80px;top:46px;width:1760px;          /* ← bottom:46px */
        animation:drop .65s cubic-bezier(.2,.9,.25,1) both}
  @keyframes drop{from{transform:translateY(-140%);opacity:0}to{transform:translateY(0);opacity:1}}

  /* ── Tier 1: crests, names, scores ───────────────────────── */
  .tier1{display:flex;align-items:stretch;height:92px;background:var(--rail);
         border-radius:4px;filter:drop-shadow(0 16px 34px rgba(0,0,0,.6))}

  /* Every segment is the same parallelogram; a small negative margin pulls
     them together so only a thin slanted sliver of the rail shows through. */
  .seg{clip-path:polygon(18px 0,100% 0,calc(100% - 18px) 100%,0 100%);margin-left:-14px;
       display:flex;align-items:center;justify-content:center;flex:0 0 auto}
  .seg:first-child{margin-left:0}

  .cap{width:96px;background:linear-gradient(135deg,var(--brand2),var(--brand))}
  .cap-l{clip-path:polygon(26px 0,100% 0,calc(100% - 18px) 100%,0 100%)}
  .cap-r{clip-path:polygon(18px 0,100% 0,calc(100% - 26px) 100%,0 100%)}
  .cap img{width:58px;height:58px;border-radius:50%;background:#fff;object-fit:contain;padding:3px}

  .crest{width:118px;background:var(--seg2)}
  .crest .holder{width:72px;height:72px;border-radius:50%;overflow:hidden;background:#0D131B;
                 border:2px solid rgba(255,255,255,.3);display:flex;align-items:center;
                 justify-content:center;font-size:22px;font-weight:900;color:#93A1B2}
  .crest .holder img{width:100%;height:100%;object-fit:cover}

  .band{flex:1 1 0;min-width:0;background:var(--band);padding:0 26px}
  .bname{font-size:42px;font-weight:900;letter-spacing:1.5px;text-transform:uppercase;
         color:var(--bandink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}

  .score{width:116px;background:var(--seg);position:relative}
  .score .num{font-size:58px;font-weight:900;color:var(--ink);font-variant-numeric:tabular-nums;line-height:1}
  .score::after{content:'';position:absolute;left:0;right:0;top:0;height:4px;background:var(--brand)}
  .score.bump .num{animation:bump .45s ease}
  @keyframes bump{0%{transform:scale(1)}35%{transform:scale(1.26)}100%{transform:scale(1)}}

  /* The highlighted centre box carries the raid clock — the number the
     commentary tracks between points. */
  .centre{width:104px;background:var(--green)}
  .centre .num{font-size:54px;font-weight:900;color:#fff;font-variant-numeric:tabular-nums;line-height:1}
  .centre.idle{background:var(--seg2)}
  .centre.idle .num{color:#8593A5}
  .centre.warn{background:var(--amber);animation:urgent .85s steps(2,end) infinite}
  @keyframes urgent{50%{opacity:.52}}

  /* ── Tier 2: squads, period, group, match clock ───────────── */
  .tier2{display:flex;justify-content:center;margin-top:7px}
  .tier2in{display:flex;align-items:stretch;height:54px;background:var(--rail);border-radius:3px;
           filter:drop-shadow(0 12px 26px rgba(0,0,0,.55))}

  .squad{background:var(--seg);padding:0 22px;gap:9px}
  .pip{width:19px;height:19px;border-radius:50%;background:#fff;color:#0C1219;display:flex;
       align-items:center;justify-content:center;font-size:10px;flex:0 0 auto}
  .pip.spent{background:#39465A;color:#1A222E}

  .tag{padding:0 20px}
  .tag-part{background:linear-gradient(135deg,var(--brand2),var(--brand));color:#fff}
  .tag-part.empty{display:none}
  .tag-venue{background:#D8DFE7;color:var(--bandink)}
  .tag-venue.empty{display:none}
  .tag span{font-size:19px;font-weight:800;letter-spacing:1.6px;text-transform:uppercase;white-space:nowrap}

  .mclock{background:var(--seg2);padding:0 22px}
  .mclock span{font-size:26px;font-weight:900;color:var(--ink);letter-spacing:1px;
               font-variant-numeric:tabular-nums;white-space:nowrap}
  .mclock.stopped span{color:#8593A5}

  /* Quiet notice if the poll starts failing — the graphic stays on air. */
  .offline{position:absolute;right:0;top:-30px;font-size:14px;font-weight:800;letter-spacing:2px;
           text-transform:uppercase;color:#FDBA74;opacity:0;transition:opacity .3s}
  .offline.on{opacity:1}
  /* Transport readout — invisible on a stream while healthy, legible when not. */
  .age{position:absolute;left:0;top:-28px;font-size:12px;font-weight:700;letter-spacing:2px;
       color:rgba(255,255,255,.14);white-space:nowrap}
  .age.late{color:#FCA5A5}

  /* Autoplay prompt. Sits above the bar so it is outside the broadcast frame,
     and it only exists while the browser is refusing to start audio. */
  .sound-gate{position:absolute;left:50%;top:-34px;transform:translateX(-50%);
              display:inline-flex;align-items:center;gap:8px;cursor:pointer;
              background:var(--green);color:#fff;border:none;border-radius:16px;
              padding:6px 16px;font-family:inherit;font-size:12px;font-weight:800;
              letter-spacing:1px;text-transform:uppercase;white-space:nowrap;
              box-shadow:0 6px 18px rgba(0,0,0,.45)}
  .sound-gate[hidden]{display:none}
  .sound-gate.bad{background:#B91C1C;cursor:default}
</style>
</head>
<body>
@php
  [$left, $right] = $match->sides();
  $full = \App\Models\TeamMatch::FULL_SQUAD;

  /*
   * Countdown bed played for the length of a raid, tried in order.
   *
   * The 16-bit file comes first because Chrome (and so OBS) will not decode
   * 24-bit PCM WAV — the original export plays nothing. Regenerate it after
   * dropping in a new recording:
   *
   *     php artisan raid:audio-convert "Your File.wav"
   *
   * The original is kept as a fallback for browsers that do handle 24-bit.
   */
  $raidAudioSources = array_values(array_filter([
      '/audio/raid-countdown.wav',
      '/audio/' . rawurlencode('Kabbadi Countodwn Final.wav'),
  ], fn ($path) => is_file(public_path(rawurldecode(ltrim($path, '/'))))));
@endphp

<div class="stage" id="stage">
  <div class="rail">
    <div class="offline" id="offline">Reconnecting</div>
    <div class="age" id="mlAge"></div>
    {{-- Only shown when the browser is holding audio back; never appears in OBS. --}}
    <button type="button" class="sound-gate" id="soundGate" hidden>
      <i class="fa-solid fa-volume-high"></i> Click to enable raid countdown audio
    </button>

    <div class="tier1">
      <div class="seg cap cap-l"><img src="/uploads/logo.png" alt="ADT Sports"></div>

      <div class="seg crest">
        <div class="holder" id="crestL">
          @if($left['logo'])<img src="{{ $left['logo'] }}" alt="">@else{{ $left['short'] }}@endif
        </div>
      </div>

      <div class="seg band"><div class="bname" id="nameL">{{ $left['name'] }}</div></div>

      <div class="seg score score-l" id="scoreL"><div class="num" id="scoreLv">{{ $left['score'] }}</div></div>

      <div class="seg centre {{ $match->raid_running ? '' : 'idle' }}" id="centre">
        <div class="num" id="raidVal">{{ $match->raid_remaining }}</div>
      </div>

      <div class="seg score score-r" id="scoreR"><div class="num" id="scoreRv">{{ $right['score'] }}</div></div>

      <div class="seg band"><div class="bname" id="nameR">{{ $right['name'] }}</div></div>

      <div class="seg crest">
        <div class="holder" id="crestR">
          @if($right['logo'])<img src="{{ $right['logo'] }}" alt="">@else{{ $right['short'] }}@endif
        </div>
      </div>

      <div class="seg cap cap-r"></div>
    </div>

    <div class="tier2">
      <div class="tier2in">
        <div class="seg squad" id="squadL"></div>
        <div class="seg tag tag-part {{ $match->period ? '' : 'empty' }}" id="part"><span>{{ $match->period }}</span></div>
        <div class="seg tag tag-venue {{ $match->venue ? '' : 'empty' }}" id="venue"><span>{{ $match->venue }}</span></div>
        <div class="seg mclock {{ $match->timer_running ? '' : 'stopped' }}" id="mclock"><span id="clock">{{ $match->clock }}</span></div>
        <div class="seg squad" id="squadR"></div>
      </div>
    </div>

  </div>
</div>

@include('admin.matches._engine')
<script src="/js/raid-audio.js"></script>
<script>
(function () {
  var FULL = {{ $full }};

  /* ── Raid countdown audio ───────────────────────────────────────────────
     The countdown bed starts when the 30-second raid clock starts and stops
     the moment the raid ends. Add ?mute=1 to the URL to silence it (useful if
     the broadcast already carries its own bed), or ?vol=0.4 to trim the level. */
  var params = new URLSearchParams(window.location.search);
  var audioOn = params.get('mute') !== '1';
  var raidWasRunning = false;

  RaidAudio.configure({
    sources: @json($raidAudioSources),
    raidSeconds: {{ $match->raid_duration_seconds }},
    volume: params.has('vol') ? parseFloat(params.get('vol')) : 0.85
  });
  RaidAudio.setEnabled(audioOn);

  var gate = document.getElementById('soundGate');

  /* Report audio state on the page, above the bar and so out of the broadcast
     frame. Without this a silent countdown is only diagnosable in devtools. */
  function refreshGate() {
    if (!audioOn || !RaidAudio.isSupported()) { gate.hidden = true; return; }

    var state = RaidAudio.status();

    if (state === 'blocked') {
      gate.textContent = '🔊 Click to enable raid countdown audio';
      gate.className = 'sound-gate';
      gate.hidden = false;
    } else if (state === 'failed') {
      gate.textContent = '⚠ Countdown audio file could not be loaded';
      gate.className = 'sound-gate bad';
      gate.hidden = false;
    } else {
      gate.hidden = true;
    }
  }

  gate.addEventListener('click', function () {
    RaidAudio.arm();
    setTimeout(refreshGate, 150);
  });

  // Any interaction anywhere also satisfies the autoplay rule.
  ['pointerdown', 'keydown'].forEach(function (evt) {
    window.addEventListener(evt, function () {
      if (audioOn) { RaidAudio.arm(); setTimeout(refreshGate, 150); }
    }, { once: true });
  });

  // Catch a load failure that happens well after the page settles.
  setInterval(refreshGate, 2000);

  function raidAudio(t) {
    if (!audioOn) { return; }

    if (t.raidRunning && !raidWasRunning) {
      // Raid just went in. Seek to however far into it we already are, so a
      // reload mid-raid lines the bed up instead of restarting it.
      RaidAudio.start(t.raidSeconds);
      refreshGate();
    } else if (!t.raidRunning && raidWasRunning) {
      RaidAudio.stop();                 // raid out, or the clock was reset
    }

    raidWasRunning = t.raidRunning;
  }

  /* Scale the 1920-wide canvas to whatever the browser source is set to. */
  var stage = document.getElementById('stage');
  function fit() { stage.style.transform = 'scale(' + (window.innerWidth / 1920) + ')'; }
  fit();
  window.addEventListener('resize', fit);

  var clockEl = document.getElementById('clock');
  var mclockEl = document.getElementById('mclock');
  var centreEl = document.getElementById('centre');
  var raidValEl = document.getElementById('raidVal');
  // #offline is driven by the shared engine when a poll fails.

  var shown = { L: {{ $left['score'] }}, R: {{ $right['score'] }} };

  function squad(el, left) {
    var html = '';
    for (var i = 1; i <= FULL; i++) {
      html += '<span class="pip' + (i <= left ? '' : ' spent') + '"><i class="fa-solid fa-user"></i></span>';
    }
    el.innerHTML = html;
  }

  /* Pop the number when a point lands so it reads on a stream even if the
     viewer glanced away. */
  function setScore(prefix, value) {
    if (shown[prefix] === value) { return; }
    shown[prefix] = value;
    document.getElementById('score' + prefix + 'v').textContent = value;
    var tile = document.getElementById('score' + prefix);
    tile.classList.remove('bump');
    void tile.offsetWidth;                    // restart the animation
    tile.classList.add('bump');
  }

  function setSide(prefix, data) {
    document.getElementById('name' + prefix).textContent = data.name;
    setScore(prefix, data.score);
    squad(document.getElementById('squad' + prefix), data.players);

    var crest = document.getElementById('crest' + prefix);
    if (data.logo) {
      if (crest.firstElementChild) { crest.firstElementChild.src = data.logo; }
      else { crest.innerHTML = '<img src="' + data.logo + '" alt="">'; }
    } else {
      crest.textContent = data.short;
    }
  }

  function tag(id, value) {
    var el = document.getElementById(id);
    el.firstElementChild.textContent = value || '';
    el.classList.toggle('empty', !value);
  }

  MatchLive.init({
    url: '{{ route('admin.matches.state', $match) }}',
    state: @json($match->toScreenPayload()),
    socket: @json($socket),

    onPaint: function (t) {
      clockEl.textContent = t.matchClock;
      mclockEl.classList.toggle('stopped', !t.matchRunning);

      raidValEl.textContent = t.raidSeconds;
      centreEl.classList.toggle('idle', !t.raidRunning);
      // Flag the last ten seconds of a raid, the moment the play turns on.
      centreEl.classList.toggle('warn', t.raidRunning && t.raidSeconds <= 10);

      raidAudio(t);
    },

    onState: function (d) {
      if (!d) { return; }
      setSide('L', d.left);
      setSide('R', d.right);
      tag('part', d.period);
      tag('venue', d.venue);
    }
  });
})();
</script>
</body>
</html>
