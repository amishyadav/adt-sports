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

  /* Fixed design canvas, pinned bottom-left and scaled to the source width. */
  .stage{position:fixed;left:0;bottom:0;width:1920px;height:1080px;
         transform-origin:bottom left}

  /* Score bar along the bottom of frame. Its total height — 92 (tier 1) + 7
     (gap) + 54 (tier 2) = 153 — is what RAIL_H below is derived from, so the
     sting can be parked directly on top of it. */
  .rail{position:absolute;left:80px;bottom:46px;width:1760px;
        animation:rise .65s cubic-bezier(.2,.9,.25,1) both}
  @keyframes rise{from{transform:translateY(140%);opacity:0}to{transform:translateY(0);opacity:1}}

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

  /* Diagnostics sit BELOW the bar now — the space above it belongs to the
     sting. All three are near-invisible or hidden while everything is healthy. */
  .offline{position:absolute;right:0;bottom:-30px;font-size:14px;font-weight:800;letter-spacing:2px;
           text-transform:uppercase;color:#FDBA74;opacity:0;transition:opacity .3s}
  .offline.on{opacity:1}
  /* Transport readout — invisible on a stream while healthy, legible when not. */
  .age{position:absolute;left:0;bottom:-28px;font-size:12px;font-weight:700;letter-spacing:2px;
       color:rgba(255,255,255,.14);white-space:nowrap}
  .age.late{color:#FCA5A5}

  /* Autoplay prompt — only exists while the browser is refusing to start audio. */
  .sound-gate{position:absolute;left:50%;bottom:-34px;transform:translateX(-50%);
              display:inline-flex;align-items:center;gap:8px;cursor:pointer;
              background:var(--green);color:#fff;border:none;border-radius:16px;
              padding:6px 16px;font-family:inherit;font-size:12px;font-weight:800;
              letter-spacing:1px;text-transform:uppercase;white-space:nowrap;
              box-shadow:0 6px 18px rgba(0,0,0,.45)}
  .sound-gate[hidden]{display:none}
  .sound-gate.bad{background:#B91C1C;cursor:default}

  /* ── Event sting ───────────────────────────────────────────────────────
     A broadcast-style card that punches in, holds, then lifts off. One
     3-second animation drives the whole life cycle, so nothing has to be
     timed in JS beyond removing the node afterwards. */
  /* Parked directly above the score bar: 46 (bar offset) + 153 (bar height)
     + 26 (breathing room). Grows upward, so a taller card never pushes into
     the bar. */
  .cue{position:absolute;left:50%;bottom:225px;transform:translateX(-50%);
       pointer-events:none;z-index:50}

  .cue-card{position:relative;display:flex;flex-direction:column;align-items:center;
            gap:10px;padding:30px 72px;border-radius:18px;overflow:hidden;
            background:linear-gradient(135deg,rgba(10,13,18,.93),rgba(4,6,9,.96));
            border:3px solid var(--brand);
            box-shadow:0 26px 80px rgba(0,0,0,.72),0 0 60px rgba(232,89,12,.3);
            animation:cue-life 3s cubic-bezier(.16,.84,.3,1) both}

  .cue-label{position:relative;font-size:76px;font-weight:900;line-height:1;
             letter-spacing:4px;text-transform:uppercase;white-space:nowrap;
             color:#fff;text-shadow:0 4px 24px rgba(0,0,0,.6)}
  /* Team name only — points are deliberately not shown on air. The scoreline
     in the bar below is the single place a viewer reads the score from. */
  .cue-sub{position:relative;font-size:26px;font-weight:800;letter-spacing:3px;
           text-transform:uppercase;color:rgba(255,255,255,.82);white-space:nowrap}

  /* Shockwave ring behind the card. */
  .cue-ring{position:absolute;left:50%;top:50%;width:300px;height:300px;margin:-150px 0 0 -150px;
            border-radius:50%;border:6px solid var(--brand);opacity:0;
            animation:cue-ring 1.1s ease-out both}

  /* Diagonal speed streaks, swept across once. */
  .cue-streaks{position:absolute;inset:0;opacity:.16;
               background:repeating-linear-gradient(115deg,#fff 0 3px,transparent 3px 26px);
               animation:cue-streaks 1.5s ease-out both}

  @keyframes cue-life{
    0%  {opacity:0;transform:scale(.52) translateY(26px)}
    11% {opacity:1;transform:scale(1.07) translateY(0)}
    17% {transform:scale(1)}
    80% {opacity:1;transform:scale(1)}
    100%{opacity:0;transform:scale(1.14) translateY(-18px)}
  }
  @keyframes cue-ring{
    0%  {transform:scale(.35);opacity:.9}
    65% {opacity:0}
    100%{transform:scale(2.6);opacity:0}
  }
  @keyframes cue-streaks{
    0%  {transform:translateX(-60%)}
    100%{transform:translateX(60%)}
  }
  @keyframes cue-shake{
    0%,100%{margin-left:0} 15%{margin-left:-9px} 30%{margin-left:8px}
    45%{margin-left:-6px} 60%{margin-left:4px} 75%{margin-left:-2px}
  }
  @keyframes cue-throb{
    0%,100%{opacity:1} 50%{opacity:.55}
  }

  /* ── Per-event treatments ── */

  /* Safe raid: the quiet one. Gold, no shake. */
  .cue-safe_raid .cue-card{border-color:#F5A524;box-shadow:0 26px 80px rgba(0,0,0,.72),0 0 60px rgba(245,165,36,.32)}
  .cue-safe_raid .cue-label{color:#FFD98A}
  .cue-safe_raid .cue-ring{border-color:#F5A524}

  /* Super raid: the big one — green, shakes, keeps the streaks. */
  .cue-super_raid .cue-card{border-color:#22C55E;animation:cue-life 3s cubic-bezier(.16,.84,.3,1) both,
                            cue-shake .55s ease-out both;
                            box-shadow:0 26px 80px rgba(0,0,0,.72),0 0 70px rgba(34,197,94,.4)}
  .cue-super_raid .cue-label{color:#86EFAC;font-size:84px}
  .cue-super_raid .cue-ring{border-color:#22C55E}

  /* Do or die: tense. Red, throbbing label, no ring. */
  .cue-do_or_die .cue-card{border-color:#DC2626;box-shadow:0 26px 80px rgba(0,0,0,.72),0 0 64px rgba(220,38,38,.42)}
  .cue-do_or_die .cue-label{color:#FCA5A5;animation:cue-throb .5s steps(2,end) 4}
  .cue-do_or_die .cue-ring{display:none}

  /* All out: the most dramatic — wide, brand orange, shake plus streaks. */
  .cue-all_out .cue-card{border-color:#FF7A29;padding:34px 110px;
                         animation:cue-life 3s cubic-bezier(.16,.84,.3,1) both,
                                   cue-shake .7s ease-out both;
                         box-shadow:0 26px 90px rgba(0,0,0,.76),0 0 80px rgba(255,122,41,.45)}
  .cue-all_out .cue-label{color:#FFB37A;font-size:92px;letter-spacing:8px}
  .cue-all_out .cue-ring{border-color:#FF7A29}

  @media (max-width:760px){ .cue{display:none} }
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

  {{-- Event sting. Injected on a scoring click, removes itself after 3s. --}}
  <div class="cue" id="cue" aria-live="polite"></div>

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

  /* ── Raid countdown audio (opt-in) ──────────────────────────────────────
     The countdown bed now plays from the Raid In button on the timer console,
     so this page is SILENT by default — two pages open on one machine would
     otherwise play the same bed twice, slightly out of phase.

     Add ?audio=1 to this page's URL (e.g. on the OBS browser source) to put the
     countdown into the stream mix instead of the operator's machine, and mute
     the timer page with its own ?mute=1. ?vol=0.4 trims the level. */
  var params = new URLSearchParams(window.location.search);
  var audioOn = params.get('audio') === '1';
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

  /* ── Paint only what changed ──────────────────────────────────────────────
     State arrives several times a second, and almost every frame says exactly
     what the previous one said. Each of these compares against what is on
     screen now and writes nothing when they agree.

     It matters more here than on a console. This page is a video source: a
     redundant write is wasted work on the machine running OBS, and rebuilding
     a node that has not changed can show as a flicker on air — which is what
     the squad pips were doing, since they were torn down and rebuilt on every
     single frame.

     The DOM is the comparison point wherever it can be, rather than a cache
     kept alongside it. The markup is server-rendered with the opening scoreline
     already in place, so there is nothing to seed and nothing that can fall out
     of step with what a viewer is actually looking at. */

  /** Write text only if it differs. Returns true when the DOM was touched. */
  function write(el, value) {
    var next = String(value == null ? '' : value);
    if (el.textContent === next) { return false; }
    el.textContent = next;
    return true;
  }

  // The pips are built here, not server-rendered, so this one needs a record of
  // what it last drew.
  var paintedSquad = { L: null, R: null };

  function squad(prefix, left) {
    if (paintedSquad[prefix] === left) { return; }
    paintedSquad[prefix] = left;

    var html = '';
    for (var i = 1; i <= FULL; i++) {
      html += '<span class="pip' + (i <= left ? '' : ' spent') + '"><i class="fa-solid fa-user"></i></span>';
    }
    document.getElementById('squad' + prefix).innerHTML = html;
  }

  /* Pop the number when a point lands so it reads on a stream even if the
     viewer glanced away — and only then. An unchanged number must not re-run
     the animation, which would read on air as a point being scored twice. */
  function setScore(prefix, value) {
    if (! write(document.getElementById('score' + prefix + 'v'), value)) {
      return;
    }

    var tile = document.getElementById('score' + prefix);
    tile.classList.remove('bump');
    void tile.offsetWidth;                    // restart the animation
    tile.classList.add('bump');
  }

  function setCrest(prefix, data) {
    var crest = document.getElementById('crest' + prefix);
    var img = crest.firstElementChild;

    if (data.logo) {
      // getAttribute, not .src: the property is resolved to an absolute URL and
      // would never match the relative path the payload carries.
      if (img) {
        if (img.getAttribute('src') !== data.logo) { img.setAttribute('src', data.logo); }
      } else {
        crest.innerHTML = '<img src="' + data.logo + '" alt="">';
      }
      return;
    }

    if (img) { crest.textContent = data.short; return; }   // logo was removed
    write(crest, data.short);
  }

  function setSide(prefix, data) {
    write(document.getElementById('name' + prefix), data.name);
    setScore(prefix, data.score);
    squad(prefix, data.players);
    setCrest(prefix, data);
  }

  function tag(id, value) {
    var el = document.getElementById(id);
    write(el.firstElementChild, value);
    el.classList.toggle('empty', !value);     // already a no-op when unchanged
  }

  /* ── Event sting ────────────────────────────────────────────────────────
     Fires when the scoreboard records a raid preset.

     The same event reaches this page twice by design. First as the console's
     own guess, relayed the instant the operator clicks — that is what puts the
     card on air in step with the scoreline instead of a round trip behind it.
     Then as the server's record of it, carrying the real event id.

     Authoritative cues are ordered by that id, so a cue already played, or one
     that was already standing when this page loaded, never repeats. A guess
     has no id yet, so each one books a slot which the matching record spends
     silently. Clicking the same preset twice therefore still gives two stings,
     and a slot expires so an action that failed cannot mute a later event. */
  var cueHost = document.getElementById('cue');
  var lastCueId = 0;
  var cueSeeded = false;
  var lastPid = null;
  var pendingCues = [];
  var cueTimer = null;

  function bookGuess(cue) {
    pendingCues.push({ key: cue.type + '|' + cue.team, at: Date.now() });
  }

  function spendGuess(cue) {
    var cutoff = Date.now() - 10000;
    pendingCues = pendingCues.filter(function (p) { return p.at > cutoff; });

    var key = cue.type + '|' + cue.team;
    for (var i = 0; i < pendingCues.length; i++) {
      if (pendingCues[i].key === key) {
        pendingCues.splice(i, 1);
        return true;
      }
    }
    return false;
  }

  function eventSting(cue) {
    /* The first state this page sees is the match as it already stands. Record
       where the feed had got to without replaying it — and do that even when
       there is no event yet, so the first event of a fresh match still plays.
       Keying this off "no cue seen yet" swallowed it. */
    if (!cueSeeded) {
      cueSeeded = true;
      lastCueId = cue ? cue.id : 0;
      return;
    }

    if (!cue) { return; }

    if (cue.provisional) {
      if (cue.pid && cue.pid === lastPid) { return; }   // same guess, re-sent
      lastPid = cue.pid;
      bookGuess(cue);
    } else {
      if (cue.id <= lastCueId) { return; }              // already shown
      lastCueId = cue.id;
      if (spendGuess(cue)) { return; }                  // played on the click
    }

    clearTimeout(cueTimer);
    cueHost.className = 'cue cue-' + cue.type;
    cueHost.innerHTML =
      '<div class="cue-card">' +
        '<div class="cue-ring"></div>' +
        '<div class="cue-streaks"></div>' +
        '<div class="cue-label">' + escapeHtml(cue.label) + '</div>' +
        '<div class="cue-sub">' + escapeHtml(cue.team_name || '') + '</div>' +
      '</div>';

    // The CSS animation runs exactly 3s; clear the node once it has finished.
    cueTimer = setTimeout(function () { cueHost.innerHTML = ''; }, 3000);
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  MatchLive.init({
    url: '{{ route('admin.matches.state', $match, false) }}',
    state: @json($match->toScreenPayload()),
    socket: @json($socket),

    onPaint: function (t) {
      /* Runs about four times a second off the shared ticker, while both of
         these change at most once a second — so three in four frames have
         nothing to say and write nothing. */
      write(clockEl, t.matchClock);
      mclockEl.classList.toggle('stopped', !t.matchRunning);

      write(raidValEl, t.raidSeconds);
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
      eventSting(d.cue);
    }
  });
})();
</script>
</body>
</html>
