@extends('layouts.admin')
@section('title','Tournaments')
@section('content')

<div class="page-hd">
  <div>
    <h1>Tournaments</h1>
    <div class="page-hd-sub">{{ $tournaments->total() }} {{ Str::plural('tournament', $tournaments->total()) }}</div>
  </div>
  <div>
    <a href="{{ route('admin.ktms.tournaments.create') }}" class="btn btn-primary">
      <i class="fa-solid fa-plus"></i> New Tournament
    </a>
  </div>
</div>

<div class="table-wrap">
  <div class="table-hd">
    <h3>All Tournaments</h3>
    <div class="table-filters">
      <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <input type="text" name="search" class="search-input" placeholder="Name or city…" value="{{ request('search') }}">
        <select name="status" class="filter-select" onchange="this.form.submit()">
          <option value="">All Status</option>
          @foreach(\App\Models\Ktms\Tournament::STATUSES as $value => $label)
            <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
        <button type="submit" class="btn btn-ghost btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
        @if(request('search') || request('status'))
          <a href="{{ route('admin.ktms.tournaments.index') }}" class="btn btn-ghost btn-sm">Clear</a>
        @endif
      </form>
    </div>
  </div>

  <table>
    <thead>
      <tr><th>Tournament</th><th>Season</th><th>Dates</th><th>Squads</th><th>Matches</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
      @forelse($tournaments as $t)
      <tr>
        <td>
          <a href="{{ route('admin.ktms.tournaments.show', $t) }}" style="font-weight:500;color:var(--ink)">{{ $t->name }}</a>
          @if($t->city || $t->venue)
            <div style="font-size:11px;color:var(--ink3)">{{ collect([$t->venue, $t->city])->filter()->implode(', ') }}</div>
          @endif
        </td>
        <td style="font-size:12px;color:var(--ink3)">{{ $t->season ?: '—' }}</td>
        <td style="font-size:12px;color:var(--ink3)">
          @if($t->start_date)
            {{ $t->start_date->format('d M Y') }}
            @if($t->end_date) – {{ $t->end_date->format('d M Y') }}@endif
          @else
            —
          @endif
        </td>
        <td style="font-weight:600">{{ $t->teams_count }}</td>
        <td style="font-weight:600">{{ $t->matches_count }}</td>
        <td><span class="badge {{ $t->status_badge }}">{{ $t->status_label }}</span></td>
        <td>
          <div class="actions">
            <a href="{{ route('admin.ktms.tournaments.show', $t) }}" class="btn btn-ghost btn-sm">
              <i class="fa-solid fa-eye"></i> Open
            </a>
            <a href="{{ route('admin.ktms.reports.tournament', $t) }}" class="btn btn-ghost btn-sm" title="Player report">
              <i class="fa-solid fa-chart-column"></i>
            </a>
          </div>
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="7">
          <div class="empty-state">
            <i class="fa-solid fa-trophy"></i>
            @if(request('search') || request('status'))
              No tournament matches those filters.
            @else
              No tournaments yet — create the first one to get started.
            @endif
          </div>
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>

  @if($tournaments->hasPages())
    <div style="padding:14px">{{ $tournaments->links() }}</div>
  @endif
</div>
@endsection
