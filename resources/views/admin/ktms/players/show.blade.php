@extends('layouts.admin')
@section('title',$player->name)
@section('content')

<div class="page-hd">
  <div style="display:flex;align-items:center;gap:14px">
    @include('admin.ktms.partials.crest', ['team' => $player->team, 'size' => 'lg'])
    <div>
      <h1>{{ $player->display_name }}</h1>
      <div class="page-hd-sub">
        <a href="{{ route('admin.ktms.teams.show', $player->team) }}" style="color:var(--brand)">{{ $player->team->name }}</a>
        · {{ $player->role_label }}
        @if($player->position_label) · {{ $player->position_label }}@endif
        @if($player->is_captain) · <span class="badge badge-ktms-soon">Captain</span>@endif
        @unless($player->is_active) · <span class="badge badge-ktms-done">Inactive</span>@endunless
      </div>
    </div>
  </div>
  <div class="ktms-btn-row">
    <button type="button" class="btn btn-ghost" onclick="window.print()">
      <i class="fa-solid fa-print"></i> Print
    </button>
    <a href="{{ route('admin.ktms.players.edit', $player) }}" class="btn btn-ghost">
      <i class="fa-solid fa-pen"></i> Edit
    </a>
  </div>
</div>

<div class="ktms-tiles">
  @foreach([
    ['Total points', $statistic->total_points, 'fa-star'],
    ['Raid points',  $statistic->raid_points,  'fa-person-running'],
    ['Defence points', $statistic->defense_points, 'fa-shield-halved'],
    ['Matches', $statistic->matches_played, 'fa-table-tennis-paddle-ball'],
  ] as [$label, $value, $icon])
    <div class="ktms-tile">
      <span class="ktms-tile-icon"><i class="fa-solid {{ $icon }}"></i></span>
      <span>
        <span class="ktms-tile-value">{{ number_format($value) }}</span>
        <span class="ktms-tile-label">{{ $label }}</span>
      </span>
    </div>
  @endforeach
</div>

<div class="half-grid" style="margin-bottom:16px">
  <div class="table-wrap">
    <div class="table-hd"><h3>Raiding</h3></div>
    <table>
      <tbody>
        @foreach([
          ['Raids attempted', $statistic->total_raids],
          ['Successful', $statistic->successful_raids],
          ['Unsuccessful', $statistic->unsuccessful_raids],
          ['Empty', $statistic->empty_raids],
          ['Success rate', $statistic->raid_success_rate . '%'],
          ['Touch points', $statistic->touch_points],
          ['Bonus points', $statistic->bonus_points],
          ['Super raids', $statistic->super_raids],
          ['Do-or-die raids', $statistic->do_or_die_raids],
          ['Do-or-die converted', $statistic->do_or_die_conversions . ' (' . $statistic->do_or_die_conversion_rate . '%)'],
          ['Points per raid', $statistic->raid_points_per_raid],
        ] as [$label, $value])
          <tr>
            <td style="color:var(--ink2)">{{ $label }}</td>
            <td style="text-align:right;font-weight:600">{{ $value }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="table-wrap">
    <div class="table-hd"><h3>Defending</h3></div>
    <table>
      <tbody>
        @foreach([
          ['Defences attempted', $statistic->total_defenses],
          ['Successful', $statistic->successful_defenses],
          ['Failed', $statistic->failed_defenses],
          ['Success rate', $statistic->defense_success_rate . '%'],
          ['Defence points', $statistic->defense_points],
          ['Super tackles', $statistic->super_tackles],
          ['Points per match', $statistic->points_per_match],
        ] as [$label, $value])
          <tr>
            <td style="color:var(--ink2)">{{ $label }}</td>
            <td style="text-align:right;font-weight:600">{{ $value }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

<div class="half-grid">
  <div class="table-wrap">
    <div class="table-hd"><h3>Recent Raids</h3></div>
    <table>
      <thead><tr><th>#</th><th>Match</th><th>Result</th><th>Pts</th></tr></thead>
      <tbody>
        @forelse($raids as $raid)
        <tr>
          <td style="color:var(--ink3)">{{ $raid->raid_number }}</td>
          <td style="font-size:12px">
            <a href="{{ route('admin.ktms.matches.show', $raid->match_id) }}" style="color:var(--ink2)">
              vs {{ $raid->defendingTeam?->short_name ?: $raid->defendingTeam?->name }}
            </a>
          </td>
          <td>
            <span class="badge {{ $raid->result_badge }}">{{ $raid->result_label }}</span>
            @if($raid->is_super_raid)<span class="badge badge-ktms-soon">Super</span>@endif
            @if($raid->is_do_or_die)<span class="badge badge-ktms-half">D-o-D</span>@endif
          </td>
          <td style="font-weight:600">{{ $raid->raid_points }}</td>
        </tr>
        @empty
        <tr><td colspan="4"><div class="empty-state"><i class="fa-solid fa-person-running"></i>No raids recorded</div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="table-wrap">
    <div class="table-hd"><h3>Recent Defences</h3></div>
    <table>
      <thead><tr><th>Match</th><th>Action</th><th>Outcome</th><th>Pts</th></tr></thead>
      <tbody>
        @forelse($defenses as $action)
        <tr>
          <td style="font-size:12px">
            <a href="{{ route('admin.ktms.matches.show', $action->match_id) }}" style="color:var(--ink2)">
              {{ $action->match?->homeTeam?->short_name }}–{{ $action->match?->awayTeam?->short_name }}
            </a>
          </td>
          <td style="font-size:12px;color:var(--ink3)">{{ $action->action_type_label }}</td>
          <td>
            <span class="badge {{ $action->is_successful ? 'badge-ktms-live' : 'badge-ktms-done' }}">
              {{ $action->is_successful ? 'Stopped' : 'Beaten' }}
            </span>
          </td>
          <td style="font-weight:600">{{ $action->points_awarded }}</td>
        </tr>
        @empty
        <tr><td colspan="4"><div class="empty-state"><i class="fa-solid fa-shield-halved"></i>No defensive actions recorded</div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
