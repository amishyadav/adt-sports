@extends('layouts.admin')
@section('title','New Tournament')
@section('content')

<div class="page-hd">
  <div>
    <h1>New Tournament</h1>
    <div class="page-hd-sub">KTMS</div>
  </div>
  <div>
    <a href="{{ route('admin.ktms.tournaments.index') }}" class="btn btn-ghost">
      <i class="fa-solid fa-arrow-left"></i> Back to tournaments
    </a>
  </div>
</div>

<form action="{{ route('admin.ktms.tournaments.store') }}" method="POST">
  @csrf
  @include('admin.ktms.tournaments._form')

  <div class="ktms-btn-row" style="margin-top:16px">
    <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-plus"></i> Create Tournament</button>
    <a href="{{ route('admin.ktms.tournaments.index') }}" class="btn btn-ghost btn-lg">Cancel</a>
  </div>
</form>
@endsection
