@extends('layouts.admin')
@section('title','Edit User')
@section('content')

<div class="page-hd">
  <div>
    <h1>Edit User</h1>
    <div class="page-hd-sub">
      {{ $user->full_name }}
      @if($user->id === auth()->id())
        · this is your own account
      @endif
    </div>
  </div>
  <div>
    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">
      <i class="fa-solid fa-arrow-left"></i> Back to users
    </a>
  </div>
</div>

@if($user->id === auth()->id())
  <div class="alert alert-error" style="background:rgba(217,119,6,.1);border-color:rgba(217,119,6,.35);color:#FCD34D">
    <i class="fa-solid fa-circle-info"></i>
    You are editing your own account. Your role and status are locked to an active
    administrator so you cannot remove your own access to this screen.
  </div>
@endif

<form action="{{ route('admin.users.update', $user) }}" method="POST" enctype="multipart/form-data">
  @csrf @method('PUT')

  @include('admin.users._form')

  <div class="form-actions">
    <button type="submit" class="btn btn-primary btn-lg">
      <i class="fa-solid fa-check"></i> Save Changes
    </button>
    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost btn-lg">Cancel</a>

    @if($user->id !== auth()->id())
      <button type="button" class="btn btn-danger btn-lg" style="margin-left:auto"
              onclick="document.getElementById('deleteUserForm').requestSubmit()">
        <i class="fa-solid fa-trash-can"></i> Delete User
      </button>
    @endif
  </div>
</form>

@if($user->id !== auth()->id())
  {{-- Separate form: it cannot be nested inside the edit form above. --}}
  <form id="deleteUserForm" action="{{ route('admin.users.destroy', $user) }}" method="POST"
        style="display:none"
        onsubmit="return confirm('Delete {{ addslashes($user->full_name) }}? Their articles and uploads are reassigned to you.')">
    @csrf @method('DELETE')
  </form>
@endif
@endsection

@push('styles')
<style>
  .form-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:16px}
  @media (max-width:560px){
    .form-actions .btn{flex:1;justify-content:center}
    .form-actions .btn-danger{margin-left:0 !important}
  }
</style>
@endpush
