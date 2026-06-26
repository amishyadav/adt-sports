@extends('layouts.admin')
@section('title','Trash')
@section('content')

<div class="page-hd">
  <div><h1>Trash</h1><div class="page-hd-sub">{{ $articles->total() }} trashed article(s)</div></div>
  <a href="{{ route('admin.articles.index') }}" class="btn btn-ghost">← Back to Articles</a>
</div>

<div class="table-wrap">
  <div class="table-hd"><h3>Deleted articles</h3></div>
  <table>
    <thead>
      <tr><th>Title</th><th>Author</th><th>Deleted</th><th>Actions</th></tr>
    </thead>
    <tbody>
      @forelse($articles as $a)
      <tr>
        <td class="td-title">
          {{ Str::limit($a->title, 60) }}
          <small>{{ $a->slug }}</small>
        </td>
        <td style="font-size:12px;color:var(--ink3)">{{ $a->author?->name ?? '—' }}</td>
        <td style="font-size:11px;color:var(--ink3);white-space:nowrap">{{ optional($a->deleted_at)->format('d M Y, H:i') }}</td>
        <td>
          <div class="actions">
            <form action="{{ route('admin.articles.restore', $a->id) }}" method="POST" style="display:inline">
              @csrf @method('PUT')
              <button type="submit" class="btn btn-success btn-sm"><i class="fa-solid fa-rotate-left"></i> Restore</button>
            </form>
            <form action="{{ route('admin.articles.force', $a->id) }}" method="POST" style="display:inline"
                  onsubmit="return confirm('Permanently delete this article? This cannot be undone.')">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-ghost btn-sm" style="color:#dc2626"><i class="fa-solid fa-trash-can"></i> Delete forever</button>
            </form>
          </div>
        </td>
      </tr>
      @empty
      <tr><td colspan="4" style="text-align:center;color:var(--ink3);padding:32px">Trash is empty.</td></tr>
      @endforelse
    </tbody>
  </table>
  <div style="padding:14px">{{ $articles->links() }}</div>
</div>
@endsection
