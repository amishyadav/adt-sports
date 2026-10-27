@extends('layouts.admin')
@section('title','Matches')
@section('content')

<div class="page-hd">
  <div><h1>Matches</h1><div class="page-hd-sub">{{ $matches->total() }} {{ Str::plural('match', $matches->total()) }}</div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start">

  {{-- Match list --}}
  <div class="table-wrap">
    <div class="table-hd">
      <h3>All Matches</h3>
      <div class="table-filters">
        <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
          <input type="text" name="search" class="search-input" placeholder="Search team or venue…" value="{{ request('search') }}">
          <select name="status" class="filter-select" onchange="this.form.submit()">
            <option value="">All Status</option>
            @foreach(\App\Models\TeamMatch::STATUSES as $s)
              <option value="{{ $s }}" {{ request('status')==$s ? 'selected':'' }}>{{ ucfirst($s) }}</option>
            @endforeach
          </select>
          <button type="submit" class="btn btn-ghost btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
          @if(request('search') || request('status'))
            <a href="{{ route('admin.matches.index') }}" class="btn btn-ghost btn-sm">Clear</a>
          @endif
        </form>
      </div>
    </div>
    <table>
      <thead><tr><th>Team 1</th><th>Team 2</th><th>Score</th><th>Scheduled</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse($matches as $m)
        <tr>
          <td style="font-weight:500;color:var(--ink)">{{ $m->team1->name ?? '—' }}</td>
          <td style="font-weight:500;color:var(--ink)">{{ $m->team2->name ?? '—' }}</td>
          <td style="font-weight:600;white-space:nowrap">{{ $m->team_1_score }} – {{ $m->team_2_score }}</td>
          <td style="font-size:12px;color:var(--ink3);white-space:nowrap">
            {{ $m->scheduled_at ? $m->scheduled_at->format('d M Y, H:i') : 'Not scheduled' }}
            @if($m->venue)<div>{{ $m->venue }}</div>@endif
          </td>
          <td><span class="badge badge-ms badge-ms-{{ $m->status }}">{{ $m->status_label }}</span></td>
          <td>
            <div class="actions">
              <a href="{{ route('admin.matches.scoreboard',$m) }}" class="btn btn-success btn-sm" title="Score console">
                <i class="fa-solid fa-table-list"></i> Scoreboard
              </a>
              <a href="{{ route('admin.matches.timer',$m) }}" class="btn btn-amber btn-sm" title="Match clock">
                <i class="fa-solid fa-stopwatch"></i> Timer
              </a>
              <a href="{{ route('admin.matches.screen',$m) }}" target="_blank" class="btn btn-danger btn-sm" title="Open the venue display in a new tab">
                <i class="fa-solid fa-tv"></i> Main Screen
              </a>
              <a href="{{ route('admin.matches.live',$m) }}" target="_blank" class="btn btn-ghost btn-sm" title="Open the live score strip in a new tab">
                <i class="fa-solid fa-bolt"></i> Live Score
              </a>
              <button class="btn btn-ghost btn-sm"
                onclick="openMatchEdit({{ $m->id }},{{ $m->team_1 }},{{ $m->team_2 }},'{{ $m->status }}',{{ $m->team_1_score }},{{ $m->team_2_score }},'{{ $m->scheduled_at?->format('Y-m-d\TH:i') }}','{{ addslashes($m->venue ?? '') }}')">
                <i class="fa-solid fa-pen"></i> Edit
              </button>
              <form action="{{ route('admin.matches.destroy',$m) }}" method="POST" style="display:inline"
                    onsubmit="return confirm('Delete this match?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash-can"></i></button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-trophy"></i>No matches yet</div></td></tr>
        @endforelse
      </tbody>
    </table>
    @if($matches->hasPages())
      <div style="padding:14px">{{ $matches->links() }}</div>
    @endif
  </div>

  {{-- Add / Edit form --}}
  <div class="panel-card" id="matchFormCard">
    <h4 id="matchFormTitle">Add Team Match</h4>

    @if($teams->count() < 2)
      <div class="field-hint" style="margin-bottom:14px">
        You need at least two teams before you can create a match.
        <a href="{{ route('admin.teams.index') }}" style="color:var(--brand)">Add teams →</a>
      </div>
    @endif

    <form action="{{ route('admin.matches.store') }}" method="POST" id="matchForm">
      @csrf
      <input type="hidden" name="_method" id="matchMethod" value="POST">

      <div class="field">
        <label>Team 1 *</label>
        <select name="team_1" id="matchTeam1" required>
          <option value="">Select Team</option>
          @foreach($teams as $team)
            <option value="{{ $team->id }}">{{ $team->name }}{{ $team->status ? '' : ' (inactive)' }}</option>
          @endforeach
        </select>
      </div>

      <div class="field">
        <label>Team 2 *</label>
        <select name="team_2" id="matchTeam2" required>
          <option value="">Select Team</option>
          @foreach($teams as $team)
            <option value="{{ $team->id }}">{{ $team->name }}{{ $team->status ? '' : ' (inactive)' }}</option>
          @endforeach
        </select>
        <div class="field-hint">Must be different from Team 1.</div>
      </div>

      <div class="field">
        <label>Status *</label>
        <select name="status" id="matchStatus" required>
          @foreach(\App\Models\TeamMatch::STATUSES as $s)
            <option value="{{ $s }}">{{ ucfirst($s) }}</option>
          @endforeach
        </select>
      </div>

      <div class="field">
        <label>Score</label>
        <div style="display:flex;align-items:center;gap:8px">
          <input type="number" name="team_1_score" id="matchScore1" min="0" max="9999" value="0">
          <span style="color:var(--ink3)">–</span>
          <input type="number" name="team_2_score" id="matchScore2" min="0" max="9999" value="0">
        </div>
        <div class="field-hint">Team 1 score on the left.</div>
      </div>

      <div class="field">
        <label>Scheduled At</label>
        <input type="datetime-local" name="scheduled_at" id="matchScheduledAt">
      </div>

      <div class="field" style="margin-bottom:16px">
        <label>Venue</label>
        <input type="text" name="venue" id="matchVenue" maxlength="150" placeholder="e.g. Sawai Mansingh Stadium">
      </div>

      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary" id="matchSubmitBtn" @disabled($teams->count() < 2)>+ Add Match</button>
        <button type="button" class="btn btn-ghost" onclick="resetMatchForm()">Reset</button>
      </div>
    </form>
  </div>

</div>
@endsection

@push('styles')
<style>
  .badge-ms::before{content:'';width:6px;height:6px;border-radius:50%;background:currentColor;flex-shrink:0}
  .badge-ms-pending{background:rgba(217,119,6,.15);color:#FCD34D}
  .badge-ms-live{background:rgba(220,38,38,.15);color:#FCA5A5}
  .badge-ms-completed{background:rgba(22,163,74,.15);color:#86EFAC}
  .badge-ms-cancelled{background:rgba(148,163,184,.15);color:#CBD5E1}
</style>
@endpush

@push('scripts')
<script>
function openMatchEdit(id, team1, team2, status, score1, score2, scheduledAt, venue) {
  document.getElementById('matchFormTitle').textContent = 'Edit Team Match';
  document.getElementById('matchForm').action = '/admin/matches/' + id;
  document.getElementById('matchMethod').value = 'PUT';
  document.getElementById('matchTeam1').value = team1;
  document.getElementById('matchTeam2').value = team2;
  document.getElementById('matchStatus').value = status;
  document.getElementById('matchScore1').value = score1;
  document.getElementById('matchScore2').value = score2;
  document.getElementById('matchScheduledAt').value = scheduledAt;
  document.getElementById('matchVenue').value = venue;
  document.getElementById('matchSubmitBtn').textContent = '✓ Update Match';
  document.getElementById('matchSubmitBtn').disabled = false;
  document.getElementById('matchFormCard').scrollIntoView({behavior:'smooth'});
}

function resetMatchForm() {
  document.getElementById('matchFormTitle').textContent = 'Add Team Match';
  document.getElementById('matchForm').action = '{{ route("admin.matches.store") }}';
  document.getElementById('matchMethod').value = 'POST';
  document.getElementById('matchTeam1').value = '';
  document.getElementById('matchTeam2').value = '';
  document.getElementById('matchStatus').value = 'pending';
  document.getElementById('matchScore1').value = 0;
  document.getElementById('matchScore2').value = 0;
  document.getElementById('matchScheduledAt').value = '';
  document.getElementById('matchVenue').value = '';
  document.getElementById('matchSubmitBtn').textContent = '+ Add Match';
}
</script>
@endpush
