@extends('layouts.admin')
@section('title','Add Player')
@section('content')

<div class="page-hd">
  <div>
    <h1>Add Player</h1>
    <div class="page-hd-sub">{{ $team->name }} · {{ $team->tournament->name }}</div>
  </div>
  <div>
    <a href="{{ route('admin.ktms.teams.show', $team) }}" class="btn btn-ghost">
      <i class="fa-solid fa-arrow-left"></i> Back to squad
    </a>
  </div>
</div>

<form action="{{ route('admin.ktms.players.store', $team) }}" method="POST">
  @csrf
  @include('admin.ktms.players._form')

  <div class="ktms-btn-row" style="margin-top:16px">
    <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-user-plus"></i> Add Player</button>
    <a href="{{ route('admin.ktms.teams.show', $team) }}" class="btn btn-ghost btn-lg">Cancel</a>
  </div>
</form>
@endsection
