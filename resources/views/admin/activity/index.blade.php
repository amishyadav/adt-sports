@extends('layouts.admin')
@section('title','Activity Log')
@section('content')

@php
  $badge = [
    'article.created'   => ['#16a34a','Article'],
    'article.updated'   => ['#0ea5e9','Article'],
    'article.trashed'   => ['#d97706','Article'],
    'article.restored'  => ['#16a34a','Article'],
    'article.deleted'   => ['#e0245e','Article'],
    'comment.approved'  => ['#16a34a','Comment'],
    'comment.hidden'    => ['#d97706','Comment'],
    'comment.deleted'   => ['#e0245e','Comment'],
    'user.invited'      => ['#16a34a','User'],
    'user.created'      => ['#16a34a','User'],
    'user.deleted'      => ['#e0245e','User'],
    'category.created'  => ['#16a34a','Category'],
    'category.updated'  => ['#0ea5e9','Category'],
    'category.deleted'  => ['#e0245e','Category'],
    'settings.updated'  => ['#8b5cf6','Settings'],
  ];
@endphp

<div class="page-hd">
  <div><h1>Activity Log</h1><div class="page-hd-sub">{{ number_format($logs->total()) }} recorded actions</div></div>
</div>

<div class="table-wrap">
  <div class="table-hd"><h3>Recent activity</h3></div>
  <table>
    <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
    <tbody>
      @forelse($logs as $log)
        @php($b = $badge[$log->action] ?? ['#6b7280', ucfirst(explode('.', $log->action)[0])])
        <tr>
          <td style="font-size:12px;color:var(--ink3);white-space:nowrap" title="{{ $log->created_at }}">
            {{ $log->created_at ? $log->created_at->diffForHumans() : '—' }}
          </td>
          <td style="font-weight:500;color:var(--ink)">{{ $log->user?->name ?? 'System' }}</td>
          <td>
            <span style="display:inline-block;font-size:11px;font-weight:600;padding:2px 8px;border-radius:99px;color:{{ $b[0] }};background:{{ $b[0] }}1a">{{ $b[1] }}</span>
          </td>
          <td style="color:var(--ink2)">{{ $log->description ?: $log->action }}</td>
          <td style="font-size:12px;color:var(--ink3)">{{ $log->ip ?: '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-clock-rotate-left"></i>No activity recorded yet</div></td></tr>
      @endforelse
    </tbody>
  </table>
  @if($logs->hasPages())
    <div style="padding:14px">{{ $logs->links() }}</div>
  @endif
</div>

@endsection
