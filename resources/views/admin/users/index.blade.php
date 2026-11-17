@extends('layouts.admin')
@section('title','Users')
@section('content')

<div class="page-hd">
  <div>
    <h1>Users</h1>
    <div class="page-hd-sub">{{ $users->total() }} {{ Str::plural('account', $users->total()) }}</div>
  </div>
  <div>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
      <i class="fa-solid fa-user-plus"></i> Add User
    </a>
  </div>
</div>

<div class="table-wrap">
  <div class="table-hd">
    <h3>All Users</h3>
    <div class="table-filters">
      <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <input type="text" name="search" class="search-input" placeholder="Name, email or phone…"
               value="{{ request('search') }}">
        <select name="role" class="filter-select" onchange="this.form.submit()">
          <option value="">All Roles</option>
          @foreach($roles as $r)
            <option value="{{ $r->name }}" {{ strcasecmp(request('role',''), $r->name) === 0 ? 'selected' : '' }}>
              {{ $r->name }}
            </option>
          @endforeach
        </select>
        <select name="status" class="filter-select" onchange="this.form.submit()">
          <option value="">All Status</option>
          <option value="active"   {{ request('status')==='active'   ? 'selected' : '' }}>Active</option>
          <option value="inactive" {{ request('status')==='inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
        <button type="submit" class="btn btn-ghost btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
        @if(request('search') || request('role') || request('status'))
          <a href="{{ route('admin.users.index') }}" class="btn btn-ghost btn-sm">Clear</a>
        @endif
      </form>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>User</th><th>Contact</th><th>Role</th><th>Status</th>
        <th>Articles</th><th>Last Login</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($users as $u)
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            @if($u->avatar)
              <img src="{{ $u->avatar }}" alt="{{ $u->full_name }}" class="user-photo" loading="lazy">
            @else
              <div class="user-av" style="background:{{ $u->isAdmin() ? 'var(--brand)' : '#4A4640' }}">{{ $u->initials }}</div>
            @endif
            <div>
              <span style="font-weight:500;color:var(--ink)">{{ $u->full_name }}</span>
              @if($u->id === auth()->id())
                <span style="font-size:11px;color:var(--ink3)"> · you</span>
              @endif
              @if($u->gender)
                <div style="font-size:11px;color:var(--ink3)">{{ \App\Models\User::GENDERS[$u->gender] ?? $u->gender }}</div>
              @endif
            </div>
          </div>
        </td>
        <td style="font-size:12px;color:var(--ink3)">
          {{ $u->email }}
          @if($u->phone)
            <div>{{ $u->phone }}</div>
          @endif
        </td>
        <td>
          <span class="badge badge-{{ $u->isAdmin() ? 'admin' : 'editor' }}">{{ $u->role }}</span>
          @unless($u->hasPanelAccess())
            <div style="font-size:10.5px;color:#FCD34D;margin-top:3px" title="Only Admin and Editor open the admin panel for now">
              <i class="fa-solid fa-triangle-exclamation"></i> no panel access
            </div>
          @endunless
        </td>
        <td>
          <span class="badge {{ $u->isActive() ? 'badge-published' : 'badge-draft' }}">
            {{ $u->isActive() ? 'Active' : 'Inactive' }}
          </span>
        </td>
        <td style="font-weight:500">{{ $u->articles_count }}</td>
        <td style="font-size:11px;color:var(--ink3)">
          {{ $u->last_login_at ? $u->last_login_at->diffForHumans() : 'Never' }}
        </td>
        <td>
          <div class="actions">
            <a href="{{ route('admin.users.edit',$u) }}" class="btn btn-ghost btn-sm">
              <i class="fa-solid fa-pen"></i> Edit
            </a>
            @if($u->id !== auth()->id())
              <form action="{{ route('admin.users.destroy',$u) }}" method="POST" style="display:inline"
                    onsubmit="return confirm('Delete {{ addslashes($u->full_name) }}? Their articles and uploads are reassigned to you.')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash-can"></i></button>
              </form>
            @endif
          </div>
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="7">
          <div class="empty-state">
            <i class="fa-solid fa-users"></i>
            @if(request('search') || request('role') || request('status'))
              No user matches those filters.
            @else
              No users yet
            @endif
          </div>
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>

  @if($users->hasPages())
    <div style="padding:14px">{{ $users->links() }}</div>
  @endif
</div>
@endsection

@push('styles')
<style>
  .user-photo{width:34px;height:34px;border-radius:50%;object-fit:cover;background:var(--panel);border:1px solid var(--border);flex-shrink:0}
</style>
@endpush
