@extends('layouts.admin')
@section('title','New Match')
@section('content')

<div class="page-hd">
  <div>
    <h1>New Match</h1>
    <div class="page-hd-sub">{{ $tournament->name }}</div>
  </div>
  <div>
    <a href="{{ route('admin.ktms.tournaments.show', $tournament) }}" class="btn btn-ghost">
      <i class="fa-solid fa-arrow-left"></i> Back to tournament
    </a>
  </div>
</div>

<form action="{{ route('admin.ktms.matches.store', $tournament) }}" method="POST">
  @csrf
  @include('admin.ktms.matches._form')

  <div class="ktms-btn-row" style="margin-top:16px">
    <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-plus"></i> Create Match</button>
    <a href="{{ route('admin.ktms.tournaments.show', $tournament) }}" class="btn btn-ghost btn-lg">Cancel</a>
  </div>
</form>
@endsection
