@extends('layouts.admin')
@section('title','Edit Match')
@section('content')

<div class="page-hd">
  <div>
    <h1>Edit Match</h1>
    <div class="page-hd-sub">{{ $match->title }} · {{ $tournament->name }}</div>
  </div>
  <div>
    <a href="{{ route('admin.ktms.matches.show', $match) }}" class="btn btn-ghost">
      <i class="fa-solid fa-arrow-left"></i> Back to match
    </a>
  </div>
</div>

<form action="{{ route('admin.ktms.matches.update', $match) }}" method="POST">
  @csrf @method('PUT')
  @include('admin.ktms.matches._form')

  <div class="ktms-btn-row" style="margin-top:16px;align-items:center">
    <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-check"></i> Save Changes</button>
    <a href="{{ route('admin.ktms.matches.show', $match) }}" class="btn btn-ghost btn-lg">Cancel</a>

    <button type="button" class="btn btn-danger btn-lg" style="margin-left:auto"
            onclick="document.getElementById('deleteMatch').requestSubmit()">
      <i class="fa-solid fa-trash-can"></i> Delete Match
    </button>
  </div>
</form>

<div class="panel-card" style="margin-top:16px">
  <h4>Reset Match</h4>
  <p style="font-size:12.5px;color:var(--ink2);line-height:1.6;margin-bottom:12px">
    Clears the score, every raid and defensive action, the whole timeline and both
    clocks, puts the match back to scheduled and rebuilds the lineup. The affected
    players' statistics are recalculated so nothing is left over.
  </p>
  <form action="{{ route('admin.ktms.matches.reset', $match) }}" method="POST"
        onsubmit="return confirm('Reset {{ addslashes($match->title) }}? All scoring for this match is deleted. This cannot be undone.')">
    @csrf
    <button type="submit" class="btn btn-amber">
      <i class="fa-solid fa-arrows-rotate"></i> Reset this match
    </button>
  </form>
</div>

{{-- Separate form: it cannot be nested inside the edit form above. --}}
<form id="deleteMatch" action="{{ route('admin.ktms.matches.destroy', $match) }}" method="POST" style="display:none"
      onsubmit="return confirm('Delete {{ addslashes($match->title) }}? Its raids, timeline and lineup go too. This cannot be undone.')">
  @csrf @method('DELETE')
</form>
@endsection
