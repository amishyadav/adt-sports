@extends('layouts.admin')
@section('title','Team Members')
@section('content')

<div class="page-hd">
  <div><h1>Team Members</h1><div class="page-hd-sub">{{ $users->count() }} members</div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start">

  <div class="table-wrap">
    <div class="table-hd"><h3>All Users</h3></div>
    <table>
      <thead><tr><th>Member</th><th>Email</th><th>Role</th><th>Articles</th><th>Last Login</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse($users as $u)
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="user-av" style="background:{{ $u->id===1?'var(--brand)':'#4A4640' }}">{{ $u->initials }}</div>
              <span style="font-weight:500;color:var(--ink)">{{ $u->name }}</span>
            </div>
          </td>
          <td style="font-size:12px;color:var(--ink3)">{{ $u->email }}</td>
          <td><span class="badge badge-{{ $u->role }}">{{ $u->role }}</span></td>
          <td style="font-weight:500">{{ $u->articles_count }}</td>
          <td style="font-size:11px;color:var(--ink3)">
            {{ $u->last_login_at ? $u->last_login_at->diffForHumans() : 'Never' }}
          </td>
          <td>
            @if($u->id !== auth()->id())
              <form action="{{ route('admin.users.destroy',$u) }}" method="POST" style="display:inline"
                    onsubmit="return confirm('Remove {{ $u->name }} from the team?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-user-xmark"></i> Remove</button>
              </form>
            @else
              <span style="font-size:12px;color:var(--ink3)">You</span>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--ink3)">No team members yet</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="panel-card">
    <h4>Invite Team Member</h4>
    <form action="{{ route('admin.users.store') }}" method="POST">
      @csrf
      <div class="field">
        <label>Full Name *</label>
        <input type="text" name="name" required placeholder="Full name" value="{{ old('name') }}">
      </div>
      <div class="field">
        <label>Email Address *</label>
        <input type="email" name="email" required placeholder="email@example.com" value="{{ old('email') }}">
      </div>
      <div class="field" style="margin-bottom:14px">
        <label>Role</label>
        <select name="role">
          <option value="editor">Editor — Write & manage own articles</option>
          <option value="admin">Admin — Full access to everything</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%"><i class="fa-solid fa-paper-plane"></i> Send Invite</button>
      <div class="field-hint" style="margin-top:10px">We'll email them a secure link to set their own password — you never handle it.</div>
    </form>
  </div>

</div>
@endsection
