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

  2. FALLBACK. An HTTP reconcile still runs: every 30s while the socket is up,
     purely to re-baseline the clock offset, and every second when it is down,
     where it becomes the primary transport. Only ever ONE is in flight, it
     stands aside while the operator's own action is being sent, and screens
     open in the same browser share their frames so the server is asked once
     rather than once per screen.

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
  /* No socket: poll tight enough that a raid change is not visibly late. This
     is the primary transport on hosting that cannot run a WebSocket server, so
     it has to be good rather than merely a fallback.

     Safe to keep short because reconcile() allows only one request in flight —
     on a slow server it self-throttles to whatever the server can answer
     instead of stacking requests up behind each other. */
  var pollFast = 1000;
  var pollSlow = 30000;      // socket up: just re-baseline the clock offset
  var ticker = null;         // Web Worker driving the repaints
  var lastFrameAt = 0;       // when authoritative state last arrived
  var downSince = null;      // when the socket was first seen disconnected
  var httpHealthy = false;   // last known health of the HTTP reconcile
  var socketConfigured = false;  // was a WebSocket endpoint supplied at all?

  /* ── Cross-screen relay ──────────────────────────────────
     Every open screen used to fetch on its own, so running the console with
     the two boards meant three or four requests a second — and a server that
     answers one request at a time makes each screen wait for all the others.
     PHP's built-in server (what `artisan serve` runs) is exactly that: four
     simultaneous requests measured 493, 1005, 1519 and 2025 ms.

     Screens open in the same browser now share one BroadcastChannel. Whoever
     fetched last hands the frame straight to the rest, so they see a raid
     start without waiting for their own turn at the server, and a screen that
     is being fed this way skips its own poll.

     Nothing is elected. If the feeding screen closes, the others simply stop
     hearing frames and resume polling within one interval. A screen in another
     browser — OBS, a venue display — hears nothing and keeps polling, which is
     the behaviour it had before. */
  var relay = null;
  var relayAt = 0;           // when a frame last arrived from another screen
  var ownFetchAt = 0;        // when this screen last fetched for itself
  var wantsFeed = false;     // the console needs the feed, so it always fetches

  /* ── Guesses adopted from another screen ─────────────────
     A screen that did not originate a tap has no ticket for it, so it has
     nothing to re-apply. Until the server confirms that tap, ANY frame
     composed before it committed will overwrite the guess — the score going up
     the instant the button is pressed, dropping back, then climbing again when
     the action finally lands.

     That window is not rare. The server answers one request at a time, so a
     poll that was already queued, or a push from a tap just before this one, is
     composed in exactly that gap and carries the old score.

     While a guess is outstanding this screen therefore trusts only the screen
     that made it. That screen holds the ticket, re-applies anything still
     unacknowledged, and relays the result — so its frames are the only ones
     guaranteed to include the tap. Polls and socket pushes are set aside until
     it says the tap has landed, or the guess times out. */
  var guessHeldAt = 0;
  var GUESS_TTL = 8000;

  function holdingGuess() {
    return guessHeldAt !== 0 && (Date.now() - guessHeldAt) < GUESS_TTL;
  }

  var actionSeq = 0;         // bumped for every console action
  var lastRtt = 0;           // last measured round trip, ms
  var lastServerMs = null;   // what the server said it spent on that request

  /* ── Unacknowledged actions ──────────────────────────────
     Every response describes the match as it stood when that one action was
     composed — so the reply to the first of five quick taps says 41, and
     adopting it plainly would throw away the guesses for taps two to five.
     That is what made a burst of points climb to 45, fall back, and then count
     up again one at a time.

     So each action keeps a ticket holding the mutations it applied locally.
     A ticket is torn up the moment its own response arrives, and whatever is
     still outstanding is re-applied on top of every frame that lands. The
     screen therefore shows the server's state plus the taps the server has not
     answered yet, which is the only version that is never wrong for long. */
  var pendingActions = [];
  var currentTicket = null;  // the ticket a patch() call belongs to
  var actionsInFlight = 0;   // requests still open, counted separately

  /* Backstop for a request that never settles at all, since a ticket that
     outlives its response would inflate the score until it expired. Generous
     on purpose: a queue of taps against a server that handles one request at a
     time has been measured taking nearly nine seconds to drain, and expiring a
     ticket while its request is still open would bring back the very walk-back
     this mechanism exists to prevent. */
  var TICKET_TTL = 30000;

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

  /**
   * Is this frame behind the one we already hold?
   *
   * Every payload carries the server clock reading it was built with, which
   * makes frames orderable: a frame built later cannot know less about the
   * match, because an action always commits before its response is composed.
   * That lets a reply which overtakes a newer one be dropped instead of
   * rolling the match back — the raid clock snapping to 30 and OUT for a
   * moment and then jumping forward again, which is what made starting a raid
   * look broken when the server was slow to answer.
   *
   * Deliberately bounded. A frame that is far behind means something larger
   * than a race — the server's clock being corrected, or this machine waking
   * from sleep — and refusing those forever would freeze the screen.
   *
   * Only ever applied to frames the SERVER built. A guess is not a snapshot of
   * the match at some moment; it is the latest another screen knew plus the tap
   * its operator has just made. Judging it on the age of the frame it was built
   * on meant that whenever that screen's last poll happened to be older than
   * ours — routine when the server takes a second to answer — the guess was
   * thrown away and the tap only appeared a round trip later. Anything of our
   * own that a guess does not know about is put back by replayPending(), and
   * the originating screen relays the confirmed state straight after.
   */
  function isStale(payload) {
    if (!state || !state.now || !payload.now) { return false; }
    var behind = state.now - payload.now;
    return behind > 0 && behind < 30000;
  }

  /**
   * Adopt a payload as the current state.
   *
   * meta.relayed — it came from another screen, so don't echo it back.
   * meta.provisional — it is somebody's optimistic guess, not the server's
   * word, so it must not count as authoritative state having arrived, and it
   * is exempt from the ordering check because it is derived from the frame we
   * already hold rather than built by the server.
   */
  function apply(payload, t0, t1, extra, meta) {
    if (!payload) { return; }
    meta = meta || {};

    // The offset sample is good even when the body turns out to be stale: it
    // measures the round trip, not the contents.
    if (t0 && t1 && payload.now) { syncOffset(t0, t1, payload.now); }

    if (!meta.provisional && isStale(payload)) { return; }

    /*
     * Holding somebody else's guess: a poll answer or a socket push cannot be
     * trusted to contain the tap it is waiting on, so set it aside. Only the
     * screen that made the guess knows when it has landed, and it relays that
     * itself — see the note on guessHeldAt.
     */
    if (!meta.provisional && !meta.relayed && holdingGuess()) { return; }

    // The originating screen has spoken: the tap is accounted for.
    if (!meta.provisional && meta.relayed) { guessHeldAt = 0; }

    state = payload;
    if (!meta.provisional) { lastFrameAt = Date.now(); }

    // Put back the taps this frame cannot know about yet.
    replayPending();

    if (onState) { onState(state, extra); }
    paint();
    if (!meta.relayed) { share(state, !!meta.provisional); }
  }

  /** Forget tickets whose response is never going to arrive. */
  function prunePending() {
    var cutoff = Date.now() - TICKET_TTL;
    pendingActions = pendingActions.filter(function (t) { return t.at > cutoff; });
  }

  function forgetTicket(ticket) {
    var i = pendingActions.indexOf(ticket);
    if (i !== -1) { pendingActions.splice(i, 1); }
  }

  /**
   * Re-apply every outstanding optimistic change on top of the frame just
   * adopted, oldest first.
   *
   * Each step is handed the instant it was originally made, not the time now —
   * replaying a raid start with a fresh timestamp would restart the raid clock.
   * The replaying flag lets a page skip anything that should happen once only,
   * such as putting an event sting on air.
   */
  function replayPending() {
    prunePending();

    pendingActions.forEach(function (ticket) {
      ticket.steps.forEach(function (step) {
        try { step.fn(state, step.at, true); } catch (e) { /* skip a bad step */ }
      });
    });
  }

  /** Hand a frame to the other screens open in this browser. */
  function share(payload, provisional) {
    if (!relay) { return; }
    try {
      relay.postMessage({
        kind: 'state',
        at: Date.now(),
        provisional: !!provisional,
        payload: payload
      });
    } catch (e) { /* a closed channel must never break the paint */ }
  }

  function openRelay(stateUrl) {
    if (typeof BroadcastChannel === 'undefined') { return; }

    // One channel per match. The state URL already identifies it, so no screen
    // needs to pass an id in: strip the query (the console adds ?feed=1).
    try {
      relay = new BroadcastChannel('adt-match:' + stateUrl.replace(/\?.*$/, ''));
    } catch (e) {
      return;                                   // relay is a bonus, not a need
    }

    relay.onmessage = function (e) {
      var msg = e.data;
      if (!msg || msg.kind !== 'state' || !msg.payload) { return; }

      /* A frame from another screen cannot know about an action of ours that
         is still in the air — but it no longer has to: apply() puts anything
         outstanding back on top. This used to drop such frames outright, which
         meant a screen mid-action ignored everything the others were doing. */

      /* Server frames from another screen are ordered like our own. Guesses
         are not ordered at all — see isStale. */
      if (!msg.provisional && isStale(msg.payload)) { return; }

      // Only a real server frame proves somebody is polling on our behalf.
      if (!msg.provisional) { relayAt = Date.now(); }

      if (msg.provisional) {
        guessHeldAt = Date.now();

        /* Whatever is in the air right now was composed before this tap, so it
           would undo the guess the moment it lands. */
        if (inflight) { inflight.abort(); inflight = null; }
      }

      apply(msg.payload, null, null, null, {
        relayed: true,
        provisional: msg.provisional
      });
    };
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

    var at = serverNow();
    try { mutator(state, at, false); } catch (e) { /* never block the send */ }

    /* Remember it against the action being sent, so it survives the frames
       that arrive before the server has acknowledged this one. */
    if (currentTicket) { currentTicket.steps.push({ fn: mutator, at: at }); }

    if (onState) { onState(state); }
    paint();

    /* Pass the guess to the other screens too. Both clocks are derived from
       timestamps and every screen in this browser reads the same machine
       clock, so the boards start counting on the same frame as the console
       instead of a round trip later. The server's own frame follows and
       corrects anything the operator's click did not actually achieve. */
    share(state, true);
  }

  /* ── HTTP fallback ───────────────────────────────────────── */

  /**
   * One reconcile at a time. Without this guard a slow response plus a short
   * interval meant each tick queued another request behind the last, and the
   * backlog grew until every response took many seconds.
   */
  function reconcile(force) {
    if (inflight) { return Promise.resolve(); }

    /*
     * Another screen in this browser is fetching and sharing its frames, so
     * there is nothing to learn from fetching the same thing ourselves — and
     * on a one-request-at-a-time server, not asking is what makes the other
     * screens fast.
     *
     * The window is two intervals wide so an ordinary slow response doesn't
     * make every screen start fetching again; if the feeding screen is closed,
     * the frames dry up and this one is back to polling within that window.
     * It still takes a turn every pollSlow to re-measure the clock offset, and
     * the console never skips, because the match feed only comes back on its
     * own ?feed=1 request.
     */
    if (!force && !wantsFeed
        && relayAt && (Date.now() - relayAt) < pollFast * 2
        && (Date.now() - ownFetchAt) < pollSlow) {
      return Promise.resolve();
    }

    /* Waiting on somebody else's tap to land: there is nothing the server can
       tell us yet that is newer than the guess we are already showing, and the
       answer would only have to be set aside on arrival. */
    if (holdingGuess()) { return Promise.resolve(); }

    var ctl = new AbortController();
    inflight = ctl;
    var t0 = Date.now();
    var seqAtStart = actionSeq;
    ownFetchAt = t0;

    /* Cache-bust every poll. no-store covers well-behaved caches, but a
       server-level cache (LiteSpeed on shared hosting, a CDN, a corporate
       proxy) may key purely on the URL and serve a stale clock — which makes
       the boards sit seconds behind the console. A unique URL per request
       cannot be served from any of them. */
    var bust = url + (url.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now();

    return fetch(bust, {
      headers: { 'Accept': 'application/json', 'Cache-Control': 'no-cache' },
      cache: 'no-store',
      signal: ctl.signal
    })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d) { throw new Error('bad state response'); }

        lastRtt = Date.now() - t0;
        lastServerMs = (typeof d.server_ms === 'number') ? d.server_ms : null;
        mark(true);

        /*
         * An action was fired while this request was in the air, so the server
         * may have read the match BEFORE that action committed — in which case
         * this payload is older than what we already show, and applying it
         * would snap the raid clock back to 30 and OUT for a moment before the
         * action's own response put it right again. The action's response is
         * the newer word, so drop this body and keep only its timing.
         */
        if (actionSeq !== seqAtStart) {
          if (d.now) { syncOffset(t0, Date.now(), d.now); }
          return;
        }

        apply(d, t0, Date.now());
      })
      .catch(function (e) {
        if (e.name !== 'AbortError') { mark(false); }   // hold the last paint
      })
      .finally(function () {
        if (inflight === ctl) { inflight = null; }
      });
  }

  /**
   * Reflect transport health: #offline on the boards, #mlConn on the consoles.
   *
   * httpOk is optional. The socket's own open/close path calls this with no
   * argument so it reports the socket without overwriting what we last knew
   * about HTTP — previously a refused WebSocket flipped the chip to "Offline"
   * even though polling was working fine, so it flickered between Offline and
   * Polling once per reconnect attempt.
   */
  /**
   * Split the last request into the server's share and the rest.
   *
   * A round trip far longer than the server's own time means the request spent
   * that time waiting its turn, not being answered — the signature of several
   * screens polling a server that handles one request at a time.
   */
  function timing() {
    if (!lastRtt) { return ''; }

    var bits = ['round trip ' + lastRtt + ' ms'];

    if (lastServerMs !== null) {
      bits.push('server ' + lastServerMs + ' ms');

      if (lastRtt > 400 && lastRtt > lastServerMs * 3) {
        bits.push('the server answered quickly, so the wait is requests queueing in front of this one');
      }
    }

    return bits.join(' · ');
  }

  function mark(httpOk) {
    if (httpOk !== undefined) { httpHealthy = httpOk; }

    var healthy = connected || httpHealthy;
    var off = document.getElementById('offline');
    if (off) { off.classList.toggle('on', !healthy); }

    var chip = document.getElementById('mlConn');
    if (chip) {
      if (connected) {
        chip.textContent = 'Live';
        chip.className = 'ml-conn ok';
      } else if (httpHealthy) {
        /* Polling is the intended, supported mode when no WebSocket server is
           configured — shared hosting cannot run one — so present it as normal
           rather than as a fault. It is only a warning when a socket exists
           and has dropped. */
        chip.textContent = socketConfigured ? 'Polling' : 'Live (HTTP)';
        chip.className = 'ml-conn ' + (socketConfigured ? 'warn' : 'ok');
      } else {
        chip.textContent = 'Offline';
        chip.className = 'ml-conn bad';
      }

      /* Hover the chip to see where the wait went. The browser only shows the
         total round trip, which hides the difference between a slow server and
         a fast one with a queue in front of it — and the fix for each is a
         different one. */
      if (chip._title === undefined) { chip._title = chip.getAttribute('title') || ''; }
      var detail = timing();
      chip.title = detail ? (chip._title ? chip._title + ' — ' + detail : detail) : chip._title;
    }

    /* The "start the WebSocket server" hint is only actionable when a socket
       was actually configured. With broadcasting switched off it would be
       advice the operator cannot follow, so it stays hidden. */
    if (connected || !socketConfigured) {
      downSince = null;
    } else if (!downSince) {
      downSince = Date.now();
    }

    var hint = document.getElementById('mlHint');
    if (hint) {
      hint.hidden = !(socketConfigured && downSince && (Date.now() - downSince) > 8000);
    }
  }

  function repoll(interval) {
    clearInterval(pollTimer);
    pollTimer = setInterval(reconcile, interval);
  }

  /* ── WebSocket (Pusher protocol, public channel only) ────── */

  function connectSocket(cfg) {
    if (!cfg || !cfg.key || typeof WebSocket === 'undefined') {
      // Expected whenever broadcasting is off (shared hosting, for instance).
      // Not a fault: the reconcile below keeps every screen current.
      repoll(pollFast);
      return;
    }

    socketConfigured = true;

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
          reconcile(true);                   // re-baseline after (re)connecting
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
   * +1 three times must register three points.
   *
   * The guard below exists only to swallow the same submit arriving twice for
   * one press, which happens in the same event-loop turn. It was 200ms, which
   * is slow human tapping — five quick taps on +1 lost two of them, and the
   * score ended up lower than the operator had counted.
   */
  function send(form) {
    var now = Date.now();
    if (form._last && (now - form._last) < 40) { return; }
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

    /* Mark the action before anything else happens, so a reconcile already in
       flight knows its body is stale even if it lands during the guess. */
    actionSeq++;

    var ticket = { seq: actionSeq, at: Date.now(), steps: [] };
    pendingActions.push(ticket);
    actionsInFlight++;

    /* Clear the road for the operator's action: abort the running poll and
       stop the next tick from starting one. On a server that handles a single
       request at a time, a poll firing now costs the click its entire round
       trip — which is what made starting the clock feel slow. */
    if (inflight) { inflight.abort(); inflight = null; }
    clearInterval(pollTimer);

    /* Show the expected result immediately; the authoritative frame follows.
       Anything patch() does while this is set belongs to this action's ticket. */
    currentTicket = ticket;
    if (onSend) {
      try { onSend(form, body); } catch (e) { /* never block the send */ }
    }
    currentTicket = null;

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
        /* This action is now accounted for either way: on success the frame
           below contains it, on failure it never happened. Tearing the ticket
           up first is what stops its own delta being counted twice. */
        forgetTicket(ticket);

        if (!res.ok) {
          var errs = res.body.errors || {};
          var first = Object.keys(errs).length
            ? errs[Object.keys(errs)[0]][0]
            : (res.body.message || ('Request failed (' + res.status + ').'));
          toast(first, false);
          reconcile(true);                    // drop the optimistic guess
          return;
        }
        if (res.body.state) {
          lastServerMs = (typeof res.body.state.server_ms === 'number')
            ? res.body.state.server_ms
            : lastServerMs;
          apply(res.body.state, null, null, res.body);
        }
        // 200 with ok:false is a business refusal, e.g. an empty undo stack.
        toast(res.body.message, res.body.ok !== false);
      })
      .catch(function () {
        forgetTicket(ticket);
        toast('Action failed — check the connection.', false);
        reconcile(true);
      })
      .finally(function () {
        form._clicked = null;
        actionsInFlight = Math.max(0, actionsInFlight - 1);

        /* Hand the clock back to the poll once the operator's actions settle.
           Counted here rather than off the ticket list, because a ticket can
           outlive its request by design and polling must not be left switched
           off waiting for one to expire. */
        if (actionsInFlight === 0) { repoll(connected ? pollSlow : pollFast); }
      });
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

    // The console asks for the match feed, which only its own request carries,
    // so it must keep fetching for itself rather than riding on a board.
    wantsFeed = /[?&]feed=1/.test(url);
    openRelay(url);

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
    reconcile(true);                          // establishes the clock offset

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
      if (relay) { try { relay.close(); } catch (e) { /* already gone */ } }
    });
  }

  return {
    init: init,
    bindForms: bindForms,
    refresh: function () { return reconcile(true); },
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

  /* Shown on the consoles only, once the socket has been down a few seconds. */
  .ml-hint{display:flex;align-items:center;gap:9px;background:rgba(217,119,6,.12);
           border:1px solid rgba(217,119,6,.35);border-radius:8px;padding:10px 14px;
           font-size:12.5px;color:#FCD34D;margin-bottom:16px;line-height:1.5}
  .ml-hint[hidden]{display:none}
  .ml-hint code{background:rgba(0,0,0,.4);border-radius:4px;padding:2px 7px;
                font-size:12px;color:#FDE68A;white-space:nowrap}
</style>
@once
<div class="ml-toast" id="mlToast" role="status" aria-live="polite"></div>
@endonce
