@extends('layouts.admin')
@section('title','Edit Squad')
@section('content')

<div class="page-hd">
  <div>
    <h1>Edit Squad</h1>
    <div class="page-hd-sub">{{ $team->name }} · {{ $tournament->name }}</div>
  </div>
  <div>
    <a href="{{ route('admin.ktms.teams.show', $team) }}" class="btn btn-ghost">
      <i class="fa-solid fa-arrow-left"></i> Back to squad
    </a>
  </div>
</div>

<form action="{{ route('admin.ktms.teams.update', $team) }}" method="POST" enctype="multipart/form-data">
  @csrf @method('PUT')
  @include('admin.ktms.teams._form')

  <div class="ktms-btn-row" style="margin-top:16px;align-items:center">
    <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-check"></i> Save Changes</button>
    <a href="{{ route('admin.ktms.teams.show', $team) }}" class="btn btn-ghost btn-lg">Cancel</a>

    <button type="button" class="btn btn-danger btn-lg" style="margin-left:auto"
            onclick="document.getElementById('deleteTeam').requestSubmit()">
      <i class="fa-solid fa-trash-can"></i> Delete Squad
    </button>
  </div>
</form>

{{-- Separate form: it cannot be nested inside the edit form above. --}}
<form id="deleteTeam" action="{{ route('admin.ktms.teams.destroy', $team) }}" method="POST" style="display:none"
      onsubmit="return confirm('Delete {{ addslashes($team->name) }}? Its players, and any match it appears in, go too. This cannot be undone.')">
  @csrf @method('DELETE')
</form>
@endsection
