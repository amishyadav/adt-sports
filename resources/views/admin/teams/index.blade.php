@extends('layouts.admin')
@section('title','Teams')
@section('content')

<div class="page-hd">
  <div><h1>Teams</h1><div class="page-hd-sub">{{ $teams->total() }} {{ Str::plural('team', $teams->total()) }}</div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start">

  {{-- Team list --}}
  <div class="table-wrap">
    <div class="table-hd">
      <h3>All Teams</h3>
      <div class="table-filters">
        <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
          <input type="text" name="search" class="search-input" placeholder="Search teams…" value="{{ request('search') }}">
          <select name="status" class="filter-select" onchange="this.form.submit()">
            <option value="">All Status</option>
            <option value="active"   {{ request('status')=='active'   ? 'selected':'' }}>Active</option>
            <option value="inactive" {{ request('status')=='inactive' ? 'selected':'' }}>Inactive</option>
          </select>
          <button type="submit" class="btn btn-ghost btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
          @if(request('search') || request('status'))
            <a href="{{ route('admin.teams.index') }}" class="btn btn-ghost btn-sm">Clear</a>
          @endif
        </form>
      </div>
    </div>
    <table>
      <thead><tr><th>Team</th><th>Short</th><th>Matches</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse($teams as $t)
        <tr>
          <td style="font-weight:500;color:var(--ink)">
            <span style="display:inline-flex;align-items:center;gap:10px">
              @if($t->logo)
                <img src="{{ $t->logo }}" alt="{{ $t->name }}" class="team-logo" loading="lazy">
              @else
                <span class="team-logo team-logo-ph">{{ $t->initials }}</span>
              @endif
              {{ $t->name }}
            </span>
          </td>
          <td style="font-size:12px;color:var(--ink3)">{{ $t->short_name ?: '—' }}</td>
          <td style="font-weight:600">{{ $t->matches_count }}</td>
          <td>
            <form action="{{ route('admin.teams.toggle',$t) }}" method="POST" style="display:inline">
              @csrf @method('PUT')
              <button type="submit" class="badge {{ $t->status ? 'badge-published' : 'badge-draft' }}"
                      style="border:none;cursor:pointer;font-family:inherit"
                      title="Click to {{ $t->status ? 'deactivate' : 'activate' }}">
                {{ $t->status ? 'Active' : 'Inactive' }}
              </button>
            </form>
          </td>
          <td>
            <div class="actions">
              <button class="btn btn-ghost btn-sm"
                onclick="openTeamEdit({{ $t->id }},'{{ addslashes($t->name) }}','{{ addslashes($t->short_name ?? '') }}','{{ $t->logo }}',{{ $t->status ? 'true':'false' }})">
                <i class="fa-solid fa-pen"></i> Edit
              </button>
              <form action="{{ route('admin.teams.destroy',$t) }}" method="POST" style="display:inline"
                    onsubmit="return confirm('Delete team \'{{ $t->name }}\'?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash-can"></i></button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-shield-halved"></i>No teams yet</div></td></tr>
        @endforelse
      </tbody>
    </table>
    @if($teams->hasPages())
      <div style="padding:14px">{{ $teams->links() }}</div>
    @endif
  </div>

  {{-- Add / Edit form --}}
  <div class="panel-card" id="teamFormCard">
    <h4 id="teamFormTitle">Add Team</h4>
    <form action="{{ route('admin.teams.store') }}" method="POST" id="teamForm" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="_method" id="teamMethod" value="POST">
      <input type="hidden" name="remove_logo" id="teamRemoveLogo" value="0">

      <div class="field">
        <label>Name *</label>
        <input type="text" name="name" id="teamName" required maxlength="100" placeholder="e.g. Jaipur Pink Panthers">
      </div>

      <div class="field">
        <label>Short Name</label>
        <input type="text" name="short_name" id="teamShortName" maxlength="10" placeholder="e.g. JPP">
        <div class="field-hint">Abbreviation used on scoreboards. Optional.</div>
      </div>

      <div class="field">
        <label>Logo</label>
        <div style="display:flex;align-items:center;gap:12px">
          <div class="logo-drop" onclick="document.getElementById('teamLogo').click()" title="Choose an image">
            <img id="teamLogoPreview" src="" alt="" style="display:none">
            <i class="fa-solid fa-shield-halved" id="teamLogoIcon"></i>
            <span class="logo-pencil"><i class="fa-solid fa-pen"></i></span>
          </div>
          <div>
            <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('teamLogo').click()">Choose image</button>
            <button type="button" class="btn btn-ghost btn-sm" id="teamLogoClear" onclick="clearTeamLogo()" style="display:none">Remove</button>
            <div class="field-hint">JPG, PNG or WebP. Max 2 MB.</div>
          </div>
        </div>
        <input type="file" name="logo" id="teamLogo" accept="image/jpeg,image/png,image/webp"
               onchange="previewTeamLogo(this)" style="display:none">
      </div>

      <div class="toggle-row" style="margin-bottom:16px">
        <span class="toggle-label">Status <span style="color:var(--ink3)">(active)</span></span>
        <input type="checkbox" name="status" id="teamStatus" class="toggle-cb" value="1" checked>
      </div>

      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary" id="teamSubmitBtn">+ Add Team</button>
        <button type="button" class="btn btn-ghost" onclick="resetTeamForm()">Reset</button>
      </div>
    </form>
  </div>

</div>
@endsection

@push('styles')
<style>
  .team-logo{width:30px;height:30px;border-radius:50%;object-fit:cover;background:var(--panel);border:1px solid var(--border);flex-shrink:0}
  .team-logo-ph{display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--ink3);letter-spacing:.5px}
  .logo-drop{position:relative;width:76px;height:76px;flex:0 0 auto;display:flex;align-items:center;justify-content:center;background:var(--card);border:1px solid var(--border);border-radius:10px;cursor:pointer;color:var(--ink3);font-size:26px;overflow:hidden;transition:border-color .12s}
  .logo-drop:hover{border-color:var(--brand)}
  .logo-drop img{width:100%;height:100%;object-fit:cover}
  .logo-pencil{position:absolute;top:-1px;right:-1px;width:22px;height:22px;display:flex;align-items:center;justify-content:center;background:var(--panel);border:1px solid var(--border);border-radius:50%;font-size:10px;color:var(--ink2)}
</style>
@endpush

@push('scripts')
<script>
function setTeamLogoPreview(url) {
  var img  = document.getElementById('teamLogoPreview');
  var icon = document.getElementById('teamLogoIcon');
  var clr  = document.getElementById('teamLogoClear');
  if (url) {
    img.src = url; img.style.display = 'block'; icon.style.display = 'none'; clr.style.display = 'inline-flex';
  } else {
    img.src = ''; img.style.display = 'none'; icon.style.display = 'block'; clr.style.display = 'none';
  }
}

function previewTeamLogo(input) {
  var file = input.files && input.files[0];
  if (!file) { return; }
  document.getElementById('teamRemoveLogo').value = '0';
  setTeamLogoPreview(URL.createObjectURL(file));
}

function clearTeamLogo() {
  document.getElementById('teamLogo').value = '';
  // Tells update() to null the stored logo; ignored by store().
  document.getElementById('teamRemoveLogo').value = '1';
  setTeamLogoPreview('');
}

function openTeamEdit(id, name, shortName, logo, status) {
  document.getElementById('teamFormTitle').textContent = 'Edit Team';
  document.getElementById('teamForm').action = '/admin/teams/' + id;
  document.getElementById('teamMethod').value = 'PUT';
  document.getElementById('teamName').value = name;
  document.getElementById('teamShortName').value = shortName;
  document.getElementById('teamStatus').checked = status;
  document.getElementById('teamLogo').value = '';
  document.getElementById('teamRemoveLogo').value = '0';
  setTeamLogoPreview(logo);
  document.getElementById('teamSubmitBtn').textContent = '✓ Update Team';
  document.getElementById('teamFormCard').scrollIntoView({behavior:'smooth'});
}

function resetTeamForm() {
  document.getElementById('teamFormTitle').textContent = 'Add Team';
  document.getElementById('teamForm').action = '{{ route("admin.teams.store") }}';
  document.getElementById('teamMethod').value = 'POST';
  document.getElementById('teamName').value = '';
  document.getElementById('teamShortName').value = '';
  document.getElementById('teamStatus').checked = true;
  document.getElementById('teamLogo').value = '';
  document.getElementById('teamRemoveLogo').value = '0';
  setTeamLogoPreview('');
  document.getElementById('teamSubmitBtn').textContent = '+ Add Team';
}
</script>
@endpush
