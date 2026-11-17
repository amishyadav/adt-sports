@extends('layouts.admin')
@section('title','Permissions')
@section('content')

<div class="page-hd">
  <div>
    <h1>Permissions</h1>
    <div class="page-hd-sub">
      {{ $permissions->total() }} {{ Str::plural('permission', $permissions->total()) }}
      · {{ $roles->count() }} {{ Str::plural('role', $roles->count()) }}
    </div>
  </div>
  <div>
    <a href="{{ route('admin.roles.index') }}" class="btn btn-ghost">
      <i class="fa-solid fa-user-shield"></i> Manage roles
    </a>
  </div>
</div>

<div class="split-grid">

  {{-- Permission × role grid --}}
  <div class="table-wrap">
    <div class="table-hd">
      <h3>Who Gets What</h3>
      <div class="table-filters">
        <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
          <input type="text" name="search" class="search-input" placeholder="Search permissions…" value="{{ request('search') }}">
          <button type="submit" class="btn btn-ghost btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
          @if(request('search'))
            <a href="{{ route('admin.permissions.index') }}" class="btn btn-ghost btn-sm">Clear</a>
          @endif
        </form>
        @if($roles->isNotEmpty() && $permissions->isNotEmpty())
          {{-- Lives outside the grid form, so it points back at it by id. --}}
          <button type="submit" form="permSyncForm" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-floppy-disk"></i> Save assignments
          </button>
        @endif
      </div>
    </div>

    @if($roles->isEmpty())
      <div class="empty-state">
        <i class="fa-solid fa-user-shield"></i>
        There are no roles yet, so there is nothing to grant permissions to.
        <div style="margin-top:14px">
          <a href="{{ route('admin.roles.index') }}" class="btn btn-primary btn-sm">Add a role first</a>
        </div>
      </div>
    @elseif($permissions->isEmpty())
      <div class="empty-state">
        <i class="fa-solid fa-key"></i>
        @if(request('search'))
          No permission matches “{{ request('search') }}”.
        @else
          No permissions yet — add one on the right.
        @endif
      </div>
    @else
      <form action="{{ route('admin.permissions.sync') }}" method="POST" id="permSyncForm">
        @csrf @method('PUT')

        {{--
          Which rows and columns were actually drawn. The controller only
          touches these, so saving one page of a paginated grid cannot wipe the
          grants on another page — an unticked box and a box that was never
          rendered are indistinguishable in a form submission otherwise.
        --}}
        @foreach($permissions as $p)
          <input type="hidden" name="page_permissions[]" value="{{ $p->id }}">
        @endforeach
        @foreach($roles as $role)
          <input type="hidden" name="page_roles[]" value="{{ $role->id }}">
        @endforeach

        <div class="perm-scroll">
          <table class="perm-table">
            <thead>
              <tr>
                <th>Permission</th>
                @foreach($roles as $role)
                  <th class="perm-col">
                    <span class="perm-col-name" title="{{ $role->name }}">{{ $role->name }}</span>
                    <button type="button" class="perm-col-all" onclick="togglePermColumn({{ $role->id }}, this)"
                            title="Tick or untick this whole column">all</button>
                  </th>
                @endforeach
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($permissions as $p)
              <tr>
                <td style="font-weight:500;color:var(--ink)">
                  <span style="display:inline-flex;align-items:center;gap:9px">
                    <span class="perm-chip"><i class="fa-solid fa-key"></i></span>
                    {{ $p->name }}
                  </span>
                </td>
                @foreach($roles as $role)
                  <td class="perm-col">
                    <label class="perm-box" title="{{ $role->name }} → {{ $p->name }}">
                      <input type="checkbox"
                             class="perm-cb"
                             data-role="{{ $role->id }}"
                             name="grid[{{ $role->id }}][]"
                             value="{{ $p->id }}"
                             {{ isset($granted[$role->id][$p->id]) ? 'checked' : '' }}>
                    </label>
                  </td>
                @endforeach
                <td>
                  <div class="actions">
                    <button type="button" class="btn btn-ghost btn-sm"
                      onclick="openPermEdit({{ $p->id }},'{{ addslashes($p->name) }}')">
                      <i class="fa-solid fa-pen"></i> Rename
                    </button>
                    {{-- Forms cannot nest, so the delete form sits after the
                         grid and this button points at it. --}}
                    <button type="submit" form="delPerm{{ $p->id }}" class="btn btn-danger btn-sm"
                            onclick="return confirm('Delete the \'{{ $p->name }}\' permission? It is revoked from every role that has it.')">
                      <i class="fa-solid fa-trash-can"></i>
                    </button>
                  </div>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="perm-save-row">
          <span style="font-size:12px;color:var(--ink3)">
            Changes are not stored until you save.
          </span>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-floppy-disk"></i> Save assignments
          </button>
        </div>
      </form>

      @if($permissions->hasPages())
        <div style="padding:14px">{{ $permissions->links() }}</div>
      @endif

      {{-- Delete targets for the row buttons above. --}}
      @foreach($permissions as $p)
        <form id="delPerm{{ $p->id }}" action="{{ route('admin.permissions.destroy',$p) }}" method="POST" style="display:none">
          @csrf @method('DELETE')
        </form>
      @endforeach
    @endif
  </div>

  {{-- Add / Rename form --}}
  <div>
    <div class="panel-card" id="permFormCard">
      <h4 id="permFormTitle">Add Permission</h4>
      <form action="{{ route('admin.permissions.store') }}" method="POST" id="permForm">
        @csrf
        <input type="hidden" name="_method" id="permMethod" value="POST">

        <div class="field" style="margin-bottom:16px">
          <label>Permission Name *</label>
          <input type="text" name="name" id="permName" required maxlength="255"
                 placeholder="e.g. publish articles" value="{{ old('name') }}" autocomplete="off">
          <div class="field-hint">Name the action, not the role — “publish articles”, “delete comments”.</div>
        </div>

        <div id="permRenameNote" style="display:none;margin-bottom:14px;padding:10px 12px;border:1px solid rgba(217,119,6,.35);background:rgba(217,119,6,.1);border-radius:7px;font-size:12px;color:#FCD34D;line-height:1.5">
          <i class="fa-solid fa-triangle-exclamation"></i>
          Permissions are referred to by name in code. Renaming one does not update those references.
        </div>

        <div style="display:flex;gap:8px">
          <button type="submit" class="btn btn-primary" id="permSubmitBtn">+ Add Permission</button>
          <button type="button" class="btn btn-ghost" onclick="resetPermForm()">Reset</button>
        </div>
      </form>
    </div>

    <div class="panel-card">
      <h4>Reading the grid</h4>
      <p style="font-size:12.5px;color:var(--ink2);line-height:1.6;margin-bottom:10px">
        Permissions run down the side, roles across the top. Tick a box to grant
        that permission to that role, then save.
      </p>
      <p style="font-size:12.5px;color:var(--ink3);line-height:1.6">
        Only the page you are looking at is saved, so grants on other pages are
        left alone.
      </p>
    </div>
  </div>

</div>
@endsection

@push('styles')
<style>
  /* The grid grows a column per role, so it gets its own scroller. The parent
     .table-wrap clips its overflow to keep the rounded corners. */
  .perm-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
  .perm-chip{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:7px;background:rgba(212,66,10,.14);color:var(--brand);font-size:12px;flex-shrink:0}
  .perm-col{text-align:center;white-space:nowrap}
  .perm-col-name{display:block;max-width:120px;overflow:hidden;text-overflow:ellipsis}
  .perm-col-all{display:inline-block;margin-top:3px;padding:0;background:none;border:none;font:inherit;font-size:9.5px;letter-spacing:.5px;text-transform:uppercase;color:var(--ink3);cursor:pointer;text-decoration:underline}
  .perm-col-all:hover{color:var(--brand)}
  /* A padded label so the hit area is finger-sized, not just the 18px box. */
  .perm-box{display:inline-flex;align-items:center;justify-content:center;padding:6px;cursor:pointer}
  .perm-cb{width:18px;height:18px;accent-color:var(--brand);cursor:pointer;margin:0}
  .perm-save-row{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:12px 16px;border-top:1px solid var(--border)}
</style>
@endpush

@push('scripts')
<script>
/* Tick or untick a whole role column — only the boxes on this page, which is
   also all the save touches. */
function togglePermColumn(roleId, btn) {
  var boxes = document.querySelectorAll('.perm-cb[data-role="' + roleId + '"]');
  if (!boxes.length) { return; }

  var allOn = Array.prototype.every.call(boxes, function (b) { return b.checked; });
  Array.prototype.forEach.call(boxes, function (b) { b.checked = !allOn; });

  if (btn) { btn.textContent = allOn ? 'all' : 'none'; }
}

function openPermEdit(id, name) {
  document.getElementById('permFormTitle').textContent = 'Rename Permission';
  document.getElementById('permForm').action = '/admin/permissions/' + id;
  document.getElementById('permMethod').value = 'PUT';
  document.getElementById('permName').value = name;
  document.getElementById('permRenameNote').style.display = 'block';
  document.getElementById('permSubmitBtn').textContent = '✓ Save Name';
  document.getElementById('permFormCard').scrollIntoView({behavior:'smooth'});
  document.getElementById('permName').focus();
}

function resetPermForm() {
  document.getElementById('permFormTitle').textContent = 'Add Permission';
  document.getElementById('permForm').action = '{{ route("admin.permissions.store") }}';
  document.getElementById('permMethod').value = 'POST';
  document.getElementById('permName').value = '';
  document.getElementById('permRenameNote').style.display = 'none';
  document.getElementById('permSubmitBtn').textContent = '+ Add Permission';
}
</script>
@endpush
