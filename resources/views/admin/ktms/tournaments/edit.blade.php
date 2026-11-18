@extends('layouts.admin')
@section('title','Edit Tournament')
@section('content')

<div class="page-hd">
  <div>
    <h1>Edit Tournament</h1>
    <div class="page-hd-sub">{{ $tournament->name }}</div>
  </div>
  <div>
    <a href="{{ route('admin.ktms.tournaments.show', $tournament) }}" class="btn btn-ghost">
      <i class="fa-solid fa-arrow-left"></i> Back to tournament
    </a>
  </div>
</div>

<form action="{{ route('admin.ktms.tournaments.update', $tournament) }}" method="POST">
  @csrf @method('PUT')
  @include('admin.ktms.tournaments._form')

  <div class="ktms-btn-row" style="margin-top:16px;align-items:center">
    <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-check"></i> Save Changes</button>
    <a href="{{ route('admin.ktms.tournaments.show', $tournament) }}" class="btn btn-ghost btn-lg">Cancel</a>

    <button type="button" class="btn btn-danger btn-lg" style="margin-left:auto"
            onclick="document.getElementById('deleteTournament').requestSubmit()">
      <i class="fa-solid fa-trash-can"></i> Delete Tournament
    </button>
  </div>
</form>

{{-- Separate form: it cannot be nested inside the edit form above. --}}
<form id="deleteTournament" action="{{ route('admin.ktms.tournaments.destroy', $tournament) }}" method="POST"
      style="display:none"
      onsubmit="return confirm('Delete {{ addslashes($tournament->name) }}? Every squad, player, match and statistic in it goes too. This cannot be undone.')">
  @csrf @method('DELETE')
</form>
@endsection
