@extends('layouts.admin')
@section('title','Roles')
@section('content')

<div class="page-hd">
  <div>
    <h1>Roles</h1>
    <div class="page-hd-sub">{{ $roles->total() }} {{ Str::plural('role', $roles->total()) }}</div>
  </div>
  <div>
    <a href="{{ route('admin.permissions.index') }}" class="btn btn-ghost">
      <i class="fa-solid fa-key"></i> Manage permissions
    </a>
  </div>
</div>

<div class="split-grid">

  {{-- Role list --}}
  <div class="table-wrap">
    <div class="table-hd">
      <h3>All Roles</h3>
      <div class="table-filters">
        <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
          <input type="text" name="search" class="search-input" placeholder="Search roles…" value="{{ request('search') }}">
          <button type="submit" class="btn btn-ghost btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
          @if(request('search'))
            <a href="{{ route('admin.roles.index') }}" class="btn btn-ghost btn-sm">Clear</a>
          @endif
        </form>
      </div>
    </div>
    <table>
      <thead><tr><th>Role</th><th>Permissions</th><th>Users</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse($roles as $r)
        <tr>
          <td style="font-weight:500;color:var(--ink)">
            <span style="display:inline-flex;align-items:center;gap:9px">
              <span class="role-chip"><i class="fa-solid fa-user-shield"></i></span>
              {{ $r->name }}
            </span>
          </td>
          <td>
            @if($r->permissions_count > 0)
              <a href="{{ route('admin.permissions.index') }}" style="font-weight:600;color:var(--ink);text-decoration:none"
                 title="Change what this role can do">
                {{ $r->permissions_count }}
              </a>
            @else
              <span style="font-size:12px;color:var(--ink3)">none yet</span>
            @endif
          </td>
          <td style="font-weight:600">{{ $r->users_count }}</td>
          <td>
            <div class="actions">
              <button class="btn btn-ghost btn-sm"
                onclick="openRoleEdit({{ $r->id }},'{{ addslashes($r->name) }}')">
                <i class="fa-solid fa-pen"></i> Rename
              </button>
              @if($r->users_count > 0)
                {{-- The controller refuses this too; disabling it here saves a
                     pointless round trip and explains why. --}}
                <button type="button" class="btn btn-ghost btn-sm" disabled
                        style="opacity:.45;cursor:not-allowed"
                        title="{{ $r->users_count }} {{ Str::plural('user', $r->users_count) }} still {{ $r->users_count === 1 ? 'holds' : 'hold' }} this role">
                  <i class="fa-solid fa-trash-can"></i>
                </button>
              @else
                <form action="{{ route('admin.roles.destroy',$r) }}" method="POST" style="display:inline"
                      onsubmit="return confirm('Delete the \'{{ $r->name }}\' role? Any permissions granted to it are released.')">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash-can"></i></button>
                </form>
              @endif
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="4"><div class="empty-state"><i class="fa-solid fa-user-shield"></i>No roles yet — add one on the right</div></td></tr>
        @endforelse
      </tbody>
    </table>
    @if($roles->hasPages())
      <div style="padding:14px">{{ $roles->links() }}</div>
    @endif
  </div>

  {{-- Add / Rename form --}}
  <div>
    <div class="panel-card" id="roleFormCard">
      <h4 id="roleFormTitle">Add Role</h4>
      <form action="{{ route('admin.roles.store') }}" method="POST" id="roleForm">
        @csrf
        <input type="hidden" name="_method" id="roleMethod" value="POST">

        <div class="field" style="margin-bottom:16px">
          <label>Role Name *</label>
          <input type="text" name="name" id="roleName" required maxlength="255"
                 placeholder="e.g. editor" value="{{ old('name') }}" autocomplete="off">
          <div class="field-hint">Lowercase, single word is the usual convention — it is what you write in code.</div>
        </div>

        <div id="roleRenameNote" style="display:none;margin-bottom:14px;padding:10px 12px;border:1px solid rgba(217,119,6,.35);background:rgba(217,119,6,.1);border-radius:7px;font-size:12px;color:#FCD34D;line-height:1.5">
          <i class="fa-solid fa-triangle-exclamation"></i>
          Roles are referred to by name in code. Renaming one does not update those references.
        </div>

        <div style="display:flex;gap:8px">
          <button type="submit" class="btn btn-primary" id="roleSubmitBtn">+ Add Role</button>
          <button type="button" class="btn btn-ghost" onclick="resetRoleForm()">Reset</button>
        </div>
      </form>
    </div>

    <div class="panel-card">
      <h4>How this fits together</h4>
      <p style="font-size:12.5px;color:var(--ink2);line-height:1.6;margin-bottom:10px">
        A role is just a name. What it allows is decided on the
        <a href="{{ route('admin.permissions.index') }}" style="color:var(--brand)">Permissions</a>
        screen, where every role and permission is shown as one grid.
      </p>
      <p style="font-size:12.5px;color:var(--ink3);line-height:1.6">
        Roles are assigned to people on the
        <a href="{{ route('admin.users.index') }}" style="color:var(--brand)">Users</a> screen.
        Signing in to this panel is still decided by the role name, though —
        only <strong>Admin</strong> and <strong>Editor</strong> open it.
      </p>
    </div>
  </div>

</div>
@endsection

@push('styles')
<style>
  .role-chip{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:7px;background:rgba(147,51,234,.15);color:#D8B4FE;font-size:12px;flex-shrink:0}
</style>
@endpush

@push('scripts')
<script>
function openRoleEdit(id, name) {
  document.getElementById('roleFormTitle').textContent = 'Rename Role';
  document.getElementById('roleForm').action = '/admin/roles/' + id;
  document.getElementById('roleMethod').value = 'PUT';
  document.getElementById('roleName').value = name;
  document.getElementById('roleRenameNote').style.display = 'block';
  document.getElementById('roleSubmitBtn').textContent = '✓ Save Name';
  document.getElementById('roleFormCard').scrollIntoView({behavior:'smooth'});
  document.getElementById('roleName').focus();
}

function resetRoleForm() {
  document.getElementById('roleFormTitle').textContent = 'Add Role';
  document.getElementById('roleForm').action = '{{ route("admin.roles.store") }}';
  document.getElementById('roleMethod').value = 'POST';
  document.getElementById('roleName').value = '';
  document.getElementById('roleRenameNote').style.display = 'none';
  document.getElementById('roleSubmitBtn').textContent = '+ Add Role';
}
</script>
@endpush
