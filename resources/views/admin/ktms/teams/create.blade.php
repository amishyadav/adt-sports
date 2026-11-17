@extends('layouts.admin')
@section('title','Add Squad')
@section('content')

<div class="page-hd">
  <div>
    <h1>Add Squad</h1>
    <div class="page-hd-sub">{{ $tournament->name }}</div>
  </div>
  <div>
    <a href="{{ route('admin.ktms.tournaments.show', $tournament) }}" class="btn btn-ghost">
      <i class="fa-solid fa-arrow-left"></i> Back to tournament
    </a>
  </div>
</div>

<form action="{{ route('admin.ktms.teams.store', $tournament) }}" method="POST" enctype="multipart/form-data">
  @csrf
  @include('admin.ktms.teams._form')

  <div class="ktms-btn-row" style="margin-top:16px">
    <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-plus"></i> Add Squad</button>
    <a href="{{ route('admin.ktms.tournaments.show', $tournament) }}" class="btn btn-ghost btn-lg">Cancel</a>
  </div>
</form>
@endsection
