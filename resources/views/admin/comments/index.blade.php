@extends('layouts.admin')
@section('title','Comments')
@section('content')

<div class="page-hd">
  <div><h1>Comments</h1><div class="page-hd-sub">{{ $approved->total() }} live · {{ $pending->count() }} hidden</div></div>
</div>

{{-- Live comments — they post instantly; moderate (hide/remove) after the fact --}}
<div class="table-wrap" style="margin-bottom:24px">
  <div class="table-hd">
    <h3><i class="fa-solid fa-comments" style="color:var(--green)"></i> Live comments</h3>
    <span style="font-size:12px;color:var(--ink3)">Comments go live instantly — hide or remove anything that shouldn't be here.</span>
  </div>
  <table>
    <thead><tr><th>Author</th><th>Comment</th><th>Article</th><th>When</th><th>Actions</th></tr></thead>
    <tbody>
      @forelse($approved as $c)
      <tr>
        <td>
          <div style="font-weight:500;color:var(--ink)">{{ $c->author_name }}</div>
          <div style="font-size:11px;color:var(--ink3)">{{ $c->author_email }}</div>
        </td>
        <td style="font-size:13px;color:var(--ink2);max-width:340px">{!! $c->body !!}</td>
        <td style="font-size:12px">
          <a href="{{ route('article', $c->article->slug) }}" target="_blank" style="color:var(--brand)">{{ Str::limit($c->article->title, 40) }}</a>
        </td>
        <td style="font-size:11px;color:var(--ink3)">{{ $c->created_at->diffForHumans() }}</td>
        <td style="white-space:nowrap">
          <form action="{{ route('admin.comments.hide', $c) }}" method="POST" style="display:inline">
            @csrf @method('PUT')
            <button type="submit" class="btn btn-amber btn-sm"><i class="fa-solid fa-eye-slash"></i> Hide</button>
          </form>
          <form action="{{ route('admin.comments.destroy', $c) }}" method="POST" style="display:inline"
                onsubmit="return confirm('Delete this comment permanently?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash-can"></i></button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="5"><div class="empty-state"><i class="fa-regular fa-comment-dots"></i>No comments yet</div></td></tr>
      @endforelse
    </tbody>
  </table>
  @if($approved->hasPages())
    <div style="padding:14px">{{ $approved->links() }}</div>
  @endif
</div>

{{-- Hidden — only what a moderator pulled from the public page --}}
@if($pending->count())
<div class="table-wrap">
  <div class="table-hd"><h3><i class="fa-solid fa-eye-slash" style="color:var(--amber)"></i> Hidden</h3></div>
  <table>
    <thead><tr><th>Author</th><th>Comment</th><th>Article</th><th>When</th><th>Actions</th></tr></thead>
    <tbody>
      @foreach($pending as $c)
      <tr>
        <td>
          <div style="font-weight:500;color:var(--ink)">{{ $c->author_name }}</div>
          <div style="font-size:11px;color:var(--ink3)">{{ $c->author_email }}</div>
        </td>
        <td style="font-size:13px;color:var(--ink2);max-width:340px">{!! $c->body !!}</td>
        <td style="font-size:12px">
          <a href="{{ route('article', $c->article->slug) }}" target="_blank" style="color:var(--brand)">{{ Str::limit($c->article->title, 40) }}</a>
        </td>
        <td style="font-size:11px;color:var(--ink3)">{{ $c->created_at->diffForHumans() }}</td>
        <td style="white-space:nowrap">
          <form action="{{ route('admin.comments.approve', $c) }}" method="POST" style="display:inline">
            @csrf @method('PUT')
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-rotate-left"></i> Restore</button>
          </form>
          <form action="{{ route('admin.comments.destroy', $c) }}" method="POST" style="display:inline"
                onsubmit="return confirm('Delete this comment permanently?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash-can"></i></button>
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

@endsection
