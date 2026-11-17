@extends('layouts.admin')
@section('title',$match->title)
@section('content')

<div class="page-hd">
  <div>
    <h1>{{ $match->title }}</h1>
    <div class="page-hd-sub">
      <a href="{{ route('admin.ktms.tournaments.show', $match->tournament) }}" style="color:var(--brand)">{{ $match->tournament->name }}</a>
      @if($match->match_number) · Match {{ $match->match_number }}@endif
      @if($match->round) · {{ $match->round }}@endif
      @if($match->venue) · {{ $match->venue }}@endif
      @if($match->scheduled_at) · {{ $match->scheduled_at->format('d M Y, H:i') }}@endif
    </div>
  </div>
  <div class="ktms-btn-row">
    <a href="{{ route('admin.ktms.matches.edit', $match) }}" class="btn btn-ghost">
      <i class="fa-solid fa-pen"></i> Edit
    </a>
  </div>
</div>

{{-- Scoreboard --}}
<div class="ktms-board" style="margin-bottom:16px">
  <div class="ktms-board-side">
    @include('admin.ktms.partials.crest', ['team' => $match->homeTeam, 'size' => 'lg'])
    <div class="ktms-board-name">{{ $match->homeTeam?->name }}</div>
    <div class="ktms-board-score">{{ $match->home_score }}</div>
    <div class="ktms-pips">
      @for($i = 1; $i <= $match->playersPerSide(); $i++)
        <span class="ktms-pip {{ $i <= $match->home_players_on_court ? 'on' : '' }}"></span>
      @endfor
    </div>
  </div>

  <div class="ktms-board-mid">
    <span class="badge {{ $match->status_badge }}">{{ $match->status_label }}</span>
    <div class="ktms-board-clock">{{ \App\Models\Ktms\GameMatch::formatClock($match->effectiveGameClockRemaining()) }}</div>
    <div class="ktms-board-meta">Half {{ $match->current_half }} · {{ $match->raid_count }} raids</div>
    @if($match->winner_team_id)
      <div class="ktms-board-meta" style="color:#86EFAC">{{ $match->winnerTeam?->name }} won</div>
    @elseif($match->isCompleted())
      <div class="ktms-board-meta">Tied</div>
    @endif
  </div>

  <div class="ktms-board-side">
    @include('admin.ktms.partials.crest', ['team' => $match->awayTeam, 'size' => 'lg'])
    <div class="ktms-board-name">{{ $match->awayTeam?->name }}</div>
    <div class="ktms-board-score">{{ $match->away_score }}</div>
    <div class="ktms-pips">
      @for($i = 1; $i <= $match->playersPerSide(); $i++)
        <span class="ktms-pip {{ $i <= $match->away_players_on_court ? 'on' : '' }}"></span>
      @endfor
    </div>
  </div>
</div>

{{-- The three screens --}}
<div class="panel-card" style="margin-bottom:16px">
  <h4>Match Screens</h4>
  <div class="ktms-btn-row">
    <a href="{{ route('admin.ktms.scoring.console', $match) }}" class="btn btn-primary">
      <i class="fa-solid fa-gamepad"></i> Scoring Console
    </a>
    <a href="{{ route('admin.ktms.timer.console', $match) }}" class="btn btn-ghost">
      <i class="fa-solid fa-stopwatch"></i> Timer Console
    </a>
    <a href="{{ route('admin.ktms.matches.live', $match) }}" target="_blank" rel="noopener" class="btn btn-ghost">
      <i class="fa-solid fa-tv"></i> Broadcast Display
    </a>
  </div>
  <div class="field-hint" style="margin-top:10px">
    All three read one server-side snapshot, so two operators can work side by
    side and the display never disagrees with them.
  </div>
</div>

<div class="split-grid">
  <div>
    {{-- Raids --}}
    <div class="table-wrap" style="margin-bottom:16px">
      <div class="table-hd">
        <h3>Raids</h3>
        <span style="font-size:12px;color:var(--ink3)">{{ $raids->count() }} recorded</span>
      </div>
      <table>
        <thead>
          <tr><th>#</th><th>Raider</th><th>Result</th><th>Touch</th><th>Bonus</th><th>Raid pts</th><th>Def pts</th><th>Flags</th></tr>
        </thead>
        <tbody>
          @forelse($raids as $raid)
          <tr>
            <td style="color:var(--ink3)">{{ $raid->raid_number }}</td>
            <td>
              <a href="{{ route('admin.ktms.players.show', $raid->raider_id) }}" style="color:var(--ink);font-weight:500">
                {{ $raid->raider?->display_name }}
              </a>
              <div style="font-size:11px;color:var(--ink3)">{{ $raid->raidingTeam?->short_name ?: $raid->raidingTeam?->name }}</div>
            </td>
            <td><span class="badge {{ $raid->result_badge }}">{{ $raid->result_label }}</span></td>
            <td>{{ $raid->touch_points }}</td>
            <td>{{ $raid->bonus_points }}</td>
            <td style="font-weight:600">{{ $raid->raid_points }}</td>
            <td style="font-weight:600">{{ $raid->defending_points }}</td>
            <td style="font-size:11px">
              @if($raid->is_super_raid)<span class="badge badge-ktms-soon">Super raid</span>@endif
              @if($raid->is_do_or_die)<span class="badge badge-ktms-half">Do-or-die</span>@endif
              @if($raid->defensiveActions->isNotEmpty())
                <div style="color:var(--ink3);margin-top:3px">
                  {{ $raid->defensiveActions->where('is_successful', true)->map(fn ($a) => $a->defender?->display_name)->filter()->implode(', ') }}
                </div>
              @endif
            </td>
          </tr>
          @empty
          <tr><td colspan="8"><div class="empty-state"><i class="fa-solid fa-person-running"></i>No raids recorded yet</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    {{-- Lineups --}}
    <div class="half-grid">
      @foreach([['home', $match->homeTeam], ['away', $match->awayTeam]] as [$side, $team])
        <div class="table-wrap">
          <div class="table-hd"><h3>{{ $team?->name }}</h3></div>
          <table>
            <tbody>
              @forelse($team?->players ?? [] as $player)
                <tr>
                  <td>
                    <a href="{{ route('admin.ktms.players.show', $player) }}" style="color:var(--ink)">
                      <span class="ktms-pick-no">{{ $player->jersey_number }}</span> {{ $player->name }}
                    </a>
                    @if($player->is_captain)<span class="badge badge-ktms-soon">C</span>@endif
                  </td>
                  <td style="text-align:right;font-size:11px;color:var(--ink3)">{{ $player->role_label }}</td>
                </tr>
              @empty
                <tr><td><div class="empty-state"><i class="fa-solid fa-users"></i>No players</div></td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      @endforeach
    </div>
  </div>

  <div>
    <div class="panel-card">
      <h4>Top Performers</h4>
      @if($topPerformers->isEmpty())
        <div class="field-hint">Nothing scored yet.</div>
      @else
        @foreach($topPerformers as $stat)
          <div style="display:flex;align-items:center;gap:9px;padding:8px 0;border-bottom:1px solid var(--border2)">
            <span class="ktms-pick-no">{{ $loop->iteration }}</span>
            <span style="flex:1;min-width:0">
              <a href="{{ route('admin.ktms.players.show', $stat->player_id) }}"
                 style="display:block;font-size:12.5px;font-weight:500;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                {{ $stat->player?->display_name }}
              </a>
              <span style="font-size:11px;color:var(--ink3)">{{ $stat->player?->team?->name }}</span>
            </span>
            <span style="font-weight:700">{{ $stat->total_points }}</span>
          </div>
        @endforeach
        <div class="field-hint" style="margin-top:10px">Tournament totals, not this match alone.</div>
      @endif
    </div>

    <div class="panel-card">
      <h4>Timeline</h4>
      @if($timeline->isEmpty())
        <div class="field-hint">Nothing has happened yet.</div>
      @else
        <div class="ktms-timeline">
          @foreach($timeline as $event)
            <div class="ktms-event" style="{{ $event->is_undone ? 'opacity:.4;text-decoration:line-through' : '' }}">
              <span class="ktms-event-clock">{{ $event->clock_label }}</span>
              <span class="ktms-event-icon"><i class="fa-solid {{ $event->icon }}"></i></span>
              <span class="ktms-event-text">{{ $event->description }}</span>
              <span class="ktms-event-pts">{{ $event->points ? '+' . $event->points : '' }}</span>
            </div>
          @endforeach
        </div>
      @endif
    </div>
  </div>
</div>
@endsection
