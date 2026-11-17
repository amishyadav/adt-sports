@extends('layouts.admin')
@section('title','Add User')
@section('content')

<div class="page-hd">
  <div>
    <h1>Add User</h1>
    <div class="page-hd-sub">Create an account and set its password</div>
  </div>
  <div>
    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">
      <i class="fa-solid fa-arrow-left"></i> Back to users
    </a>
  </div>
</div>

<form action="{{ route('admin.users.store') }}" method="POST" enctype="multipart/form-data">
  @csrf

  @include('admin.users._form', ['user' => null, 'currentRoleId' => null])

  <div class="form-actions">
    <button type="submit" class="btn btn-primary btn-lg">
      <i class="fa-solid fa-user-plus"></i> Create User
    </button>
    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost btn-lg">Cancel</a>
  </div>
</form>
@endsection

@push('styles')
<style>
  .form-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
  @media (max-width:560px){ .form-actions .btn{flex:1;justify-content:center} }
</style>
@endpush
