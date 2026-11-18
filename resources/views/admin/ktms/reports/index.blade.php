@extends('layouts.admin')
@section('title','Player Reports')
@section('content')

<div class="page-hd">
  <div>
    <h1>Player Reports</h1>
    <div class="page-hd-sub">
      {{ $tournament ? $tournament->name : 'Across all tournaments' }}
      · {{ $statistics->total() }} {{ Str::plural('record', $statistics->total()) }}
    </div>
  </div>
  <div class="ktms-btn-row">
    @if($tournament)
      <a href="{{ route('admin.ktms.reports.index') }}" class="btn btn-ghost">
        <i class="fa-solid fa-globe"></i> All tournaments
      </a>
    @endif
    <a href="{{ route('admin.ktms.reports.export', request()->query()) }}" class="btn btn-primary">
      <i class="fa-solid fa-file-csv"></i> Export CSV
    </a>
  </div>
</div>

{{-- Totals across whatever the filters currently select --}}
<div class="ktms-tiles">
  @foreach([
    ['Total points',   number_format($summary['total_points']),   'fa-star'],
    ['Raids',          number_format($summary['raids']),          'fa-person-running'],
    ['Raid success',   $summary['raid_success_rate'] . '%',       'fa-arrow-trend-up'],
    ['Defences',       number_format($summary['defenses']),       'fa-shield-halved'],
    ['Defence success',$summary['defense_success_rate'] . '%',    'fa-hand-fist'],
  ] as [$label, $value, $icon])
    <div class="ktms-tile">
      <span class="ktms-tile-icon"><i class="fa-solid {{ $icon }}"></i></span>
      <span>
        <span class="ktms-tile-value">{{ $value }}</span>
        <span class="ktms-tile-label">{{ $label }}</span>
      </span>
    </div>
  @endforeach
</div>

<div class="table-wrap">
  <div class="table-hd">
    <h3>Leaderboard</h3>
    <div class="table-filters">
      <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <input type="text" name="search" class="search-input" placeholder="Player name…" value="{{ request('search') }}">

        @if($tournament)
          {{-- The route pins the tournament, so offer a squad filter instead. --}}
          <select name="team" class="filter-select" onchange="this.form.submit()">
            <option value="">All Squads</option>
            @foreach($teams as $team)
              <option value="{{ $team->id }}" {{ (int) request('team') === $team->id ? 'selected' : '' }}>{{ $team->name }}</option>
            @endforeach
          </select>
        @else
          <select name="tournament" class="filter-select" onchange="this.form.submit()">
            <option value="">All Tournaments</option>
            @foreach($tournaments as $t)
              <option value="{{ $t->id }}" {{ (int) request('tournament') === $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
            @endforeach
          </select>
        @endif

        <select name="role" class="filter-select" onchange="this.form.submit()">
          <option value="">All Roles</option>
          @foreach(\App\Models\Ktms\Player::ROLES as $value => $label)
            <option value="{{ $value }}" {{ request('role') === $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>

        <select name="sort" class="filter-select" onchange="this.form.submit()">
          @foreach(\App\Http\Controllers\Ktms\ReportController::SORTABLE as $value => $label)
            <option value="{{ $value }}" {{ $sort === $value ? 'selected' : '' }}>Sort: {{ $label }}</option>
          @endforeach
        </select>

        <label class="ktms-pick" style="padding:6px 10px">
          <input type="checkbox" name="played_only" value="1" onchange="this.form.submit()"
                 {{ request()->boolean('played_only') ? 'checked' : '' }}>
          Played only
        </label>

        <button type="submit" class="btn btn-ghost btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
        @if(request()->hasAny(['search','team','role','sort','played_only','tournament']))
          <a href="{{ $tournament ? route('admin.ktms.reports.tournament', $tournament) : route('admin.ktms.reports.index') }}"
             class="btn btn-ghost btn-sm">Clear</a>
        @endif
      </form>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>#</th><th>Player</th><th>Squad</th>
        @unless($tournament)<th>Tournament</th>@endunless
        <th>M</th><th>Raids</th><th>Succ</th><th>Raid %</th><th>Raid pts</th>
        <th>SR</th><th>DoD</th>
        <th>Def</th><th>Succ</th><th>Def %</th><th>Def pts</th><th>ST</th>
        <th>Total</th><th>PPM</th>
      </tr>
    </thead>
    <tbody>
      @forelse($statistics as $stat)
      <tr>
        <td style="color:var(--ink3)">{{ $statistics->firstItem() + $loop->index }}</td>
        <td>
          <a href="{{ route('admin.ktms.players.show', $stat->player_id) }}" style="font-weight:500;color:var(--ink)">
            {{ $stat->player?->display_name }}
          </a>
          <div style="font-size:11px;color:var(--ink3)">{{ $stat->player?->role_label }}</div>
        </td>
        <td style="font-size:12px;color:var(--ink3)">{{ $stat->player?->team?->name }}</td>
        @unless($tournament)
          <td style="font-size:12px;color:var(--ink3)">{{ $stat->tournament?->name }}</td>
        @endunless
        <td>{{ $stat->matches_played }}</td>
        <td>{{ $stat->total_raids }}</td>
        <td>{{ $stat->successful_raids }}</td>
        <td style="color:var(--ink3)">{{ $stat->raid_success_rate }}%</td>
        <td style="font-weight:600">{{ $stat->raid_points }}</td>
        <td>{{ $stat->super_raids }}</td>
        <td>{{ $stat->do_or_die_conversions }}/{{ $stat->do_or_die_raids }}</td>
        <td>{{ $stat->total_defenses }}</td>
        <td>{{ $stat->successful_defenses }}</td>
        <td style="color:var(--ink3)">{{ $stat->defense_success_rate }}%</td>
        <td style="font-weight:600">{{ $stat->defense_points }}</td>
        <td>{{ $stat->super_tackles }}</td>
        <td style="font-weight:700">{{ $stat->total_points }}</td>
        <td style="color:var(--ink3)">{{ $stat->points_per_match }}</td>
      </tr>
      @empty
      <tr>
        <td colspan="18">
          <div class="empty-state">
            <i class="fa-solid fa-chart-column"></i>
            Nothing to report yet. Figures appear as soon as matches are scored.
          </div>
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>

  @if($statistics->hasPages())
    <div style="padding:14px">{{ $statistics->links() }}</div>
  @endif
</div>

<div class="panel-card" style="margin-top:16px">
  <h4>Reading the columns</h4>
  <p style="font-size:12.5px;color:var(--ink2);line-height:1.6">
    <strong>M</strong> matches played ·
    <strong>SR</strong> super raids (three or more points in one raid) ·
    <strong>DoD</strong> do-or-die raids converted out of attempted ·
    <strong>ST</strong> super tackles ·
    <strong>PPM</strong> points per match.
  </p>
  <p style="font-size:12.5px;color:var(--ink3);line-height:1.6;margin-top:8px">
    Every figure is rebuilt from the stored raid and defensive-action records
    after each scoring action, so an undo or a correction can never leave these
    totals drifting. To rebuild them by hand: <code>php artisan ktms:rebuild-stats</code>
  </p>
</div>
@endsection
