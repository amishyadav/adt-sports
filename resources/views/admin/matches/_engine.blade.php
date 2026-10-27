{{--
  Shared runtime for the four match screens.

  Four jobs:

  1. TRANSPORT. State is pushed over a WebSocket (Laravel Reverb) the instant an
     operator acts. Reverb speaks the Pusher protocol, and the slice needed here
     is tiny — connect, subscribe to one public channel, answer pings — so it is
     implemented directly rather than pulling in pusher-js. That removes a CDN
     (which is unreachable from some networks, and was silently leaving every
     screen stuck on the HTTP fallback) and means a venue display or OBS machine
     needs no internet access at all.

  2. FALLBACK. An HTTP reconcile still runs: 30s while the socket is up, purely
     to re-baseline the clock offset, dropping to 5s if it is down. Only ever
     ONE is in flight — overlapping polls were stacking up behind each other and
     turning into multi-second waits.

  3. CLOCKS. Both countdowns are derived from server timestamps, never counted
     locally, so the match clock and the 30s raid clock cannot drift apart.

  4. ACTIONS. Console forms submit over fetch and repaint in place. patch() lets
     a page apply the expected result immediately, so a click never waits on the
     round trip.
--}}
<script>
window.MatchLive = (function () {

  var state = null;          // latest payload from the server
  var offset = 0;            // serverNow - clientNow, in ms
  var bestRtt = Infinity;
  var bestAt = 0;
  var url = null;
  var onPaint = null;
  var onState = null;
  var onSend = null;

  var ws = null;
  var wsUrl = null;
  var channel = null;
  var connected = false;
  var backoff = 1000;
  var closedByUs = false;

  var pollTimer = null;
  var inflight = null;       // AbortController for the running reconcile
  var pollFast = 5000;       // socket down
  var pollSlow = 30000;      // socket up: just re-baseline the clock offset
  var ticker = null;         // Web Worker driving the repaints
  var lastFrameAt = 0;       // when authoritative state last arrived

  function serverNow() { return Date.now() + offset; }

  /* NTP-style offset estimate from an HTTP reconcile: trust the lowest-latency
     sample, assuming the timestamp refers to the midpoint of the round trip.
     Pushed frames never touch this — they carry absolute timestamps and their
     one-way latency is unknown. */
  function syncOffset(t0, t1, serverMs) {
    var rtt = t1 - t0;
    if (rtt < bestRtt || (t1 - bestAt) > 30000) {
      bestRtt = rtt;
      bestAt = t1;
      offset = serverMs - (t0 + rtt / 2);
    }
  }

  /* Mirrors the server's elapsed_seconds accessor exactly — banked seconds plus
     whole seconds since the current run started. */
  function elapsedOf(c) {
    var e = c.elapsed || 0;
    if (c.running && c.started_at) {
      e += Math.floor((serverNow() - c.started_at) / 1000);
    }
    return e;
  }

  function matchSeconds() {
    if (!state) { return 0; }
    var c = state.clocks.timer;
    return Math.max(0, c.duration - elapsedOf(c));
  }

  function raidSeconds() {
    if (!state) { return 0; }
    var r = state.clocks.raid;
    if (!r.running || !r.started_at) { return r.duration; }
    return Math.max(0, r.duration - Math.floor((serverNow() - r.started_at) / 1000));
  }

  function format(total) {
    var h = Math.floor(total / 3600);
    var m = Math.floor((total % 3600) / 60);
    var s = total % 60;
    var mm = String(m).padStart(2, '0');
    var ss = String(s).padStart(2, '0');
    return h > 0 ? h + ':' + mm + ':' + ss : mm + ':' + ss;
  }

  function paint() {
    if (!state) { return; }

    /* How long since authoritative state last arrived. Surfaced on the boards
       so "it feels laggy" becomes a number you can read off the screen. */
    var stale = lastFrameAt ? (Date.now() - lastFrameAt) : 0;
    var age = document.getElementById('mlAge');
    if (age) {
      var late = stale > 6000;
      age.textContent = connected
        ? (late ? 'LIVE · ' + Math.round(stale / 1000) + 's' : 'LIVE')
        : 'POLLING · ' + Math.round(stale / 1000) + 's';
      age.classList.toggle('late', late || !connected);
    }

    if (!onPaint) { return; }
    var ms = matchSeconds();
    var rs = raidSeconds();
    onPaint({
      state: state,
      matchSeconds: ms,
      matchClock: format(ms),
      matchRunning: state.clocks.timer.running,
      raidSeconds: rs,
      raidRunning: state.clocks.raid.running,
      raidTeam: state.raid_team,
      staleMs: stale
    });
  }

  function apply(payload, t0, t1, extra) {
    if (!payload) { return; }
    if (t0 && t1 && payload.now) { syncOffset(t0, t1, payload.now); }
    state = payload;
    lastFrameAt = Date.now();
    if (onState) { onState(payload, extra); }
    paint();
  }

  function safePaint() {
    try { paint(); } catch (e) { /* a bad frame must not stop the clock */ }
  }

  /**
   * Drive repaints from a Web Worker.
   *
   * A main-thread setInterval is throttled to ~1Hz, then to ~1/minute, as soon
   * as the window is hidden or simply covered by another window — which made the
   * clock on a venue display lag behind the console even though pushed scores
   * arrived instantly. Worker timers escape that throttling. If the Worker can't
   * be created for any reason we fall back to the plain interval, which is still
   * correct whenever the page is actually on screen.
   */
  function startTicker() {
    try {
      ticker = new Worker('/js/match-ticker.js');
      ticker.onmessage = safePaint;
      ticker.onerror = function (e) {
        console.warn('[MatchLive] Ticker worker failed; using a timer.', e);
        ticker = null;
        setInterval(safePaint, 250);
      };
      ticker.postMessage({ cmd: 'start', interval: 250 });
    } catch (e) {
      console.warn('[MatchLive] Worker unavailable; using a timer.', e);
      setInterval(safePaint, 250);
    }
  }

  /**
   * Apply an expected change to the local state right now, before the server
   * has answered. The authoritative frame overwrites it moments later, so a
   * rejected action self-corrects.
   */
  function patch(mutator) {
    if (!state) { return; }
    try { mutator(state, serverNow()); } catch (e) { /* never block the send */ }
    if (onState) { onState(state); }
    paint();
  }

  /* ── HTTP fallback ───────────────────────────────────────── */

  /**
   * One reconcile at a time. Without this guard a slow response plus a short
   * interval meant each tick queued another request behind the last, and the
   * backlog grew until every response took many seconds.
   */
  function reconcile() {
    if (inflight) { return Promise.resolve(); }

    var ctl = new AbortController();
    inflight = ctl;
    var t0 = Date.now();

    return fetch(url, {
      headers: { 'Accept': 'application/json' },
      cache: 'no-store',
      signal: ctl.signal
    })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d) { throw new Error('bad state response'); }
        apply(d, t0, Date.now());
        mark(true);
      })
      .catch(function (e) {
        if (e.name !== 'AbortError') { mark(false); }   // hold the last paint
      })
      .finally(function () {
        if (inflight === ctl) { inflight = null; }
      });
  }

  /** Reflect transport health: #offline on the boards, #mlConn on the consoles. */
  function mark(httpOk) {
    var healthy = connected || httpOk;
    var off = document.getElementById('offline');
    if (off) { off.classList.toggle('on', !healthy); }

    var chip = document.getElementById('mlConn');
    if (chip) {
      chip.textContent = connected ? 'Live' : (httpOk ? 'Polling' : 'Offline');
      chip.className = 'ml-conn ' + (connected ? 'ok' : (httpOk ? 'warn' : 'bad'));
    }
  }

  function repoll(interval) {
    clearInterval(pollTimer);
    pollTimer = setInterval(reconcile, interval);
  }

  /* ── WebSocket (Pusher protocol, public channel only) ────── */

  function connectSocket(cfg) {
    if (!cfg || !cfg.key || typeof WebSocket === 'undefined') {
      console.warn('[MatchLive] No WebSocket config; using the HTTP fallback.');
      repoll(pollFast);
      return;
    }

    channel = 'match.' + cfg.matchId;
    wsUrl = (cfg.scheme === 'https' ? 'wss' : 'ws') + '://' + cfg.host + ':' + cfg.port
          + '/app/' + cfg.key + '?protocol=7&client=adt-live&version=1.0';
    openSocket();
  }

  function openSocket() {
    if (closedByUs) { return; }

    try {
      ws = new WebSocket(wsUrl);
    } catch (e) {
      console.warn('[MatchLive] WebSocket construction failed; using HTTP.', e);
      repoll(pollFast);
      return;
    }

    ws.onmessage = function (e) {
      var frame;
      try { frame = JSON.parse(e.data); } catch (_) { return; }

      switch (frame.event) {
        case 'pusher:connection_established':
          ws.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel: channel } }));
          break;

        case 'pusher_internal:subscription_succeeded':
          connected = true;
          backoff = 1000;
          repoll(pollSlow);
          reconcile();                       // re-baseline after (re)connecting
          mark(true);
          break;

        case 'pusher:ping':
          ws.send(JSON.stringify({ event: 'pusher:pong', data: {} }));
          break;

        case 'pusher:error':
          console.warn('[MatchLive] Reverb rejected the connection:', frame.data);
          break;

        case 'state':
          // Pusher delivers the payload as a JSON string.
          try {
            apply(typeof frame.data === 'string' ? JSON.parse(frame.data) : frame.data);
          } catch (_) { /* ignore a malformed frame */ }
          break;
      }
    };

    ws.onclose = function () {
      ws = null;
      if (connected) { console.warn('[MatchLive] WebSocket dropped; retrying.'); }
      connected = false;
      repoll(pollFast);
      mark(false);
      if (closedByUs) { return; }
      backoff = Math.min(backoff * 2, 10000);
      setTimeout(openSocket, backoff);
    };

    ws.onerror = function () { /* onclose always follows */ };
  }

  /* ── Console actions ─────────────────────────────────────── */

  function toast(message, ok) {
    var host = document.getElementById('mlToast');
    if (!host) { return; }
    host.textContent = message;
    host.className = 'ml-toast show' + (ok ? '' : ' bad');
    clearTimeout(host._t);
    host._t = setTimeout(function () { host.className = 'ml-toast'; }, 2600);
  }

  /**
   * Submit a console form over fetch and repaint from the response.
   *
   * No global lock: actions are additive and the server clamps them, so tapping
   * +1 three times must register three points. A short per-form guard absorbs an
   * accidental double-fire without dropping deliberate repeat presses.
   */
  function send(form) {
    var now = Date.now();
    if (form._last && (now - form._last) < 200) { return; }
    form._last = now;

    /* Read the URL from the ATTRIBUTE, never from form.action.
       Several of these forms carry a control named "action" (Start / Stop /
       Reset / Raid In / Raid Out), and a named form control shadows the
       HTMLFormElement property of the same name — so form.action returns that
       <input> element, not the URL. Stringified it becomes
       "[object HTMLInputElement]" and the request 404s. */
    var endpoint = new URL(form.getAttribute('action'), window.location.href).href;

    var body = new FormData(form);
    // Honour whichever submit button was clicked (Set vs Set & Start).
    if (form._clicked && form._clicked.name) {
      body.set(form._clicked.name, form._clicked.value);
    }

    // Show the expected result immediately; the authoritative frame follows.
    if (onSend) {
      try { onSend(form, body); } catch (e) { /* never block the send */ }
    }

    // Don't let the operator's action queue behind a running poll.
    if (inflight) { inflight.abort(); inflight = null; }

    fetch(endpoint, {
      method: 'POST',                        // _method in the body spoofs PUT
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: body
    })
      .then(function (r) {
        // A non-JSON error page (a 419 session timeout, say) must not break the
        // parse — fall back to an empty body and let the status decide.
        return r.json()
          .catch(function () { return {}; })
          .then(function (j) { return { ok: r.ok, status: r.status, body: j }; });
      })
      .then(function (res) {
        /* Anything other than 2xx is a failure. Only checking for 422 meant a
           404 or 419 arrived with ok === undefined and showed as success. */
        if (!res.ok) {
          var errs = res.body.errors || {};
          var first = Object.keys(errs).length
            ? errs[Object.keys(errs)[0]][0]
            : (res.body.message || ('Request failed (' + res.status + ').'));
          toast(first, false);
          reconcile();                        // drop the optimistic guess
          return;
        }
        if (res.body.state) { apply(res.body.state, null, null, res.body); }
        // 200 with ok:false is a business refusal, e.g. an empty undo stack.
        toast(res.body.message, res.body.ok !== false);
      })
      .catch(function () {
        toast('Action failed — check the connection.', false);
        reconcile();
      })
      .finally(function () { form._clicked = null; });
  }

  function bindForms() {
    // Remember the clicked button before submit, for multi-button forms.
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('button[type=submit],button:not([type])');
      if (btn && btn.form) { btn.form._clicked = btn; }
    }, true);

    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form.matches('form[data-live]')) { return; }
      var confirmMsg = form.getAttribute('data-confirm');
      if (confirmMsg && !window.confirm(confirmMsg)) { e.preventDefault(); return; }
      e.preventDefault();
      send(form);
    });
  }

  function init(opts) {
    url = opts.url;
    onPaint = opts.onPaint || null;
    onState = opts.onState || null;
    onSend = opts.onSend || null;

    /* The transport must come up even if the first paint fails. Previously an
       error in a page's onState/onPaint threw straight out of init(), so the
       socket was never opened and the fallback poll never scheduled — the page
       then sat frozen until a manual reload. */
    try {
      if (opts.state) { apply(opts.state); }
    } catch (e) {
      console.error('[MatchLive] First paint failed; carrying on.', e);
    }

    startTicker();
    connectSocket(opts.socket);
    reconcile();                              // establishes the clock offset

    // A hidden or occluded window gets its timers throttled; correct the moment
    // it comes back, and on focus/pageshow too (a window can be revealed
    // without firing visibilitychange).
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) { reconcile(); safePaint(); }
    });
    window.addEventListener('focus', safePaint);
    window.addEventListener('pageshow', function () { reconcile(); safePaint(); });

    window.addEventListener('beforeunload', function () {
      closedByUs = true;
      if (ws) { ws.close(); }
    });
  }

  return {
    init: init,
    bindForms: bindForms,
    refresh: reconcile,
    patch: patch,
    toast: toast,
    format: format,
    serverNow: serverNow,
    isConnected: function () { return connected; },
    get: function () { return state; }
  };
})();
</script>

<style>
  /* Toast + connection chip for the consoles. Harmless on the boards. */
  .ml-toast{position:fixed;left:50%;bottom:26px;transform:translateX(-50%) translateY(140%);
            background:#14532D;color:#DCFCE7;border:1px solid #166534;border-radius:8px;
            padding:11px 20px;font-size:13px;font-weight:600;z-index:9999;
            box-shadow:0 10px 30px rgba(0,0,0,.45);opacity:0;
            transition:transform .25s ease,opacity .25s ease;pointer-events:none;max-width:90vw}
  .ml-toast.show{transform:translateX(-50%) translateY(0);opacity:1}
  .ml-toast.bad{background:#4C1111;color:#FEE2E2;border-color:#7F1D1D}
  .ml-conn{display:inline-flex;align-items:center;gap:6px;font-size:10px;font-weight:700;
           letter-spacing:1.2px;text-transform:uppercase;padding:4px 10px;border-radius:12px;
           border:1px solid transparent}
  .ml-conn.ok{background:rgba(22,163,74,.16);color:#86EFAC;border-color:rgba(22,163,74,.3)}
  .ml-conn.warn{background:rgba(217,119,6,.16);color:#FCD34D;border-color:rgba(217,119,6,.3)}
  .ml-conn.bad{background:rgba(220,38,38,.16);color:#FCA5A5;border-color:rgba(220,38,38,.3)}
</style>
@once
<div class="ml-toast" id="mlToast" role="status" aria-live="polite"></div>
@endonce
