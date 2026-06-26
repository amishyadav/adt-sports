@extends('layouts.admin')
@section('title', $article ? 'Edit Article' : 'New Article')

@push('styles')
<style>
  .field .cat-checks{display:grid;grid-template-columns:1fr 1fr;gap:7px;max-height:200px;overflow-y:auto;margin-top:2px;padding-right:2px}
  .field .cat-checks .cat-check{display:flex;align-items:center;gap:8px;margin:0;padding:8px 10px;border:1px solid var(--border);border-radius:8px;cursor:pointer;
    text-transform:none;letter-spacing:0;font-size:12.5px;font-weight:500;color:var(--ink2);line-height:1.2;transition:border-color .15s,color .15s,background .15s}
  .field .cat-checks .cat-check:hover{border-color:var(--brand);color:var(--ink)}
  .field .cat-checks .cat-check:has(input:checked){border-color:var(--brand);background:rgba(212,66,10,.08);color:var(--ink)}
  .field .cat-checks .cat-check input{appearance:auto;width:15px;height:15px;accent-color:var(--brand);flex:0 0 auto;margin:0;cursor:pointer}
  .field .cat-checks .cat-check span{flex:1;min-width:0}
  /* Embedded video preview inside the rich-text editor */
  #rteEditor .embed-responsive{position:relative;width:100%;max-width:560px;padding-bottom:56.25%;height:0;margin:14px 0;border-radius:8px;overflow:hidden;background:#000}
  #rteEditor .embed-responsive iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
  /* Restore + Discard share one amber-outline look on the light banner:
     transparent with dark-amber text, filling amber with white text on hover. */
  #draftRestore,#draftDiscard{background:transparent;border:1px solid #f59e0b;color:#92400e}
  #draftRestore:hover,#draftDiscard:hover{background:#f59e0b;color:#fff}
</style>
@endpush

@section('content')

<form action="{{ $article ? route('admin.articles.update',$article) : route('admin.articles.store') }}"
      method="POST" id="artForm">
  @csrf
  @if($article) @method('PUT') @endif
  {{-- Hidden fields synced by JS --}}
  <input type="hidden" name="body"        id="bodyInput">
  <input type="hidden" name="cover_bg"    id="coverBgInput"    value="{{ old('cover_bg',    $article?->cover_bg    ?? 'linear-gradient(145deg,#1A1410,#221808)') }}">
  <input type="hidden" name="cover_emoji" id="coverEmojiInput" value="{{ old('cover_emoji', $article?->cover_emoji ?? '📰') }}">
  <input type="hidden" name="cover_image" id="coverImgInput"   value="{{ old('cover_image', $article?->cover_image ?? '') }}">
  <input type="hidden" name="tags"        id="tagsInput"       value="{{ old('tags', $article?->tags_list ?? '') }}">

  <div class="page-hd">
    <div>
      <h1>{{ $article ? 'Edit Article' : 'New Article' }}</h1>
      <div class="page-hd-sub">
        @if($article) Last saved {{ $article->updated_at->diffForHumans() }} @else Fill in the details below @endif
      </div>
    </div>
    <div style="display:flex;gap:8px">
      <button type="submit" name="status_override" value="draft"     class="btn btn-ghost"><i class="fa-solid fa-floppy-disk"></i> Save Draft</button>
      <button type="submit" name="status_override" value="published" class="btn btn-ghost"><i class="fa-solid fa-rocket"></i> Publish</button>
    </div>
  </div>

  <div class="editor-grid">

    {{-- ── MAIN COLUMN ─────────────────────────────── --}}
    <div>
      <div class="field">
        <label>Article Title *</label>
        <input type="text" name="title" id="titleInput" required
          value="{{ old('title', $article?->title) }}"
          placeholder="Enter a compelling headline…"
          style="font-size:18px;font-weight:600;padding:13px 16px">
      </div>

      <div class="field">
        <label>URL Slug</label>
        <div style="display:flex;align-items:center;background:var(--card);border:1px solid var(--border);border-radius:6px;overflow:hidden">
          <span style="padding:10px 4px 10px 12px;color:var(--ink3);font-size:13px;white-space:nowrap">/article/</span>
          <input type="text" name="slug" id="slugInput" value="{{ old('slug', $article?->slug) }}" placeholder="auto-generated from title" autocomplete="off"
            style="border:0;background:transparent;padding:10px 12px 10px 0;flex:1;color:var(--ink);outline:none;font-size:13px">
        </div>
        @if($article && $article->isPublished())
          <div class="field-hint" style="color:#d97706"><i class="fa-solid fa-triangle-exclamation"></i> This article is live — changing the slug changes its URL, and old links will 404.</div>
        @else
          <div class="field-hint">Leave blank to auto-generate from the title.</div>
        @endif
      </div>

      <div class="field">
        <label>Excerpt / Summary</label>
        <textarea name="excerpt" rows="3"
          placeholder="A brief summary shown on article cards and in search results…">{{ old('excerpt', $article?->excerpt) }}</textarea>
        <div class="field-hint">Keep under 200 characters for best display.</div>
      </div>

      <div class="field">
        <label>Article Body *</label>
        <div class="rte-wrap">
          <div class="rte-toolbar">
            <button type="button" class="rte-btn" onclick="fmt('bold')"              title="Bold"><b>B</b></button>
            <button type="button" class="rte-btn" onclick="fmt('italic')"            title="Italic"><i>I</i></button>
            <button type="button" class="rte-btn" onclick="fmt('underline')"         title="Underline"><u>U</u></button>
            <div class="rte-sep"></div>
            <button type="button" class="rte-btn" onclick="fmtBlock('h2')"           title="Heading 2" style="font-size:11px;width:auto;padding:0 8px">H2</button>
            <button type="button" class="rte-btn" onclick="fmtBlock('h3')"           title="Heading 3" style="font-size:11px;width:auto;padding:0 8px">H3</button>
            <button type="button" class="rte-btn" onclick="fmtBlock('p')"            title="Paragraph" style="font-size:11px;width:auto;padding:0 8px">¶</button>
            <div class="rte-sep"></div>
            <button type="button" class="rte-btn" onclick="fmt('insertUnorderedList')" title="Bullet list">• ≡</button>
            <button type="button" class="rte-btn" onclick="fmt('insertOrderedList')"   title="Numbered list">1.≡</button>
            <div class="rte-sep"></div>
            <button type="button" class="rte-btn" onclick="fmtBlock('blockquote')"   title="Blockquote">❝</button>
            <button type="button" class="rte-btn" onclick="insertCallout()"          title="Callout box" style="font-size:11px;width:auto;padding:0 7px"><i class="fa-solid fa-box-archive"></i> Box</button>
            <div class="rte-sep"></div>
            <button type="button" class="rte-btn" onclick="insertLink()"             title="Insert link"><i class="fa-solid fa-link"></i></button>
            <button type="button" class="rte-btn" onclick="insertBodyImage()"        title="Insert image"><i class="fa-solid fa-image"></i></button>
            <button type="button" class="rte-btn" onclick="insertEmbed()"           title="Embed YouTube / Vimeo video" style="font-size:11px;width:auto;padding:0 7px"><i class="fa-solid fa-video"></i> Embed</button>
            <button type="button" class="rte-btn" onclick="fmt('removeFormat')"      title="Clear formatting" style="font-size:11px"><i class="fa-solid fa-eraser"></i> Fmt</button>
            <div style="margin-left:auto">
              <button type="button" class="rte-btn" id="htmlModeBtn" onclick="toggleHTML()"
                style="width:auto;padding:0 10px;font-size:11px;font-weight:700">HTML</button>
            </div>
          </div>
          <div id="rteEditor" contenteditable="true">{!! old('body', $article?->body) !!}</div>
          <div class="rte-footer">
            <span id="wordCount">0 words</span>
            <span id="readTimeEst">~0 min read</span>
          </div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:4px">
        <div class="field">
          <label>SEO Title <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:10px">(optional)</span></label>
          <input type="text" name="meta_title" value="{{ old('meta_title',$article?->meta_title) }}" placeholder="Defaults to article title">
          <div class="field-hint" id="metaTitleCount"></div>
        </div>
        <div class="field">
          <label>SEO Description</label>
          <textarea name="meta_desc" rows="2" placeholder="160-char search description…">{{ old('meta_desc',$article?->meta_desc) }}</textarea>
          <div class="field-hint" id="metaDescCount"></div>
        </div>
      </div>

      {{-- Live Google-style search-result preview --}}
      <div class="field" style="margin-top:8px">
        <label>Search result preview</label>
        <div style="background:#fff;border:1px solid var(--border);border-radius:8px;padding:13px 15px">
          <div id="serpTitle" style="color:#1a0dab;font-size:18px;line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"></div>
          <div id="serpUrl" style="color:#006621;font-size:12.5px;margin:3px 0 5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"></div>
          <div id="serpDesc" style="color:#4d5156;font-size:13px;line-height:1.45"></div>
        </div>
      </div>
    </div>

    {{-- ── SIDEBAR ──────────────────────────────────── --}}
    <div>

      {{-- Publish Settings --}}
      <div class="panel-card">
        <h4>Publish Settings</h4>
        @if($article)
        <div class="field" style="margin-bottom:12px">
          <label>Current status</label>
          <div style="font-size:13px;font-weight:600">
            @if($article->isScheduled())
              <span style="color:#7C3AED"><i class="fa-solid fa-clock"></i> Scheduled</span> — goes live {{ $article->published_at->format('d M Y, H:i') }}
            @elseif($article->isPublished())
              <span style="color:#16A34A"><i class="fa-solid fa-circle-check"></i> Published</span>
            @else
              <span style="color:#D97706"><i class="fa-solid fa-file-lines"></i> Draft</span>
            @endif
          </div>
          <div style="font-size:11px;color:var(--ink3);margin-top:4px">Use “Save Draft” or “Publish” above to change it.</div>
        </div>
        @endif
        <div class="field" style="margin-bottom:12px">
          <label>Publish date <span style="opacity:.55;font-weight:400;font-size:11px">— a future time schedules it</span></label>
          <input type="datetime-local" name="published_at"
                 value="{{ old('published_at', optional($article?->published_at)->format('Y-m-d\TH:i')) }}">
        </div>
        <div class="field" style="margin-bottom:14px">
          <label>Primary category <span style="opacity:.55;font-weight:400;font-size:11px;text-transform:none;letter-spacing:0">— used for the URL &amp; canonical</span></label>
          <select name="category_id" id="primaryCategory">
            <option value="">— Select Category —</option>
            @foreach($categories as $cat)
              <option value="{{ $cat->id }}"
                {{ old('category_id',$article?->category_id)==$cat->id?'selected':'' }}>
                {{ $cat->name }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="field" style="margin-bottom:14px">
          <label>Additional categories <span style="opacity:.55;font-weight:400;font-size:11px;text-transform:none;letter-spacing:0">— also list under these</span></label>
          @php($selectedCats = old('categories', $article ? $article->categories->pluck('id')->all() : []))
          <input type="text" id="catSearch" placeholder="Filter categories…" autocomplete="off"
                 style="width:100%;margin-bottom:8px;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:8px 11px;font-size:13px;color:var(--ink);outline:none">
          <div class="cat-checks" id="catChecks">
            @foreach($categories as $cat)
              <label class="cat-check" data-cat-name="{{ Str::lower($cat->name) }}" data-cat-id="{{ $cat->id }}">
                <input type="checkbox" name="categories[]" value="{{ $cat->id }}"
                       {{ in_array($cat->id, $selectedCats) ? 'checked' : '' }}>
                <span>{{ $cat->name }}</span>
              </label>
            @endforeach
          </div>
          <p id="catEmpty" style="display:none;font-size:12px;color:var(--ink3);margin-top:6px">No categories match.</p>
        </div>
        <div class="toggle-row">
          <span class="toggle-label"><i class="fa-solid fa-star" style="color:#FCD34D"></i> Featured Article</span>
          <input type="checkbox" name="featured" value="1" class="toggle-cb"
            {{ old('featured',$article?->featured)?'checked':'' }}>
        </div>
        <div class="toggle-row">
          <span class="toggle-label"><i class="fa-solid fa-circle" style="color:#e0245e;font-size:11px"></i> Breaking News</span>
          <input type="checkbox" name="breaking" value="1" class="toggle-cb"
            {{ old('breaking',$article?->breaking)?'checked':'' }}>
        </div>
      </div>

      {{-- Cover --}}
      <div class="panel-card">
        <h4>Cover Image</h4>
        <div class="cover-preview" id="coverPreview" onclick="document.getElementById('coverFileInput').click()">
          <span id="coverEmojiShow" style="font-size:46px;color:var(--ink3)"><i class="fa-solid fa-image"></i></span>
          <img id="coverImgPreview" src="{{ $article?->cover_image }}"
               style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:{{ $article?->cover_image ? 'block' : 'none' }}">
          <div class="cover-overlay"><i class="fa-solid fa-camera"></i> Change Cover</div>
        </div>
        <input type="file" id="coverFileInput" accept="image/*" style="display:none" onchange="uploadCover(this)">

        <div class="field" style="margin-bottom:0">
          <label>Background Theme</label>
          <div class="bg-grid" id="bgGrid"></div>
        </div>
      </div>

      {{-- Tags --}}
      <div class="panel-card">
        <h4>Tags</h4>
        <div class="tag-wrap" id="tagWrap" onclick="document.getElementById('tagRealInput').focus()"></div>
        <input type="text" class="tag-inp" id="tagRealInput" placeholder="Type a tag, press Enter" list="tagSuggest" autocomplete="off"
          onkeydown="handleTag(event)" style="background:var(--card);border:1px solid var(--border);border-radius:6px;padding:6px 10px;width:100%;margin-top:8px;color:var(--ink);outline:none;font-size:13px">
        <datalist id="tagSuggest">
          @foreach($allTags ?? [] as $t)<option value="{{ $t }}">@endforeach
        </datalist>
        <div class="field-hint" style="margin-top:5px">Separate with Enter or comma — existing tags are suggested as you type.</div>
      </div>

      {{-- Revision history --}}
      @if(($revisions ?? collect())->isNotEmpty())
      <div class="panel-card">
        <h4>Revision history</h4>
        <div style="display:flex;flex-direction:column;gap:6px;max-height:240px;overflow-y:auto">
          @foreach($revisions as $rev)
            <button type="button" onclick="loadRevision('{{ route('admin.articles.revision', [$article, $rev]) }}')"
              style="display:flex;justify-content:space-between;align-items:center;gap:8px;text-align:left;background:var(--card);border:1px solid var(--border);border-radius:6px;padding:8px 10px;cursor:pointer;color:var(--ink2)">
              <span style="font-size:12.5px">{{ $rev->created_at?->diffForHumans() ?? '—' }}</span>
              <small style="font-size:11px;color:var(--ink3)">{{ $rev->user?->name ?? 'Unknown' }}</small>
            </button>
          @endforeach
        </div>
        <div class="field-hint" style="margin-top:8px">Loads a past version into the editor for review — Save to apply it.</div>
      </div>
      @endif

    </div>
  </div>
</form>
@endsection

@push('scripts')
<script>
const BGS=[
  'linear-gradient(145deg,#140E0A,#221808)',
  'linear-gradient(145deg,#0A1420,#101C2A)',
  'linear-gradient(145deg,#0A1A0E,#102016)',
  'linear-gradient(145deg,#1A100A,#221608)',
  'linear-gradient(145deg,#12082A,#1A1030)',
  'linear-gradient(145deg,#0A0A1A,#101020)',
  'linear-gradient(145deg,#141206,#1E1A0A)',
  'linear-gradient(145deg,#180A18,#221030)',
];

// ── Tags ─────────────────────────────────────────────
let tags = [];
const savedTags = document.getElementById('tagsInput').value;
if (savedTags) tags = savedTags.split(',').map(t=>t.trim()).filter(Boolean);
renderTags();

function renderTags() {
  const w = document.getElementById('tagWrap');
  w.innerHTML = tags.map(t =>
    `<span class="tag-chip">${t}<span class="rx" onclick="removeTag('${t.replace(/'/g,"\\'")}')">✕</span></span>`
  ).join('');
  document.getElementById('tagsInput').value = tags.join(', ');
}
function handleTag(e) {
  if (e.key==='Enter'||e.key===',') {
    e.preventDefault();
    const v = e.target.value.trim().replace(/,$/,'');
    if (v && !tags.includes(v)) { tags.push(v); renderTags(); }
    e.target.value='';
  }
}
function removeTag(t) { tags=tags.filter(x=>x!==t); renderTags(); }

// ── Additional categories: filter + disable the primary ───
(function () {
  const search  = document.getElementById('catSearch');
  const primary = document.getElementById('primaryCategory');
  const empty   = document.getElementById('catEmpty');
  const rows    = Array.from(document.querySelectorAll('#catChecks .cat-check'));

  // The primary category can't also be an "additional" one — disable & uncheck it.
  function syncPrimary() {
    const id = primary.value;
    rows.forEach(row => {
      const cb = row.querySelector('input');
      if (row.dataset.catId === id) {
        cb.checked = false; cb.disabled = true;
        row.style.opacity = '.4';
        row.title = 'Already the primary category';
      } else {
        cb.disabled = false;
        row.style.opacity = '';
        row.title = '';
      }
    });
  }

  function filter() {
    const q = (search.value || '').trim().toLowerCase();
    let visible = 0;
    rows.forEach(row => {
      const match = !q || row.dataset.catName.includes(q);
      row.style.display = match ? '' : 'none';
      if (match) visible++;
    });
    empty.style.display = visible ? 'none' : 'block';
  }

  if (search)  search.addEventListener('input', filter);
  if (primary) primary.addEventListener('change', syncPrimary);
  syncPrimary();
})();

// ── BG grid ──────────────────────────────────────────
const curBg = document.getElementById('coverBgInput').value;
document.getElementById('bgGrid').innerHTML = BGS.map((bg,i) =>
  `<div class="bg-swatch${bg===curBg||(!curBg&&i===0)?' sel':''}" style="background:${bg}" onclick="selBg(this,'${bg.replace(/'/g,"\\'")}')"></div>`
).join('');
document.getElementById('coverPreview').style.background = curBg || BGS[0];
function selBg(el, bg) {
  document.querySelectorAll('.bg-swatch').forEach(b=>b.classList.remove('sel'));
  el.classList.add('sel');
  document.getElementById('coverBgInput').value=bg;
  document.getElementById('coverPreview').style.background=bg;
}

// ── Cover upload ─────────────────────────────────────
async function uploadCover(input) {
  const fd = new FormData();
  fd.append('file', input.files[0]);
  fd.append('_token', '{{ csrf_token() }}');
  try {
    const r = await fetch('{{ route("admin.media.upload") }}', {method:'POST',body:fd});
    const d = await r.json();
    document.getElementById('coverImgInput').value = d.url;
    const img = document.getElementById('coverImgPreview');
    img.src = d.url; img.style.display = 'block';
    document.getElementById('coverEmojiShow').style.opacity = '0';
  } catch(e) { alert('Upload failed: ' + e.message); }
}

// ── RTE ──────────────────────────────────────────────
let htmlMode = false;
function fmt(cmd) { document.execCommand(cmd,false,null); document.getElementById('rteEditor').focus(); updateCount(); }
function fmtBlock(tag) { document.execCommand('formatBlock',false,tag); document.getElementById('rteEditor').focus(); updateCount(); }
function insertLink() { const u=prompt('Enter URL:'); if(u) document.execCommand('createLink',false,u); }

// Convert a YouTube/Vimeo share URL into a safe embed iframe and insert it.
// The server-side purifier independently re-checks the host, so a bad URL here
// can never produce a live iframe.
function embedUrlFor(raw) {
  raw = (raw || '').trim();
  let m;
  // youtu.be/ID  or  youtube.com/watch?v=ID  or  /embed/ID  or  /shorts/ID
  if ((m = raw.match(/(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{6,})/))) {
    return 'https://www.youtube.com/embed/' + m[1];
  }
  // vimeo.com/ID
  if ((m = raw.match(/vimeo\.com\/(?:video\/)?(\d+)/))) {
    return 'https://player.vimeo.com/video/' + m[1];
  }
  return null;
}
function insertEmbed() {
  const raw = prompt('Paste a YouTube or Vimeo link:');
  if (!raw) return;
  const src = embedUrlFor(raw);
  if (!src) { alert('Sorry — only YouTube and Vimeo links are supported.'); return; }
  document.getElementById('rteEditor').focus();
  document.execCommand('insertHTML', false,
    '<div class="embed-responsive"><iframe src="' + src + '" frameborder="0"></iframe></div><p><br></p>');
  updateCount();
}

// Load a past revision into the editor for review (non-destructive — Save to apply).
function loadRevision(url) {
  if (!confirm('Load this past version into the editor? Your current unsaved text will be replaced — Save to keep it.')) return;
  fetch(url, { headers: { 'Accept': 'application/json' } })
    .then(function(r){ if(!r.ok) throw new Error('load failed'); return r.json(); })
    .then(function(d){
      var t = document.getElementById('titleInput'); if (t && d.title != null) t.value = d.title;
      var ex = document.querySelector('[name=excerpt]'); if (ex && d.excerpt != null) ex.value = d.excerpt;
      var rte = document.getElementById('rteEditor'); if (rte && d.body != null) rte.innerHTML = d.body;
      var html = document.getElementById('htmlArea');
      if (html && typeof htmlMode !== 'undefined' && htmlMode && d.body != null) html.value = d.body;
      if (typeof updateCount === 'function') updateCount();
      if (window.adtSeoRefresh) window.adtSeoRefresh();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    })
    .catch(function(){ alert('Could not load that revision.'); });
}
async function insertBodyImage() {
  const input = document.createElement('input');
  input.type = 'file'; input.accept = 'image/*';
  input.onchange = async () => {
    if (!input.files[0]) return;
    // Alt text up front (accessibility + SEO) so it's stored on the media record too.
    const alt = (prompt('Describe this image (alt text):', input.files[0].name || '') || '').replace(/"/g,'&quot;');
    const fd = new FormData();
    fd.append('file', input.files[0]);
    fd.append('alt', alt);
    fd.append('_token', '{{ csrf_token() }}');
    try {
      const r = await fetch('{{ route("admin.media.upload") }}', { method:'POST', body:fd });
      if (!r.ok) throw new Error('upload rejected');
      const d = await r.json();
      document.getElementById('rteEditor').focus();
      document.execCommand('insertHTML', false,
        '<img src="' + d.url + '" alt="' + alt + '" style="max-width:100%;border-radius:8px">');
      updateCount();
    } catch(e) { alert('Image upload failed: ' + e.message); }
  };
  input.click();
}
function insertCallout() {
  document.execCommand('insertHTML',false,
    '<div style="background:rgba(212,66,10,.1);border:1px solid rgba(212,66,10,.2);border-radius:6px;padding:14px 18px;margin:16px 0;">📌 Write your callout text here...</div>');
}
function toggleHTML() {
  const ed = document.getElementById('rteEditor');
  const btn = document.getElementById('htmlModeBtn');
  if (!htmlMode) {
    const ta = document.createElement('textarea');
    ta.id='htmlArea';
    ta.style.cssText='width:100%;min-height:380px;background:var(--bg);color:var(--ink);border:none;outline:none;padding:18px 20px;font-family:monospace;font-size:12px;resize:vertical;line-height:1.6;display:block';
    ta.value=ed.innerHTML;
    ed.style.display='none';
    ed.parentNode.insertBefore(ta, ed.nextSibling);
    btn.style.color='var(--brand)'; htmlMode=true;
  } else {
    const ta=document.getElementById('htmlArea');
    ed.innerHTML=ta.value; ta.remove();
    ed.style.display=''; btn.style.color=''; htmlMode=false;
  }
}
function updateCount() {
  const text = htmlMode
    ? (document.getElementById('htmlArea')?.value||'').replace(/<[^>]+>/g,'')
    : (document.getElementById('rteEditor').innerText||'');
  const w = text.trim().split(/\s+/).filter(Boolean).length;
  document.getElementById('wordCount').textContent = w.toLocaleString()+' words';
  document.getElementById('readTimeEst').textContent = '~'+Math.max(1,Math.round(w/200))+' min read';
}
document.getElementById('rteEditor').addEventListener('input',updateCount);
updateCount();

// ── Submit: sync hidden fields ────────────────────────
document.getElementById('artForm').addEventListener('submit', function(e) {
  const body = htmlMode
    ? (document.getElementById('htmlArea')||{value:''}).value
    : document.getElementById('rteEditor').innerHTML;
  document.getElementById('bodyInput').value = body;
  // Status is carried by the clicked button's name=status_override value — no sync needed.
});

// ── URL slug auto-fill + live SEO/SERP preview ────────
(function(){
  var titleEl=document.getElementById('titleInput');
  var slugEl=document.getElementById('slugInput');
  var metaT=document.querySelector('[name=meta_title]');
  var metaD=document.querySelector('[name=meta_desc]');
  var excerpt=document.querySelector('[name=excerpt]');
  var base=@json(url('/article'));
  var siteName=@json(\App\Models\Setting::get('site_name','ADT Sports'));
  var slugTouched=!!(slugEl && slugEl.value.trim());

  function slugify(s){return (s||'').toLowerCase().trim().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');}
  function curSlug(){var v=slugEl?slugEl.value.trim():'';return v?slugify(v):slugify(titleEl?titleEl.value:'');}
  function counter(id,len,max){var el=document.getElementById(id);if(!el)return;el.textContent=len+' / '+max+(len>max?' — too long':'');el.style.color=len>max?'#e0245e':(len>max*0.85?'#d97706':'');}
  function paint(){
    // Mirror the frontend: meta_title alone if set, else "Title — Site".
    var t=(metaT&&metaT.value.trim()) ? metaT.value.trim()
        : (((titleEl&&titleEl.value.trim())||'Article title')+' — '+siteName);
    var d=((metaD&&metaD.value.trim())||(excerpt&&excerpt.value.trim())||'');
    var st=document.getElementById('serpTitle'); if(st) st.textContent=t;
    var su=document.getElementById('serpUrl'); if(su) su.textContent=base+'/'+(curSlug()||'your-slug');
    var sd=document.getElementById('serpDesc'); if(sd) sd.textContent=d.length>160?d.slice(0,160)+'…':d;
    counter('metaTitleCount', metaT?metaT.value.length:0, 60);
    counter('metaDescCount', metaD?metaD.value.length:0, 160);
  }
  if(slugEl){
    slugEl.addEventListener('input',function(){slugTouched=true;paint();});
    slugEl.addEventListener('blur',function(){slugEl.value=slugify(slugEl.value);paint();});
  }
  if(titleEl){titleEl.addEventListener('input',function(){if(!slugTouched&&slugEl)slugEl.value=slugify(titleEl.value);paint();});}
  [metaT,metaD,excerpt].forEach(function(el){if(el)el.addEventListener('input',paint);});
  paint();
  window.adtSeoRefresh=function(){slugTouched=!!(slugEl&&slugEl.value.trim());paint();};
})();

// ── Autosave to localStorage + restore banner + Ctrl/Cmd+S ──
(function(){
  var KEY='adt-draft-'+@json($article?->id ?? 'new');
  var serverTs=@json($article?->updated_at?->timestamp ?? 0);
  var form=document.getElementById('artForm');
  var rte=document.getElementById('rteEditor');
  function fields(){return {
    title:document.getElementById('titleInput'),
    slug:document.getElementById('slugInput'),
    excerpt:document.querySelector('[name=excerpt]'),
    meta_title:document.querySelector('[name=meta_title]'),
    meta_desc:document.querySelector('[name=meta_desc]')
  };}
  function currentBody(){
    return (typeof htmlMode!=='undefined' && htmlMode)
      ? ((document.getElementById('htmlArea')||{value:''}).value)
      : (rte?rte.innerHTML:'');
  }
  function snapshot(){
    var f=fields(),o={ts:Date.now(),body:currentBody(),tags:(document.getElementById('tagsInput')||{}).value||''};
    for(var k in f){o[k]=f[k]?f[k].value:'';}
    return o;
  }
  var timer=null;
  function save(){try{localStorage.setItem(KEY,JSON.stringify(snapshot()));}catch(e){}}
  function schedule(){clearTimeout(timer);timer=setTimeout(save,1500);}
  document.addEventListener('input',function(e){if(e.target&&(e.target.closest&&e.target.closest('#artForm'))) schedule();});

  // Offer to restore a newer local copy.
  try{
    var raw=localStorage.getItem(KEY);
    if(raw){
      var d=JSON.parse(raw);
      if(d&&d.ts&&(d.ts/1000)>serverTs+2) showRestore(d);
    }
  }catch(e){}

  function showRestore(d){
    var bar=document.createElement('div');
    bar.style.cssText='background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:13px;color:#92400e;display:flex;align-items:center;gap:10px;flex-wrap:wrap';
    bar.innerHTML='<i class="fa-solid fa-floppy-disk"></i> You have unsaved changes from a previous session. <button type="button" id="draftRestore" class="btn btn-ghost btn-sm" style="cursor:pointer">Restore</button> <button type="button" id="draftDiscard" class="btn btn-ghost btn-sm" style="cursor:pointer">Discard</button>';
    form.parentNode.insertBefore(bar,form);
    document.getElementById('draftRestore').onclick=function(){
      var f=fields();
      for(var k in f){if(f[k]&&d[k]!=null)f[k].value=d[k];}
      if(rte&&d.body!=null) rte.innerHTML=d.body;
      if(typeof tags!=='undefined'&&typeof renderTags==='function'&&d.tags!=null){
        tags=d.tags.split(',').map(function(x){return x.trim();}).filter(Boolean); renderTags();
      }
      if(typeof updateCount==='function') updateCount();
      if(window.adtSeoRefresh) window.adtSeoRefresh();
      bar.remove();
    };
    document.getElementById('draftDiscard').onclick=function(){try{localStorage.removeItem(KEY);}catch(e){}bar.remove();};
  }

  // Clear on submit (success redirects; a validation error repopulates via old()).
  if(form) form.addEventListener('submit',function(){try{localStorage.removeItem(KEY);}catch(e){}});

  // Ctrl/Cmd+S saves a draft.
  document.addEventListener('keydown',function(e){
    if((e.ctrlKey||e.metaKey)&&(e.key==='s'||e.key==='S')){
      e.preventDefault();
      var b=document.querySelector('button[name=status_override][value=draft]'); if(b) b.click();
    }
  });
})();
</script>
@endpush
