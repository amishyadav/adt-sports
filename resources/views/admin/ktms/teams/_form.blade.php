{{--
  Fields shared by the new and edit squad pages.

  @param $team        App\Models\Ktms\Team (unsaved when creating)
  @param $tournament  the tournament it belongs to
--}}
@php $logo = $team->exists ? $team->logo_url : null; @endphp

<div class="split-grid">
  <div class="panel-card">
    <h4>Squad</h4>

    <div class="field">
      <label>Name *</label>
      <input type="text" name="name" required maxlength="255"
             placeholder="e.g. Mumbai Maulers" value="{{ old('name', $team->name) }}">
      <div class="field-hint">Has to be unique within {{ $tournament->name }}.</div>
    </div>

    <div class="field">
      <label>Short Name</label>
      <input type="text" name="short_name" maxlength="10" placeholder="e.g. MUM"
             value="{{ old('short_name', $team->short_name) }}">
      <div class="field-hint">Used on scoreboards and for the crest initials.</div>
    </div>

    <div class="half-grid">
      <div class="field">
        <label>Coach</label>
        <input type="text" name="coach_name" maxlength="255"
               value="{{ old('coach_name', $team->coach_name) }}">
      </div>
      <div class="field">
        <label>City</label>
        <input type="text" name="city" maxlength="255" value="{{ old('city', $team->city) }}">
      </div>
    </div>

    <div class="half-grid" style="margin-bottom:0">
      <div class="field" style="margin-bottom:0">
        <label>Primary Colour</label>
        <div style="display:flex;align-items:center;gap:10px">
          <input type="color" name="primary_color" value="{{ old('primary_color', $team->primary_color) }}">
          <span class="field-hint" style="margin:0">Behind the crest initials</span>
        </div>
      </div>
      <div class="field" style="margin-bottom:0">
        <label>Secondary Colour</label>
        <div style="display:flex;align-items:center;gap:10px">
          <input type="color" name="secondary_color" value="{{ old('secondary_color', $team->secondary_color) }}">
        </div>
      </div>
    </div>
  </div>

  <div class="panel-card">
    <h4>Crest</h4>
    <div style="display:flex;align-items:center;gap:12px">
      <div class="ktms-logo-drop" onclick="document.getElementById('ktmsLogo').click()" title="Choose an image">
        <img id="ktmsLogoPreview" src="{{ $logo ?: '' }}" alt="" style="{{ $logo ? '' : 'display:none' }}">
        <i class="fa-solid fa-shield-halved" id="ktmsLogoIcon" style="{{ $logo ? 'display:none' : '' }}"></i>
        <span class="ktms-logo-pencil"><i class="fa-solid fa-pen"></i></span>
      </div>
      <div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('ktmsLogo').click()">Choose image</button>
        <button type="button" class="btn btn-ghost btn-sm" id="ktmsLogoClear" onclick="clearKtmsLogo()"
                style="{{ $logo ? '' : 'display:none' }}">Remove</button>
        <div class="field-hint">JPG, PNG or WebP. Max 2 MB.</div>
      </div>
    </div>
    <input type="hidden" name="remove_logo" id="ktmsRemoveLogo" value="0">
    <input type="file" name="logo" id="ktmsLogo" accept="image/jpeg,image/png,image/webp"
           onchange="previewKtmsLogo(this)" style="display:none">
    <div class="field-hint" style="margin-top:8px">
      Optional — the squad's initials on its primary colour are used when there is no crest.
    </div>
  </div>
</div>

@push('styles')
<style>
  .ktms-logo-drop{position:relative;width:76px;height:76px;flex:0 0 auto;display:flex;align-items:center;justify-content:center;background:var(--card);border:1px solid var(--border);border-radius:50%;cursor:pointer;color:var(--ink3);font-size:26px;overflow:hidden;transition:border-color .12s}
  .ktms-logo-drop:hover{border-color:var(--brand)}
  .ktms-logo-drop img{width:100%;height:100%;object-fit:cover}
  .ktms-logo-pencil{position:absolute;bottom:0;right:0;width:24px;height:24px;display:flex;align-items:center;justify-content:center;background:var(--panel);border:1px solid var(--border);border-radius:50%;font-size:10px;color:var(--ink2)}
</style>
@endpush

@push('scripts')
<script>
function setKtmsLogoPreview(url) {
  var img = document.getElementById('ktmsLogoPreview');
  var icon = document.getElementById('ktmsLogoIcon');
  var clr = document.getElementById('ktmsLogoClear');

  if (url) {
    img.src = url; img.style.display = 'block'; icon.style.display = 'none'; clr.style.display = 'inline-flex';
  } else {
    // removeAttribute, not src = '': an empty src resolves against the page URL
    // and the browser requests the document again.
    img.removeAttribute('src'); img.style.display = 'none'; icon.style.display = 'block'; clr.style.display = 'none';
  }
}

/*
 * Read the file as a data: URL, not an object URL. createObjectURL yields a
 * blob: address and this panel's Content-Security-Policy allows images from
 * 'self', data: and https: only, so a blob would show as broken.
 */
function previewKtmsLogo(input) {
  var file = input.files && input.files[0];
  if (!file) { return; }

  document.getElementById('ktmsRemoveLogo').value = '0';

  var reader = new FileReader();
  reader.onload = function () { setKtmsLogoPreview(reader.result); };
  reader.onerror = function () {
    // Keep whatever was showing; the upload itself is unaffected.
    console.warn('[ktms] Could not read the chosen file to preview it.');
  };
  reader.readAsDataURL(file);
}

function clearKtmsLogo() {
  document.getElementById('ktmsLogo').value = '';
  document.getElementById('ktmsRemoveLogo').value = '1';
  setKtmsLogoPreview('');
}
</script>
@endpush
