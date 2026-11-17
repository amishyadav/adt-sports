@extends('layouts.admin')
@section('title',$team->name)
@section('content')

<div class="page-hd">
  <div style="display:flex;align-items:center;gap:14px">
    @include('admin.ktms.partials.crest', ['team' => $team, 'size' => 'lg'])
    <div>
      <h1>{{ $team->name }}</h1>
      <div class="page-hd-sub">
        <a href="{{ route('admin.ktms.tournaments.show', $team->tournament) }}" style="color:var(--brand)">{{ $team->tournament->name }}</a>
        @if($team->short_name) · {{ $team->short_name }}@endif
        @if($team->coach_name) · Coach {{ $team->coach_name }}@endif
        @if($team->city) · {{ $team->city }}@endif
      </div>
    </div>
  </div>
  <div class="ktms-btn-row">
    <a href="{{ route('admin.ktms.teams.edit', $team) }}" class="btn btn-ghost">
      <i class="fa-solid fa-pen"></i> Edit
    </a>
    <a href="{{ route('admin.ktms.players.create', $team) }}" class="btn btn-primary">
      <i class="fa-solid fa-user-plus"></i> Add Player
    </a>
  </div>
</div>

<div class="table-wrap" style="margin-bottom:16px">
  <div class="table-hd">
    <h3>Squad</h3>
    <span style="font-size:12px;color:var(--ink3)">
      {{ $team->players->count() }} {{ Str::plural('player', $team->players->count()) }}
    </span>
  </div>
  <table>
    <thead>
      <tr>
        <th>Player</th><th>Role</th><th>Position</th><th>Matches</th>
        <th>Raids</th><th>Raid pts</th><th>Defences</th><th>Def pts</th><th>Total</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($team->players as $player)
        @php $s = $statistics[$player->id] ?? null; @endphp
        <tr>
          <td>
            <a href="{{ route('admin.ktms.players.show', $player) }}" style="font-weight:500;color:var(--ink)">
              <span class="ktms-pick-no">{{ $player->jersey_number }}</span>
              {{ $player->name }}
            </a>
            @if($player->is_captain)
              <span class="badge badge-ktms-soon" style="margin-left:4px">Captain</span>
            @endif
            @unless($player->is_active)
              <span class="badge badge-ktms-done" style="margin-left:4px">Inactive</span>
            @endunless
          </td>
          <td style="font-size:12px;color:var(--ink3)">{{ $player->role_label }}</td>
          <td style="font-size:12px;color:var(--ink3)">{{ $player->position_label ?: '—' }}</td>
          <td>{{ $s?->matches_played ?? 0 }}</td>
          <td>{{ $s?->total_raids ?? 0 }}</td>
          <td style="font-weight:600">{{ $s?->raid_points ?? 0 }}</td>
          <td>{{ $s?->total_defenses ?? 0 }}</td>
          <td style="font-weight:600">{{ $s?->defense_points ?? 0 }}</td>
          <td style="font-weight:700">{{ $s?->total_points ?? 0 }}</td>
          <td>
            <div class="actions">
              <a href="{{ route('admin.ktms.players.edit', $player) }}" class="btn btn-ghost btn-sm">
                <i class="fa-solid fa-pen"></i>
              </a>
            </div>
          </td>
        </tr>
      @empty
      <tr>
        <td colspan="10">
          <div class="empty-state">
            <i class="fa-solid fa-users"></i>No players yet
            <div style="margin-top:14px">
              <a href="{{ route('admin.ktms.players.create', $team) }}" class="btn btn-primary btn-sm">Add the first player</a>
            </div>
          </div>
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="table-wrap">
  <div class="table-hd"><h3>Matches</h3></div>
  <div style="padding:14px">
    @if($matches->isEmpty())
      <div class="empty-state"><i class="fa-solid fa-table-tennis-paddle-ball"></i>This squad has no fixtures yet</div>
    @else
      <div class="ktms-fixtures">
        @foreach($matches as $match)
          @include('admin.ktms.partials.fixture', ['match' => $match])
        @endforeach
      </div>
    @endif
  </div>
</div>
@endsection
