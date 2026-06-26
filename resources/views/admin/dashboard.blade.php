@extends('layouts.admin')
@section('title','Dashboard')
@section('content')

<div class="page-hd">
  <div>
    <h1>Dashboard</h1>
    <div class="page-hd-sub">
      @php $h = now()->hour; $g = $h<12?'Good morning':($h<17?'Good afternoon':'Good evening'); @endphp
      {{ $g }}, {{ explode(' ', auth()->user()->name)[0] }}!
    </div>
  </div>
  <a href="{{ route('admin.articles.create') }}" class="btn btn-primary"><i class="fa-solid fa-pen-nib"></i> Write Article</a>
</div>

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-top">
      <span class="stat-label">Total Articles</span>
      <div class="stat-icon" style="background:rgba(212,66,10,.15);color:#D4420A"><i class="fa-solid fa-newspaper"></i></div>
    </div>
    <div class="stat-value">{{ number_format($stats['total']) }}</div>
    <div class="stat-sub">All time</div>
  </div>
  <div class="stat-card">
    <div class="stat-top">
      <span class="stat-label">Published</span>
      <div class="stat-icon" style="background:rgba(22,163,74,.15);color:#16A34A"><i class="fa-solid fa-circle-check"></i></div>
    </div>
    <div class="stat-value">{{ number_format($stats['published']) }}</div>
    <div class="stat-sub">Live on site</div>
  </div>
  <div class="stat-card">
    <div class="stat-top">
      <span class="stat-label">Drafts</span>
      <div class="stat-icon" style="background:rgba(217,119,6,.15);color:#D97706"><i class="fa-solid fa-file-lines"></i></div>
    </div>
    <div class="stat-value">{{ number_format($stats['drafts']) }}</div>
    <div class="stat-sub">Unpublished</div>
  </div>
  <div class="stat-card">
    <div class="stat-top">
      <span class="stat-label">Total Views</span>
      <div class="stat-icon" style="background:rgba(37,99,235,.15);color:#2563EB"><i class="fa-solid fa-eye"></i></div>
    </div>
    <div class="stat-value">{{ number_format($stats['total_views']) }}</div>
    <div class="stat-sub">Across all articles</div>
  </div>
  <div class="stat-card">
    <div class="stat-top">
      <span class="stat-label">Total Likes</span>
      <div class="stat-icon" style="background:rgba(224,36,94,.15);color:#E0245E"><i class="fa-solid fa-heart"></i></div>
    </div>
    <div class="stat-value">{{ number_format($stats['total_likes']) }}</div>
    <div class="stat-sub">Across all articles</div>
  </div>
  <div class="stat-card">
    <div class="stat-top">
      <span class="stat-label">Comments</span>
      <div class="stat-icon" style="background:rgba(124,58,237,.15);color:#7C3AED"><i class="fa-solid fa-comments"></i></div>
    </div>
    <div class="stat-value">{{ number_format($stats['total_comments']) }}</div>
    <div class="stat-sub">Approved &amp; live</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
  <div class="table-wrap">
    <div class="table-hd"><h3>Recent Articles</h3><a href="{{ route('admin.articles.index') }}" class="btn btn-ghost btn-sm">View All</a></div>
    <table>
      <thead><tr><th>Title</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        @forelse($recent as $a)
        <tr>
          <td class="td-title">
            <a href="{{ route('admin.articles.edit',$a) }}" style="color:var(--ink)">{{ Str::limit($a->title,44) }}</a>
            <small>{{ $a->category?->name ?? '—' }}</small>
          </td>
          <td><span class="badge badge-{{ $a->status }}">{{ $a->status }}</span></td>
          <td style="font-size:11px;color:var(--ink3)">{{ $a->created_at->format('d M Y') }}</td>
        </tr>
        @empty
        <tr><td colspan="3"><div class="empty-state"><i class="fa-solid fa-inbox"></i>No articles yet. <a href="{{ route('admin.articles.create') }}" style="color:var(--brand)">Write one?</a></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="table-wrap">
    <div class="table-hd"><h3>Most Viewed</h3></div>
    <table>
      <thead><tr><th>Title</th><th>Views</th><th>Likes</th><th>Comments</th></tr></thead>
      <tbody>
        @forelse($topViewed as $a)
        <tr>
          <td class="td-title"><a href="{{ route('admin.articles.edit',$a) }}" style="color:var(--ink)">{{ Str::limit($a->title,32) }}</a></td>
          <td style="font-weight:600;color:var(--brand)">{{ number_format($a->views) }}</td>
          <td style="font-weight:600;color:#e0245e"><i class="fa-solid fa-heart"></i> {{ number_format($a->likes) }}</td>
          <td style="font-weight:600;color:#7c3aed"><i class="fa-solid fa-comment"></i> {{ number_format($a->comments_count) }}</td>
        </tr>
        @empty
        <tr><td colspan="4"><div class="empty-state"><i class="fa-solid fa-chart-line"></i>No views yet</div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
