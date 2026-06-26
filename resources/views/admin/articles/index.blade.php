@extends('layouts.admin')
@section('title','All Articles')
@section('content')

<div class="page-hd">
  <div><h1>All Articles</h1><div class="page-hd-sub">{{ $articles->total() }} total</div></div>
  <div style="display:flex;gap:8px;align-items:center">
    <a href="{{ route('admin.articles.trash') }}" class="btn btn-ghost"><i class="fa-solid fa-trash-can"></i> Trash</a>
    <a href="{{ route('admin.articles.create') }}" class="btn btn-primary"><i class="fa-solid fa-pen-nib"></i> New Article</a>
  </div>
</div>

<div class="table-wrap">
  <div class="table-hd">
    <h3>Articles</h3>
    <div class="table-filters">
      <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <input type="text" name="search" class="search-input" placeholder="Search title…" value="{{ request('search') }}">
        <select name="status" class="filter-select" onchange="this.form.submit()">
          <option value="">All Status</option>
          <option value="published" {{ request('status')=='published'?'selected':'' }}>Published</option>
          <option value="draft"     {{ request('status')=='draft'?'selected':'' }}>Drafts</option>
        </select>
        <select name="category" class="filter-select" onchange="this.form.submit()">
          <option value="">All Categories</option>
          @foreach($categories as $c)
            <option value="{{ $c->id }}" {{ request('category')==$c->id?'selected':'' }}>{{ $c->name }}</option>
          @endforeach
        </select>
        <button type="submit" class="btn btn-ghost btn-sm">Filter</button>
        @if(request()->hasAny(['search','status','category']))
          <a href="{{ route('admin.articles.index') }}" class="btn btn-ghost btn-sm"><i class="fa-solid fa-xmark"></i> Clear</a>
        @endif
      </form>
    </div>
  </div>

  {{-- Bulk-action bar: appears when one or more rows are selected. The hidden
       form lives here; row checkboxes attach to it via the HTML5 form="" attr
       so we never nest forms inside the per-row action forms. --}}
  <div id="bulkBar" style="display:none;align-items:center;gap:10px;padding:10px 16px;background:var(--card);border-bottom:1px solid var(--border);font-size:13px">
    <strong id="bulkCount" style="color:var(--ink)">0 selected</strong>
    <form id="bulkForm" method="POST" action="{{ route('admin.articles.bulk') }}" style="display:inline-flex;gap:6px" onsubmit="return bulkConfirm(event)">
      @csrf
      <button type="submit" name="action" value="publish" class="btn btn-success btn-sm"><i class="fa-solid fa-rocket"></i> Publish</button>
      <button type="submit" name="action" value="unpublish" class="btn btn-amber btn-sm"><i class="fa-solid fa-eye-slash"></i> Unpublish</button>
      <button type="submit" name="action" value="trash" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash-can"></i> Trash</button>
    </form>
  </div>

  <table>
    <thead>
      <tr><th style="width:30px"><input type="checkbox" id="selectAll" title="Select all" onclick="toggleAll(this)"></th><th>Title</th><th>Category</th><th>Author</th><th>Status</th><th>Views</th><th>Date</th><th>Actions</th></tr>
    </thead>
    <tbody>
      @forelse($articles as $a)
      <tr>
        <td><input type="checkbox" name="ids[]" value="{{ $a->id }}" form="bulkForm" class="rowchk" onchange="bulkSync()"></td>
        <td class="td-title">
          <a href="{{ route('admin.articles.edit',$a) }}" style="color:var(--ink)">{{ Str::limit($a->title,55) }}</a>
          <small>{{ $a->slug }}</small>
        </td>
        <td>
          @if($a->category)
            <span style="color:{{ $a->category->color }};font-size:12px;font-weight:500">● {{ $a->category->name }}</span>
          @else <span style="color:var(--ink3)">—</span> @endif
        </td>
        <td style="font-size:12px;color:var(--ink3)">{{ $a->author?->name ?? '—' }}</td>
        <td>
          @if($a->isScheduled())
            <span class="badge badge-published" style="background:#7C3AED1a;color:#7C3AED;border-color:#7C3AED55"
                  title="Goes live {{ $a->published_at->format('d M Y, H:i') }}">scheduled</span>
            <div style="font-size:10px;color:var(--ink3);margin-top:3px">{{ $a->published_at->format('d M Y, H:i') }}</div>
          @else
            <span class="badge badge-{{ $a->status }}">{{ $a->status }}</span>
          @endif
          @if($a->featured) <span style="font-size:12px;color:#FCD34D" title="Featured"><i class="fa-solid fa-star"></i></span> @endif
          @if($a->breaking) <span style="font-size:11px;color:#e0245e" title="Breaking"><i class="fa-solid fa-circle"></i></span> @endif
        </td>
        <td style="font-weight:500">{{ number_format($a->views) }}</td>
        <td style="font-size:11px;color:var(--ink3);white-space:nowrap">{{ $a->created_at->format('d M Y') }}</td>
        <td>
          <div class="actions">
            <a href="{{ route('admin.articles.edit',$a) }}" class="btn btn-ghost btn-sm"><i class="fa-solid fa-pen"></i> Edit</a>
            @if($a->status==='draft')
              <form action="{{ route('admin.articles.update',$a) }}" method="POST" style="display:inline">
                @csrf @method('PUT')
                <input type="hidden" name="title" value="{{ $a->title }}">
                <input type="hidden" name="status" value="published">
                <button type="submit" class="btn btn-success btn-sm" title="Publish"><i class="fa-solid fa-rocket"></i></button>
              </form>
            @else
              <form action="{{ route('admin.articles.update',$a) }}" method="POST" style="display:inline">
                @csrf @method('PUT')
                <input type="hidden" name="title" value="{{ $a->title }}">
                <input type="hidden" name="status" value="draft">
                <button type="submit" class="btn btn-amber btn-sm" title="Unpublish"><i class="fa-solid fa-eye-slash"></i></button>
              </form>
            @endif
            <form action="{{ route('admin.articles.destroy',$a) }}" method="POST" style="display:inline"
                  onsubmit="return confirm('Delete this article? Cannot be undone.')">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash-can"></i></button>
            </form>
          </div>
        </td>
      </tr>
      @empty
      <tr><td colspan="8">
        <div class="empty-state"><i class="fa-solid fa-inbox"></i>No articles found. <a href="{{ route('admin.articles.create') }}" style="color:var(--brand)">Write one?</a></div>
      </td></tr>
      @endforelse
    </tbody>
  </table>

  @if($articles->hasPages())
    <div style="padding:12px 16px;border-top:1px solid var(--border)">{{ $articles->links() }}</div>
  @endif
  <div style="padding:8px 16px;font-size:11px;color:var(--ink3);border-top:1px solid var(--border2)">
    Showing {{ $articles->firstItem() }}–{{ $articles->lastItem() }} of {{ $articles->total() }}
  </div>
</div>
@endsection

@push('scripts')
<script>
function rowChecks(){ return Array.prototype.slice.call(document.querySelectorAll('.rowchk')); }
function bulkSync(){
  var sel = rowChecks().filter(function(c){return c.checked;});
  var bar = document.getElementById('bulkBar');
  document.getElementById('bulkCount').textContent = sel.length + ' selected';
  bar.style.display = sel.length ? 'flex' : 'none';
  var all = rowChecks();
  var master = document.getElementById('selectAll');
  master.checked = all.length && sel.length === all.length;
  master.indeterminate = sel.length > 0 && sel.length < all.length;
}
function toggleAll(master){ rowChecks().forEach(function(c){ c.checked = master.checked; }); bulkSync(); }
function bulkConfirm(e){
  var sel = rowChecks().filter(function(c){return c.checked;});
  if(!sel.length){ e.preventDefault(); return false; }
  var action = (e.submitter && e.submitter.value) || '';
  if(action === 'trash' && !confirm('Move ' + sel.length + ' article(s) to Trash?')){ e.preventDefault(); return false; }
  return true;
}
</script>
@endpush
