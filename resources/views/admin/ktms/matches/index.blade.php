@extends('layouts.admin')
@section('title','KTMS Matches')
@section('content')

<div class="page-hd">
  <div>
    <h1>Matches</h1>
    <div class="page-hd-sub">{{ $matches->total() }} {{ Str::plural('match', $matches->total()) }} across all tournaments</div>
  </div>
  <div>
    <a href="{{ route('admin.ktms.tournaments.index') }}" class="btn btn-ghost">
      <i class="fa-solid fa-trophy"></i> Tournaments
    </a>
  </div>
</div>

<div class="table-wrap">
  <div class="table-hd">
    <h3>All Matches</h3>
    <div class="table-filters">
      <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <select name="tournament" class="filter-select" onchange="this.form.submit()">
          <option value="">All Tournaments</option>
          @foreach($tournaments as $t)
            <option value="{{ $t->id }}" {{ (int) request('tournament') === $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
          @endforeach
        </select>
        <select name="status" class="filter-select" onchange="this.form.submit()">
          <option value="">All Status</option>
          @foreach(\App\Models\Ktms\GameMatch::STATUSES as $value => $label)
            <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
        @if(request('tournament') || request('status'))
          <a href="{{ route('admin.ktms.matches.index') }}" class="btn btn-ghost btn-sm">Clear</a>
        @endif
      </form>
    </div>
  </div>

  <table>
    <thead>
      <tr><th>#</th><th>Fixture</th><th>Tournament</th><th>Score</th><th>Status</th><th>Scheduled</th><th>Screens</th></tr>
    </thead>
    <tbody>
      @forelse($matches as $match)
        @php $started = in_array($match->status, \App\Models\Ktms\GameMatch::STARTED_STATUSES, true); @endphp
        <tr>
          <td style="color:var(--ink3)">{{ $match->match_number ?: '—' }}</td>
          <td>
            <a href="{{ route('admin.ktms.matches.show', $match) }}" style="font-weight:500;color:var(--ink)">
              {{ $match->title }}
            </a>
            @if($match->round)
              <div style="font-size:11px;color:var(--ink3)">{{ $match->round }}</div>
            @endif
          </td>
          <td style="font-size:12px;color:var(--ink3)">{{ $match->tournament?->name }}</td>
          <td style="font-weight:700;font-variant-numeric:tabular-nums">
            {{ $started ? $match->home_score . '–' . $match->away_score : '—' }}
          </td>
          <td><span class="badge {{ $match->status_badge }}">{{ $match->status_label }}</span></td>
          <td style="font-size:11px;color:var(--ink3)">
            {{ $match->scheduled_at?->format('d M Y, H:i') ?: '—' }}
          </td>
          <td>
            <div class="actions">
              <a href="{{ route('admin.ktms.scoring.console', $match) }}" class="btn btn-ghost btn-sm" title="Scoring console">
                <i class="fa-solid fa-gamepad"></i>
              </a>
              <a href="{{ route('admin.ktms.timer.console', $match) }}" class="btn btn-ghost btn-sm" title="Timer console">
                <i class="fa-solid fa-stopwatch"></i>
              </a>
              <a href="{{ route('admin.ktms.matches.live', $match) }}" target="_blank" rel="noopener"
                 class="btn btn-ghost btn-sm" title="Broadcast display (opens in a new tab)">
                <i class="fa-solid fa-tv"></i>
              </a>
            </div>
          </td>
        </tr>
      @empty
      <tr>
        <td colspan="7">
          <div class="empty-state">
            <i class="fa-solid fa-table-tennis-paddle-ball"></i>
            @if(request('tournament') || request('status'))
              No match fits those filters.
            @else
              No matches yet. Create one from inside a tournament.
            @endif
          </div>
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>

  @if($matches->hasPages())
    <div style="padding:14px">{{ $matches->links() }}</div>
  @endif
</div>
@endsection
