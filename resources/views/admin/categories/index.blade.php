@extends('layouts.admin')
@section('title','Categories')
@section('content')

<div class="page-hd">
  <div><h1>Categories</h1><div class="page-hd-sub">{{ $categories->count() }} categories</div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start">

  {{-- Category list --}}
  <div class="table-wrap">
    <div class="table-hd"><h3>All Categories</h3></div>
    <table>
      <thead><tr><th>Name</th><th>Slug</th><th>Color</th><th>Articles</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse($categories as $c)
        <tr>
          <td style="font-weight:500;color:var(--ink)">
            <span style="display:inline-flex;align-items:center;gap:9px">
              <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:7px;background:{{ $c->color }}22;color:{{ $c->color }};font-size:13px;flex-shrink:0"><i class="fa-solid {{ $c->display_icon }}"></i></span>
              {{ $c->name }}
            </span>
          </td>
          <td style="font-size:12px;color:var(--ink3)">{{ $c->slug }}</td>
          <td>
            <div style="width:24px;height:24px;border-radius:50%;background:{{ $c->color }};border:2px solid var(--border);display:inline-block"></div>
          </td>
          <td style="font-weight:600">{{ $c->article_count }}</td>
          <td>
            <div class="actions">
              <button class="btn btn-ghost btn-sm"
                onclick="openEdit({{ $c->id }},'{{ addslashes($c->name) }}','{{ $c->color }}','{{ $c->icon }}','{{ addslashes($c->description ?? '') }}')">
                <i class="fa-solid fa-pen"></i> Edit
              </button>
              <form action="{{ route('admin.categories.destroy',$c) }}" method="POST" style="display:inline"
                    onsubmit="return confirm('Delete category \'{{ $c->name }}\'?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash-can"></i></button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-tag"></i>No categories yet</div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- Add / Edit form --}}
  <div class="panel-card" id="catFormCard">
    <h4 id="catFormTitle">Add New Category</h4>
    <form action="{{ route('admin.categories.store') }}" method="POST" id="catForm">
      @csrf
      <input type="hidden" name="_method" id="catMethod" value="POST">
      <input type="hidden" name="_cat_id" id="catEditId" value="">

      <div class="field">
        <label>Category Name *</label>
        <input type="text" name="name" id="catName" required placeholder="e.g. Match Updates">
      </div>
      <div class="field">
        <label>Accent Color</label>
        <div style="display:flex;align-items:center;gap:10px">
          <input type="color" name="color" id="catColor" value="#D4420A">
          <span style="font-size:12px;color:var(--ink3)">Choose a brand color for this category</span>
        </div>
      </div>
      <div class="field">
        <label>Icon</label>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:9px">
          <span class="icon-preview"><i class="fa-solid" id="catIconPreview"></i></span>
          <span style="font-size:12px;color:var(--ink3)">Shown on cards & menus when there's no cover image</span>
        </div>
        <input type="hidden" name="icon" id="catIcon" value="">
        <div class="icon-grid" id="catIconGrid">
          @foreach(\App\Models\Category::iconChoices() as $ic)
            <button type="button" class="icon-opt" data-icon="{{ $ic }}" onclick="selectIcon('{{ $ic }}')" title="{{ $ic }}"><i class="fa-solid {{ $ic }}"></i></button>
          @endforeach
        </div>
        <input type="text" id="catIconCustom" placeholder="Or paste a Font Awesome class (e.g. fa-volleyball)" autocomplete="off"
          style="width:100%;margin-top:8px;background:var(--card);border:1px solid var(--border);border-radius:6px;padding:7px 10px;font-size:12px;color:var(--ink);outline:none">
        <div class="field-hint" style="margin-top:4px">Browse all free icons at fontawesome.com/search</div>
      </div>
      <div class="field" style="margin-bottom:16px">
        <label>Description <span style="font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label>
        <textarea name="description" id="catDesc" rows="2" placeholder="Brief description…"></textarea>
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary" id="catSubmitBtn">+ Add Category</button>
        <button type="button" class="btn btn-ghost" onclick="resetCatForm()">Reset</button>
      </div>
    </form>
  </div>

</div>
@endsection

@push('styles')
<style>
  .icon-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:6px}
  .icon-opt{display:flex;align-items:center;justify-content:center;height:36px;background:var(--card);border:1px solid var(--border);border-radius:6px;cursor:pointer;color:var(--ink2);font-size:14px;transition:border-color .12s,background .12s,color .12s}
  .icon-opt:hover{border-color:var(--brand);color:var(--ink)}
  .icon-opt.sel{border-color:var(--brand);background:rgba(212,66,10,.12);color:var(--brand)}
  .icon-preview{width:38px;height:38px;flex:0 0 auto;display:flex;align-items:center;justify-content:center;background:var(--card);border:1px solid var(--border);border-radius:8px;color:var(--brand);font-size:18px}
</style>
@endpush

@push('scripts')
<script>
function setCatIcon(icon) {
  document.getElementById('catIcon').value = icon || '';
  document.getElementById('catIconPreview').className = icon ? 'fa-solid ' + icon : 'fa-solid';
  var inGrid = false;
  document.querySelectorAll('.icon-opt').forEach(function(b){
    var m = b.dataset.icon === icon; b.classList.toggle('sel', m); if (m) inGrid = true;
  });
  document.getElementById('catIconCustom').value = (icon && !inGrid) ? icon : '';
}
function selectIcon(cls) { setCatIcon(cls); }
document.getElementById('catIconCustom').addEventListener('input', function () {
  var v = this.value.trim();
  document.getElementById('catIcon').value = v;
  document.getElementById('catIconPreview').className = v ? 'fa-solid ' + v : 'fa-solid';
  document.querySelectorAll('.icon-opt').forEach(function(b){ b.classList.remove('sel'); });
});

function openEdit(id, name, color, icon, desc) {
  document.getElementById('catFormTitle').textContent = 'Edit Category';
  document.getElementById('catForm').action = '/admin/categories/' + id;
  document.getElementById('catMethod').value = 'PUT';
  document.getElementById('catEditId').value = id;
  document.getElementById('catName').value = name;
  document.getElementById('catColor').value = color;
  document.getElementById('catDesc').value = desc;
  setCatIcon(icon);
  document.getElementById('catSubmitBtn').textContent = '✓ Update Category';
  document.getElementById('catFormCard').scrollIntoView({behavior:'smooth'});
}
function resetCatForm() {
  document.getElementById('catFormTitle').textContent = 'Add New Category';
  document.getElementById('catForm').action = '{{ route("admin.categories.store") }}';
  document.getElementById('catMethod').value = 'POST';
  document.getElementById('catEditId').value = '';
  document.getElementById('catName').value = '';
  document.getElementById('catColor').value = '#D4420A';
  document.getElementById('catDesc').value = '';
  setCatIcon('');
  document.getElementById('catSubmitBtn').textContent = '+ Add Category';
}
</script>
@endpush
