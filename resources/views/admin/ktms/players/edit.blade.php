@extends('layouts.admin')
@section('title','Edit Player')
@section('content')

<div class="page-hd">
  <div>
    <h1>Edit Player</h1>
    <div class="page-hd-sub">{{ $player->display_name }} · {{ $team->name }}</div>
  </div>
  <div>
    <a href="{{ route('admin.ktms.players.show', $player) }}" class="btn btn-ghost">
      <i class="fa-solid fa-arrow-left"></i> Back to player
    </a>
  </div>
</div>

<form action="{{ route('admin.ktms.players.update', $player) }}" method="POST">
  @csrf @method('PUT')
  @include('admin.ktms.players._form')

  <div class="ktms-btn-row" style="margin-top:16px;align-items:center">
    <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-check"></i> Save Changes</button>
    <a href="{{ route('admin.ktms.players.show', $player) }}" class="btn btn-ghost btn-lg">Cancel</a>

    <button type="button" class="btn btn-danger btn-lg" style="margin-left:auto"
            onclick="document.getElementById('deletePlayer').requestSubmit()">
      <i class="fa-solid fa-trash-can"></i> Delete Player
    </button>
  </div>
</form>

{{-- Separate form: it cannot be nested inside the edit form above. --}}
<form id="deletePlayer" action="{{ route('admin.ktms.players.destroy', $player) }}" method="POST" style="display:none"
      onsubmit="return confirm('Delete {{ addslashes($player->name) }}? Their raids, defensive actions and statistics go too. This cannot be undone.')">
  @csrf @method('DELETE')
</form>
@endsection
