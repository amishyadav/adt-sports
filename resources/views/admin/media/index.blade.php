@extends('layouts.admin')
@section('title','Media Library')
@section('content')

<div class="page-hd">
  <div><h1>Media Library</h1><div class="page-hd-sub">{{ $media->total() }} files</div></div>
  <label for="uploadInput" class="btn btn-primary" style="cursor:pointer"><i class="fa-solid fa-upload"></i> Upload Images</label>
  <input type="file" id="uploadInput" multiple accept="image/*" style="display:none" onchange="uploadFiles(this.files)">
</div>

<div class="drop-zone" id="dropZone">
  <div class="drop-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
  <p style="color:var(--ink2);font-size:14px;margin-bottom:4px">Drag & drop images here, or click Upload above</p>
  <p style="color:var(--ink3);font-size:12px">PNG, JPG, GIF, WebP — max 10MB each</p>
</div>

<div id="uploadProgress" style="display:none;margin-bottom:16px">
  <div style="background:var(--card);border:1px solid var(--border);border-radius:8px;padding:12px 16px;font-size:13px;color:var(--ink2)">
    <i class="fa-solid fa-spinner fa-spin"></i> Uploading files… please wait.
  </div>
</div>

<div class="media-grid" id="mediaGrid">
  @forelse($media as $m)
  <div class="media-item" id="media-{{ $m->id }}">
    <div class="media-thumb">
      <img src="{{ $m->url }}" alt="{{ $m->original_name }}" loading="lazy"
           onerror="this.style.display='none';this.parentNode.innerHTML='&lt;i class=&quot;fa-solid fa-image&quot;&gt;&lt;/i&gt;'">
    </div>
    <div class="media-info">
      <div class="media-name" title="{{ $m->original_name }}">{{ $m->original_name }}</div>
      <div class="media-size">{{ $m->formatted_size }}</div>
      <input type="text" class="media-alt" value="{{ $m->alt }}" placeholder="Alt text — for SEO &amp; screen readers"
             data-url="{{ route('admin.media.update', $m) }}" onchange="saveAlt(this)"
             style="width:100%;margin-top:6px;background:var(--card);border:1px solid var(--border);border-radius:5px;padding:5px 8px;font-size:11px;color:var(--ink);outline:none">
      <div style="display:flex;gap:4px;margin-top:7px">
        <button class="btn btn-ghost btn-sm" style="font-size:10px;padding:3px 8px;flex:1"
          onclick="copyUrl('{{ $m->url }}')"><i class="fa-solid fa-link"></i> Copy URL</button>
        <form action="{{ route('admin.media.destroy',$m) }}" method="POST" style="display:inline"
              onsubmit="return confirm('Delete this image?')">
          @csrf @method('DELETE')
          <button type="submit" class="btn btn-danger btn-sm" style="font-size:10px;padding:3px 8px"><i class="fa-solid fa-trash-can"></i></button>
        </form>
      </div>
    </div>
  </div>
  @empty
  <div class="empty-state" style="grid-column:1/-1">
    <div style="font-size:44px;margin-bottom:12px"><i class="fa-solid fa-image"></i></div>
    <p>No images uploaded yet. Upload your first image above.</p>
  </div>
  @endforelse
</div>

@if($media->hasPages())
  <div style="padding:16px 0">{{ $media->links() }}</div>
@endif

@endsection

@push('scripts')
<script>
// Drag & drop
const dz = document.getElementById('dropZone');
dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('over'); });
dz.addEventListener('dragleave', () => dz.classList.remove('over'));
dz.addEventListener('drop', e => {
  e.preventDefault(); dz.classList.remove('over');
  uploadFiles(e.dataTransfer.files);
});
dz.addEventListener('click', () => document.getElementById('uploadInput').click());

async function uploadFiles(files) {
  if (!files.length) return;
  document.getElementById('uploadProgress').style.display = 'block';
  for (const file of files) {
    const fd = new FormData();
    fd.append('file', file);
    fd.append('_token', '{{ csrf_token() }}');
    try {
      const r = await fetch('{{ route("admin.media.upload") }}', {method:'POST',body:fd});
      const d = await r.json();
      if (d.url) prependMedia(d);
    } catch(e) { console.error(e); }
  }
  document.getElementById('uploadProgress').style.display = 'none';
}

function prependMedia(d) {
  const grid = document.getElementById('mediaGrid');
  const div = document.createElement('div');
  div.className = 'media-item';
  div.innerHTML = `
    <div class="media-thumb"><img src="${d.url}" style="width:100%;height:100%;object-fit:cover"></div>
    <div class="media-info">
      <div class="media-name">${d.name}</div>
      <div class="media-size">Just uploaded</div>
      <input type="text" class="media-alt" value="${(d.alt||'').replace(/"/g,'&quot;')}" placeholder="Alt text — for SEO & screen readers"
             data-url="${MEDIA_BASE}/${d.id}" onchange="saveAlt(this)"
             style="width:100%;margin-top:6px;background:var(--card);border:1px solid var(--border);border-radius:5px;padding:5px 8px;font-size:11px;color:var(--ink);outline:none">
      <div style="margin-top:7px">
        <button class="btn btn-ghost btn-sm" style="font-size:10px;padding:3px 8px;width:100%" onclick="copyUrl('${d.url}')"><i class="fa-solid fa-link"></i> Copy URL</button>
      </div>
    </div>`;
  grid.prepend(div);
}

const MEDIA_TOKEN = '{{ csrf_token() }}';
const MEDIA_BASE = '{{ url('/admin/media') }}';
function saveAlt(el) {
  fetch(el.dataset.url, {
    method:'PUT',
    headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':MEDIA_TOKEN},
    body:JSON.stringify({alt: el.value})
  })
  .then(r => { el.style.borderColor = r.ok ? '#16a34a' : '#e0245e'; setTimeout(()=>{el.style.borderColor='';}, 900); })
  .catch(() => { el.style.borderColor = '#e0245e'; });
}

function copyUrl(url) {
  const full = window.location.origin + url;
  navigator.clipboard.writeText(full)
    .then(() => { alert('URL copied: ' + full); })
    .catch(() => prompt('Copy this URL:', full));
}
</script>
@endpush
