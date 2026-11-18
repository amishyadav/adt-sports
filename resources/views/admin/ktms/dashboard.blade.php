@extends('layouts.admin')
@section('title','KTMS Dashboard')
@section('content')

<div class="page-hd">
  <div>
    <h1>KTMS</h1>
    <div class="page-hd-sub">Kabaddi tournament management — squads, fixtures, live scoring and player statistics</div>
  </div>
  <div>
    <a href="{{ route('admin.ktms.tournaments.create') }}" class="btn btn-primary">
      <i class="fa-solid fa-plus"></i> New Tournament
    </a>
  </div>
</div>

<div class="ktms-tiles">
  @foreach([
    ['Tournaments', $counts['tournaments'], 'fa-trophy',  route('admin.ktms.tournaments.index')],
    ['Squads',      $counts['teams'],       'fa-shield-halved', route('admin.ktms.tournaments.index')],
    ['Players',     $counts['players'],     'fa-users',   route('admin.ktms.reports.index')],
    ['Matches',     $counts['matches'],     'fa-table-tennis-paddle-ball', route('admin.ktms.matches.index')],
  ] as [$label, $value, $icon, $href])
    <a href="{{ $href }}" class="ktms-tile">
      <span class="ktms-tile-icon"><i class="fa-solid {{ $icon }}"></i></span>
      <span>
        <span class="ktms-tile-value">{{ number_format($value) }}</span>
        <span class="ktms-tile-label">{{ $label }}</span>
      </span>
    </a>
  @endforeach
</div>

@if($liveMatches->isNotEmpty())
  <div class="table-wrap" style="margin-bottom:20px">
    <div class="table-hd">
      <h3><i class="fa-solid fa-circle" style="font-size:8px;color:#FCA5A5"></i> In Progress</h3>
    </div>
    <div style="padding:14px">
      <div class="ktms-fixtures">
        @foreach($liveMatches as $match)
          @include('admin.ktms.partials.fixture', ['match' => $match])
        @endforeach
      </div>
      <div class="ktms-btn-row" style="margin-top:12px">
        @foreach($liveMatches as $match)
          <a href="{{ route('admin.ktms.scoring.console', $match) }}" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-gamepad"></i> Score {{ $match->homeTeam?->initials }}–{{ $match->awayTeam?->initials }}
          </a>
        @endforeach
      </div>
    </div>
  </div>
@endif

<div class="split-grid">
  <div>
    <div class="table-wrap" style="margin-bottom:16px">
      <div class="table-hd">
        <h3>Coming Up</h3>
        <a href="{{ route('admin.ktms.matches.index') }}" class="btn btn-ghost btn-sm">All matches</a>
      </div>
      <div style="padding:14px">
        @if($upcomingMatches->isEmpty())
          <div class="empty-state"><i class="fa-solid fa-calendar"></i>Nothing scheduled</div>
        @else
          <div class="ktms-fixtures">
            @foreach($upcomingMatches as $match)
              @include('admin.ktms.partials.fixture', ['match' => $match])
            @endforeach
          </div>
        @endif
      </div>
    </div>

    <div class="table-wrap">
      <div class="table-hd"><h3>Recent Results</h3></div>
      <div style="padding:14px">
        @if($recentResults->isEmpty())
          <div class="empty-state"><i class="fa-solid fa-flag-checkered"></i>No finished matches yet</div>
        @else
          <div class="ktms-fixtures">
            @foreach($recentResults as $match)
              @include('admin.ktms.partials.fixture', ['match' => $match])
            @endforeach
          </div>
        @endif
      </div>
    </div>
  </div>

  <div>
    <div class="panel-card">
      <h4>Tournaments</h4>
      @if($tournaments->isEmpty())
        <div class="field-hint">
          None yet.
          <a href="{{ route('admin.ktms.tournaments.create') }}" style="color:var(--brand)">Create the first one</a>.
        </div>
      @else
        @foreach($tournaments as $t)
          <a href="{{ route('admin.ktms.tournaments.show', $t) }}"
             style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border2)">
            <span style="flex:1;min-width:0">
              <span style="display:block;font-size:13px;font-weight:500;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $t->name }}</span>
              <span style="font-size:11px;color:var(--ink3)">
                {{ $t->teams_count }} {{ Str::plural('squad', $t->teams_count) }} ·
                {{ $t->matches_count }} {{ Str::plural('match', $t->matches_count) }}
              </span>
            </span>
            <span class="badge {{ $t->status_badge }}">{{ $t->status_label }}</span>
          </a>
        @endforeach
      @endif
    </div>

    <div class="panel-card">
      <h4>Top Raiders</h4>
      @if($topRaiders->isEmpty())
        <div class="field-hint">No raids recorded yet.</div>
      @else
        @foreach($topRaiders as $stat)
          <div style="display:flex;align-items:center;gap:9px;padding:8px 0;border-bottom:1px solid var(--border2)">
            <span class="ktms-pick-no">{{ $loop->iteration }}</span>
            <span style="flex:1;min-width:0">
              <a href="{{ route('admin.ktms.players.show', $stat->player_id) }}"
                 style="display:block;font-size:12.5px;font-weight:500;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                {{ $stat->player?->display_name }}
              </a>
              <span style="font-size:11px;color:var(--ink3)">{{ $stat->player?->team?->name }}</span>
            </span>
            <span style="font-weight:700;font-size:14px">{{ $stat->raid_points }}</span>
          </div>
        @endforeach
      @endif
    </div>

    <div class="panel-card">
      <h4>Top Defenders</h4>
      @if($topDefenders->isEmpty())
        <div class="field-hint">No defensive actions recorded yet.</div>
      @else
        @foreach($topDefenders as $stat)
          <div style="display:flex;align-items:center;gap:9px;padding:8px 0;border-bottom:1px solid var(--border2)">
            <span class="ktms-pick-no">{{ $loop->iteration }}</span>
            <span style="flex:1;min-width:0">
              <a href="{{ route('admin.ktms.players.show', $stat->player_id) }}"
                 style="display:block;font-size:12.5px;font-weight:500;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                {{ $stat->player?->display_name }}
              </a>
              <span style="font-size:11px;color:var(--ink3)">{{ $stat->player?->team?->name }}</span>
            </span>
            <span style="font-weight:700;font-size:14px">{{ $stat->defense_points }}</span>
          </div>
        @endforeach
      @endif
    </div>
  </div>
</div>
@endsection
