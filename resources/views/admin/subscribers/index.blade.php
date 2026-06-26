@extends('layouts.admin')
@section('title','Subscribers')
@section('content')

<div class="page-hd">
  <div><h1>Newsletter Subscribers</h1><div class="page-hd-sub">{{ number_format($total) }} total</div></div>
  @if($total > 0)
    <a href="{{ route('admin.subscribers.export') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-download"></i> Export CSV</a>
  @endif
</div>

<div class="table-wrap">
  <div class="table-hd"><h3>All Subscribers</h3></div>
  <table>
    <thead><tr><th>Email</th><th>Source</th><th>Subscribed</th></tr></thead>
    <tbody>
      @forelse($subscribers as $s)
      <tr>
        <td style="font-weight:500;color:var(--ink)">{{ $s->email }}</td>
        <td style="font-size:12px;color:var(--ink3)">{{ $s->source ?: '—' }}</td>
        <td style="font-size:12px;color:var(--ink3)">
          {{ $s->created_at ? $s->created_at->diffForHumans() : '—' }}
        </td>
      </tr>
      @empty
      <tr><td colspan="3"><div class="empty-state"><i class="fa-regular fa-envelope-open"></i>No subscribers yet</div></td></tr>
      @endforelse
    </tbody>
  </table>
  @if($subscribers->hasPages())
    <div style="padding:14px">{{ $subscribers->links() }}</div>
  @endif
</div>

@endsection
