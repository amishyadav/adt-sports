@extends('layouts.admin')
@section('title','Scoreboard · '.$match->title)
@section('content')

@php
  $sides = [1 => $match->sideData(1), 2 => $match->sideData(2)];
  $full = \App\Models\TeamMatch::FULL_SQUAD;
@endphp

<div class="sb-title">
  Match: {{ $sides[1]['name'] }} <span class="sb-vs">vs</span> {{ $sides[2]['name'] }}
</div>

<div class="sb-sublinks">
  <a href="{{ route('admin.matches.timer',$match) }}" class="btn btn-amber btn-sm"><i class="fa-solid fa-stopwatch"></i> Timer</a>
  <a href="{{ route('admin.matches.screen',$match) }}" target="_blank" class="btn btn-danger btn-sm"><i class="fa-solid fa-tv"></i> Main Screen</a>
  <a href="{{ route('admin.matches.live',$match) }}" target="_blank" class="btn btn-ghost btn-sm"><i class="fa-solid fa-bolt"></i> Live Score</a>
  <a href="{{ route('admin.matches.index') }}" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i> All Matches</a>
  <span class="sb-clockchip">
    <i class="fa-solid fa-stopwatch"></i>
    <strong id="sbClock">{{ $match->clock }}</strong>
    <span id="sbMeta"></span>
  </span>
  <span class="sb-clockchip"><i class="fa-solid fa-bolt"></i> Raid <strong id="sbRaid">{{ $match->raid_remaining }}</strong></span>
  <span class="ml-conn warn" id="mlConn" title="Live = pushed over WebSockets; Polling = WebSocket server unreachable, falling back to HTTP">Connecting</span>
</div>

{{-- ── Team score cards ─────────────────────────────────── --}}
<div class="sb-grid">
  @foreach($sides as $side => $data)
    <div class="sb-card sb-t{{ $side }}">
      <div class="sb-team">{{ $data['name'] }}</div>

      <div class="sb-scorerow">
        <form action="{{ route('admin.matches.score',$match) }}" method="POST" data-live>
          @csrf @method('PUT')
          <input type="hidden" name="team" value="{{ $side }}">
          <input type="hidden" name="delta" value="-1">
          <button type="submit" class="sb-step sb-minus" title="Take one point off">−</button>
        </form>

        <div class="sb-score" id="sbScore{{ $side }}">{{ $data['score'] }}</div>

        <form action="{{ route('admin.matches.score',$match) }}" method="POST" data-live>
          @csrf @method('PUT')
          <input type="hidden" name="team" value="{{ $side }}">
          <input type="hidden" name="delta" value="1">
          <button type="submit" class="sb-step sb-plus" title="Add one point">+</button>
        </form>
      </div>

      <div class="sb-presets">
        @foreach(\App\Models\MatchEvent::PRESETS as $type => $preset)
          <form action="{{ route('admin.matches.event',$match) }}" method="POST" data-live>
            @csrf @method('PUT')
            <input type="hidden" name="team" value="{{ $side }}">
            <input type="hidden" name="type" value="{{ $type }}">
            <button type="submit" class="sb-chip sb-chip-{{ $type }}"
              title="{{ $type === 'all_out'
                ? $data['name'] . ' all out — +' . $preset['points'] . ' to the other side, both squads back to ' . $full
                : $preset['label'] . ' — +' . $preset['points'] . ' to ' . $data['name'] }}">
              {{ $preset['label'] }} <span class="sb-chip-pts">+{{ $preset['points'] }}</span>
            </button>
          </form>
        @endforeach
      </div>

      <div class="sb-adjust">
        <form action="{{ route('admin.matches.adjust',$match) }}" method="POST" class="sb-adjust-grp" data-live>
          @csrf @method('PUT')
          <input type="hidden" name="team" value="{{ $side }}">
          <input type="hidden" name="direction" value="minus">
          <input type="number" name="amount" value="1" min="1" max="99" class="sb-amount" aria-label="Points to subtract from {{ $data['name'] }}">
          <button type="submit" class="btn btn-amber btn-sm">Minus</button>
        </form>

        <span class="sb-adjust-label">Adjust</span>

        <form action="{{ route('admin.matches.adjust',$match) }}" method="POST" class="sb-adjust-grp" data-live>
          @csrf @method('PUT')
          <input type="hidden" name="team" value="{{ $side }}">
          <input type="hidden" name="direction" value="plus">
          <input type="number" name="amount" value="1" min="1" max="99" class="sb-amount" aria-label="Points to add to {{ $data['name'] }}">
          <button type="submit" class="btn btn-success btn-sm">Plus</button>
        </form>
      </div>
    </div>
  @endforeach
</div>

{{-- ── Players left ─────────────────────────────────────── --}}
<div class="sb-panel">
  <h3 class="sb-panel-hd">Players Left</h3>
  <div class="sb-grid sb-grid-tight">
    @foreach($sides as $side => $data)
      <div class="sb-subcard sb-t{{ $side }}">
        <div class="sb-team sb-team-sm">{{ $data['name'] }}</div>
        <div class="sb-dots">
          @for($n = $full; $n >= 1; $n--)
            <form action="{{ route('admin.matches.players',$match) }}" method="POST" data-live>
              @csrf @method('PUT')
              <input type="hidden" name="team" value="{{ $side }}">
              <input type="hidden" name="count" value="{{ $n }}">
              <button type="submit" class="sb-dot {{ $data['players'] === $n ? 'on' : '' }}"
                      data-side="{{ $side }}" data-count="{{ $n }}"
                      title="{{ $n }} left on the mat">{{ $n }}</button>
            </form>
          @endfor
        </div>
      </div>
    @endforeach
  </div>
</div>

{{-- ── Game part ────────────────────────────────────────── --}}
<div class="sb-panel">
  <form action="{{ route('admin.matches.gamepart',$match) }}" method="POST" class="sb-part" data-live>
    @csrf @method('PUT')
    <label for="sbPart">Game Part:</label>
    <input type="text" name="period" id="sbPart" value="{{ $match->period }}" maxlength="30" placeholder="e.g. 1st Inning">
    <button type="submit" class="btn btn-primary btn-sm">Save</button>
    <span class="field-hint" style="margin:0">Shown on the timer, main screen and live strip.</span>
  </form>
</div>

{{-- ── Match-wide controls ──────────────────────────────── --}}
<div class="sb-footer">
  <form action="{{ route('admin.matches.undo',$match) }}" method="POST" data-live>
    @csrf @method('PUT')
    <button type="submit" class="btn btn-ghost" id="sbUndo" @disabled($events->isEmpty())>
      <i class="fa-solid fa-rotate-left"></i> Undo
    </button>
  </form>

  <form action="{{ route('admin.matches.reset',$match) }}" method="POST" data-live
        data-confirm="Reset scores, squads and clocks, and clear the match feed? This cannot be undone.">
    @csrf @method('PUT')
    <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash-can"></i> Reset Match</button>
  </form>

  <a href="{{ route('admin.matches.scoreboard',$match) }}" class="btn btn-danger"><i class="fa-solid fa-rotate"></i> Refresh Page</a>

  <form action="{{ route('admin.matches.swap',$match) }}" method="POST" data-live>
    @csrf @method('PUT')
    <button type="submit" class="btn btn-danger" title="Change ends — flips which side of the boards each team appears on">
      <i class="fa-solid fa-right-left"></i> Swap Courts
    </button>
  </form>

  <span class="sb-courts">Courts: <strong id="sbCourts">{{ $match->courts_swapped ? 'swapped' : 'normal' }}</strong></span>
</div>

<div class="sb-twocol">
  <div class="panel-card">
    <h4>Match Status</h4>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      @foreach(\App\Models\TeamMatch::STATUSES as $s)
        <form action="{{ route('admin.matches.status',$match) }}" method="POST" data-live>
          @csrf @method('PUT')
          <input type="hidden" name="status" value="{{ $s }}">
          <button type="submit" data-status="{{ $s }}"
                  class="btn {{ $match->status === $s ? 'btn-primary' : 'btn-ghost' }} btn-sm">{{ ucfirst($s) }}</button>
        </form>
      @endforeach
    </div>
  </div>

  <div class="panel-card">
    <h4>Match Feed <span style="text-transform:none;letter-spacing:0;font-weight:400">(newest first — Undo rewinds the top entry)</span></h4>
    <div id="sbFeed">
      @forelse($events as $event)
        <div class="sb-feed-row">
          <span class="sb-feed-dot sb-t{{ $event->team ?? 0 }}"></span>
          <span class="sb-feed-txt">{{ $event->description }}</span>
          <span class="sb-feed-meta">{{ $event->created_at?->format('H:i') }}</span>
        </div>
      @empty
        <div class="field-hint" style="margin:0">No actions recorded yet.</div>
      @endforelse
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
  :root{--t1:#F5A524;--t2:#2E7DF6}

  .sb-title{text-align:center;font-size:17px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;margin-bottom:14px}
  .sb-vs{color:var(--ink3);font-weight:600}
  .sb-sublinks{display:flex;gap:8px;flex-wrap:wrap;align-items:center;justify-content:center;margin-bottom:20px}
  .sb-clockchip{display:inline-flex;align-items:center;gap:7px;background:var(--card);border:1px solid var(--border);
                border-radius:20px;padding:5px 13px;font-size:12px;color:var(--ink3);white-space:nowrap}
  .sb-clockchip strong{color:var(--ink);font-size:14px;font-variant-numeric:tabular-nums}

  .sb-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(330px,1fr));gap:18px;margin-bottom:18px}
  .sb-grid-tight{margin-bottom:0;gap:14px}

  .sb-card{background:var(--card);border:2px solid var(--border);border-radius:12px;padding:18px;text-align:center}
  .sb-card.sb-t1{border-color:var(--t1)}
  .sb-card.sb-t2{border-color:var(--t2)}

  .sb-team{font-size:21px;font-weight:800;margin-bottom:6px;overflow-wrap:break-word}
  .sb-team-sm{font-size:14px;font-weight:700;margin-bottom:12px}
  .sb-t1 .sb-team{color:var(--t1)}
  .sb-t2 .sb-team{color:var(--t2)}

  .sb-scorerow{display:flex;align-items:center;justify-content:center;gap:14px;margin:4px 0 16px}
  .sb-scorerow form{display:inline}
  .sb-score{font-size:60px;line-height:1;font-weight:800;min-width:86px;font-variant-numeric:tabular-nums;
            transition:transform .18s ease}
  .sb-score.pop{transform:scale(1.18)}
  .sb-step{border:none;border-radius:7px;padding:7px 20px;font-size:17px;font-weight:700;color:#0B0F14;cursor:pointer;
           font-family:inherit;line-height:1.2;transition:filter .12s}
  .sb-step:hover{filter:brightness(1.12)}
  .sb-minus{background:var(--t1)}
  .sb-plus{background:#16A34A;color:#fff}

  .sb-presets{display:flex;gap:7px;flex-wrap:wrap;justify-content:center;margin-bottom:14px}
  .sb-presets form{display:inline}
  .sb-chip{background:transparent;border:1px solid var(--border);border-radius:6px;padding:6px 11px;font-size:10.5px;
           font-weight:700;letter-spacing:.6px;text-transform:uppercase;cursor:pointer;font-family:inherit;
           color:var(--t1);transition:background .12s,border-color .12s}
  .sb-chip:hover{border-color:currentColor;background:rgba(255,255,255,.04)}
  .sb-chip-pts{opacity:.65;font-weight:600}
  .sb-chip-do_or_die{color:#F87171}
  .sb-chip-super_raid{color:#4ADE80}
  .sb-chip-all_out{color:#C084FC}

  .sb-adjust{display:flex;align-items:center;justify-content:center;gap:10px;flex-wrap:wrap}
  .sb-adjust-grp{display:flex;align-items:center;gap:6px}
  .sb-amount{width:52px;background:var(--panel);border:1px solid var(--border);border-radius:6px;padding:6px 8px;
             font-size:13px;color:var(--ink);text-align:center;outline:none;font-family:inherit}
  .sb-amount:focus{border-color:var(--brand)}
  .sb-adjust-label{font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--ink3);
                   background:var(--panel);border:1px solid var(--border);border-radius:5px;padding:5px 9px}

  .sb-panel{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:18px}
  .sb-panel-hd{text-align:center;font-size:13px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;margin-bottom:14px}
  .sb-subcard{border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center}
  .sb-subcard.sb-t1{border-color:rgba(245,165,36,.4)}
  .sb-subcard.sb-t2{border-color:rgba(46,125,246,.4)}

  .sb-dots{display:flex;gap:8px;justify-content:center;flex-wrap:wrap}
  .sb-dots form{display:inline}
  .sb-dot{width:30px;height:30px;border-radius:50%;background:transparent;border:1px solid var(--border);
          color:var(--ink3);font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .12s}
  .sb-dot:hover{border-color:var(--ink2);color:var(--ink)}
  .sb-t1 .sb-dot.on{background:var(--t1);border-color:var(--t1);color:#0B0F14;font-weight:700}
  .sb-t2 .sb-dot.on{background:var(--t2);border-color:var(--t2);color:#fff;font-weight:700}

  .sb-part{display:flex;align-items:center;justify-content:center;gap:10px;flex-wrap:wrap}
  .sb-part label{font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--ink3)}
  .sb-part input{background:var(--panel);border:1px solid var(--border);border-radius:7px;padding:9px 12px;
                 font-size:14px;color:var(--ink);outline:none;min-width:200px;font-family:inherit}
  .sb-part input:focus{border-color:var(--brand)}

  .sb-footer{display:flex;gap:10px;justify-content:center;align-items:center;flex-wrap:wrap;margin-bottom:20px}
  .sb-footer form{display:inline}
  .sb-courts{font-size:11px;color:var(--ink3);text-transform:uppercase;letter-spacing:.8px}
  .sb-courts strong{color:var(--ink2)}

  .sb-twocol{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:18px}
  .sb-feed-row{display:flex;align-items:center;gap:9px;padding:6px 0;border-bottom:1px solid var(--border2);font-size:12.5px}
  .sb-feed-row:last-child{border-bottom:none}
  .sb-feed-dot{width:7px;height:7px;border-radius:50%;background:var(--ink3);flex-shrink:0}
  .sb-feed-dot.sb-t1{background:var(--t1)}
  .sb-feed-dot.sb-t2{background:var(--t2)}
  .sb-feed-txt{flex:1;color:var(--ink2)}
  .sb-feed-meta{color:var(--ink3);font-size:11px;font-variant-numeric:tabular-nums}
</style>
@endpush

@push('scripts')
@include('admin.matches._engine')
<script>
(function () {
  var shown = { 1: {{ $sides[1]['score'] }}, 2: {{ $sides[2]['score'] }} };

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function setScore(side, value) {
    var el = document.getElementById('sbScore' + side);
    el.textContent = value;
    if (shown[side] !== value) {
      shown[side] = value;
      el.classList.add('pop');
      setTimeout(function () { el.classList.remove('pop'); }, 180);
    }
  }

  function paintFeed(feed, canUndo) {
    if (!feed) { return; }
    var host = document.getElementById('sbFeed');
    if (!feed.length) {
      host.innerHTML = '<div class="field-hint" style="margin:0">No actions recorded yet.</div>';
    } else {
      host.innerHTML = feed.map(function (e) {
        return '<div class="sb-feed-row">' +
          '<span class="sb-feed-dot sb-t' + (e.team || 0) + '"></span>' +
          '<span class="sb-feed-txt">' + escapeHtml(e.description) + '</span>' +
          '<span class="sb-feed-meta">' + escapeHtml(e.time) + '</span></div>';
      }).join('');
    }
    if (typeof canUndo === 'boolean') { document.getElementById('sbUndo').disabled = !canUndo; }
  }

  MatchLive.init({
    url: '{{ route('admin.matches.state', $match) }}?feed=1',
    state: @json($match->toScreenPayload()),
    socket: @json($socket),

    /* Optimistic update: show the operator's point the moment they click, so
       the console feels instant regardless of round-trip time. The response (or
       the pushed frame) overwrites this with the authoritative score a beat
       later, so a rejected action corrects itself. */
    onSend: function (form, body) {
        var side = Number(body.get('team'));
        if (!side) { return; }

        var delta = null;
        if (body.has('delta')) {
            delta = Number(body.get('delta'));
        } else if (body.has('direction')) {
            delta = Number(body.get('amount')) * (body.get('direction') === 'minus' ? -1 : 1);
        } else if (body.has('type')) {
            var pts = @json(collect(\App\Models\MatchEvent::PRESETS)->map->points);
            delta = pts[body.get('type')] || 0;
            // An all out scores for the opposing side.
            if (body.get('type') === 'all_out') { side = side === 1 ? 2 : 1; }
        } else if (body.has('count')) {
            var dot = Number(body.get('count'));
            document.querySelectorAll('.sb-dot').forEach(function (b) {
                if (Number(b.dataset.side) === side) {
                    b.classList.toggle('on', Number(b.dataset.count) === dot);
                }
            });
            return;
        }

        if (delta === null || isNaN(delta)) { return; }
        setScore(side, Math.max(0, shown[side] + delta));
    },

    // Runs ~4×/sec off the shared clock, so this chip, the Timer page and the
    // boards all show the same second.
    onPaint: function (t) {
      document.getElementById('sbClock').textContent = t.matchClock;
      document.getElementById('sbRaid').textContent = t.raidSeconds;
      document.getElementById('sbMeta').textContent =
        (t.state.period ? '· ' + t.state.period : '') + (t.matchRunning ? ' · running' : ' · stopped');
    },

    // Runs when fresh state arrives, from a poll or from an action response.
    onState: function (s, extra) {
      if (!s) { return; }
      setScore(1, s.teams[0].score);
      setScore(2, s.teams[1].score);

      document.querySelectorAll('.sb-dot').forEach(function (b) {
        var players = s.teams[b.dataset.side - 1].players;
        b.classList.toggle('on', Number(b.dataset.count) === players);
      });

      document.querySelectorAll('[data-status]').forEach(function (b) {
        var active = b.dataset.status === s.status;
        b.classList.toggle('btn-primary', active);
        b.classList.toggle('btn-ghost', !active);
      });

      document.getElementById('sbCourts').textContent = s.courts_swapped ? 'swapped' : 'normal';

      // The poll carries the feed (?feed=1); an action response carries it too.
      paintFeed(s.feed !== undefined ? s.feed : (extra && extra.feed),
                s.can_undo !== undefined ? s.can_undo : (extra && extra.can_undo));
    }
  });

  MatchLive.bindForms();
})();
</script>
@endpush
