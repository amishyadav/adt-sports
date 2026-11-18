@extends('layouts.admin')
@section('title',$tournament->name)
@section('content')

<div class="page-hd">
  <div>
    <h1>{{ $tournament->name }}</h1>
    <div class="page-hd-sub">
      <span class="badge {{ $tournament->status_badge }}">{{ $tournament->status_label }}</span>
      @if($tournament->season) · {{ $tournament->season }}@endif
      @if($tournament->city) · {{ $tournament->city }}@endif
      · {{ $tournament->players_per_side }}-a-side
      · {{ intdiv($tournament->half_duration_seconds, 60) }} min halves
      · {{ $tournament->raid_duration_seconds }}s raids
    </div>
  </div>
  <div class="ktms-btn-row">
    <a href="{{ route('admin.ktms.reports.tournament', $tournament) }}" class="btn btn-ghost">
      <i class="fa-solid fa-chart-column"></i> Report
    </a>
    <a href="{{ route('admin.ktms.tournaments.edit', $tournament) }}" class="btn btn-ghost">
      <i class="fa-solid fa-pen"></i> Edit
    </a>
    <a href="{{ route('admin.ktms.teams.create', $tournament) }}" class="btn btn-ghost">
      <i class="fa-solid fa-shield-halved"></i> Add Squad
    </a>
    @if($tournament->teams->count() >= 2)
      <a href="{{ route('admin.ktms.matches.create', $tournament) }}" class="btn btn-primary">
        <i class="fa-solid fa-plus"></i> New Match
      </a>
    @endif
  </div>
</div>

@if($tournament->description)
  <div class="panel-card" style="margin-bottom:16px">
    <p style="font-size:13px;color:var(--ink2);line-height:1.6">{{ $tournament->description }}</p>
  </div>
@endif

<div class="split-grid">
  <div>
    {{-- Fixtures --}}
    <div class="table-wrap" style="margin-bottom:16px">
      <div class="table-hd">
        <h3>Matches</h3>
        <span style="font-size:12px;color:var(--ink3)">{{ $matches->count() }} total</span>
      </div>
      <div style="padding:14px">
        @if($matches->isEmpty())
          <div class="empty-state">
            <i class="fa-solid fa-table-tennis-paddle-ball"></i>
            @if($tournament->teams->count() < 2)
              Add at least two squads before creating a match.
            @else
              No matches yet.
              <div style="margin-top:14px">
                <a href="{{ route('admin.ktms.matches.create', $tournament) }}" class="btn btn-primary btn-sm">Create the first match</a>
              </div>
            @endif
          </div>
        @else
          <div class="ktms-fixtures">
            @foreach($matches as $match)
              @include('admin.ktms.partials.fixture', ['match' => $match])
            @endforeach
          </div>
        @endif
      </div>
    </div>

    {{-- Squads --}}
    <div class="table-wrap">
      <div class="table-hd">
        <h3>Squads</h3>
        <a href="{{ route('admin.ktms.teams.create', $tournament) }}" class="btn btn-ghost btn-sm">
          <i class="fa-solid fa-plus"></i> Add
        </a>
      </div>
      <table>
        <thead><tr><th>Squad</th><th>Short</th><th>Coach</th><th>City</th><th>Players</th><th>Actions</th></tr></thead>
        <tbody>
          @forelse($tournament->teams as $team)
          <tr>
            <td>
              <span style="display:inline-flex;align-items:center;gap:10px">
                @include('admin.ktms.partials.crest', ['team' => $team])
                <a href="{{ route('admin.ktms.teams.show', $team) }}" style="font-weight:500;color:var(--ink)">{{ $team->name }}</a>
              </span>
            </td>
            <td style="font-size:12px;color:var(--ink3)">{{ $team->short_name ?: '—' }}</td>
            <td style="font-size:12px;color:var(--ink3)">{{ $team->coach_name ?: '—' }}</td>
            <td style="font-size:12px;color:var(--ink3)">{{ $team->city ?: '—' }}</td>
            <td style="font-weight:600">{{ $team->players_count }}</td>
            <td>
              <div class="actions">
                <a href="{{ route('admin.ktms.teams.show', $team) }}" class="btn btn-ghost btn-sm">
                  <i class="fa-solid fa-eye"></i> Squad
                </a>
                <a href="{{ route('admin.ktms.players.create', $team) }}" class="btn btn-ghost btn-sm" title="Add player">
                  <i class="fa-solid fa-user-plus"></i>
                </a>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6">
              <div class="empty-state">
                <i class="fa-solid fa-shield-halved"></i>No squads yet
                <div style="margin-top:14px">
                  <a href="{{ route('admin.ktms.teams.create', $tournament) }}" class="btn btn-primary btn-sm">Add the first squad</a>
                </div>
              </div>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div>
    <div class="panel-card">
      <h4>Leading Scorers</h4>
      @if($leaders->isEmpty())
        <div class="field-hint">Nothing recorded yet. Totals appear once matches are scored.</div>
      @else
        @foreach($leaders as $stat)
          <div style="display:flex;align-items:center;gap:9px;padding:8px 0;border-bottom:1px solid var(--border2)">
            <span class="ktms-pick-no">{{ $loop->iteration }}</span>
            <span style="flex:1;min-width:0">
              <a href="{{ route('admin.ktms.players.show', $stat->player_id) }}"
                 style="display:block;font-size:12.5px;font-weight:500;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                {{ $stat->player?->display_name }}
              </a>
              <span style="font-size:11px;color:var(--ink3)">
                {{ $stat->player?->team?->name }} · {{ $stat->raid_points }} raid / {{ $stat->defense_points }} def
              </span>
            </span>
            <span style="font-weight:700;font-size:15px">{{ $stat->total_points }}</span>
          </div>
        @endforeach
        <a href="{{ route('admin.ktms.reports.tournament', $tournament) }}" class="btn btn-ghost btn-sm" style="width:100%;justify-content:center;margin-top:12px">
          Full report
        </a>
      @endif
    </div>

    <div class="panel-card">
      <h4>Running a Match</h4>
      <p style="font-size:12.5px;color:var(--ink2);line-height:1.6;margin-bottom:10px">
        Each match has a scoring console for recording raids, tackles, the court
        count and the match status. Every action is saved on the server and
        replies with a fresh snapshot of the whole match.
      </p>
      <p style="font-size:12.5px;color:var(--ink3);line-height:1.6">
        Open a match to get to it.
      </p>
    </div>
  </div>
</div>
@endsection
