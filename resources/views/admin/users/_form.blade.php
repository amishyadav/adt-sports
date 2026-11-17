{{--
  Fields shared by the Add User and Edit User pages.

  Expects:
    $roles          roles from the Roles module
    $user           the account being edited, or null when adding
    $currentRoleId  that account's role id, or null
--}}
@php
  $isEdit  = isset($user) && $user !== null && $user->exists;
  $roleSel = (int) old('role_id', $currentRoleId ?? 0);
  $status  = old('status', $isEdit ? $user->status : \App\Models\User::STATUS_ACTIVE);
  $gender  = old('gender', $isEdit ? $user->gender : '');
  $photo   = $isEdit ? $user->avatar : null;
@endphp

<div class="split-grid">

  {{-- Left: who they are --}}
  <div>
    <div class="panel-card">
      <h4>Details</h4>

      <div class="half-grid">
        <div class="field">
          <label>First Name *</label>
          <input type="text" name="first_name" required maxlength="100" autocomplete="given-name"
                 placeholder="e.g. Aditya"
                 value="{{ old('first_name', $isEdit ? $user->first_name : '') }}">
        </div>
        <div class="field">
          <label>Last Name *</label>
          <input type="text" name="last_name" required maxlength="100" autocomplete="family-name"
                 placeholder="e.g. Pandit"
                 value="{{ old('last_name', $isEdit ? $user->last_name : '') }}">
        </div>
      </div>

      <div class="field">
        <label>Email Address *</label>
        <input type="email" name="email" required maxlength="255" autocomplete="email"
               placeholder="name@example.com"
               value="{{ old('email', $isEdit ? $user->email : '') }}">
        <div class="field-hint">Used to sign in, so it has to be unique.</div>
      </div>

      <div class="half-grid">
        <div class="field">
          <label>Phone</label>
          <input type="tel" name="phone" maxlength="30" autocomplete="tel"
                 placeholder="e.g. +91 98765 43210"
                 value="{{ old('phone', $isEdit ? $user->phone : '') }}">
        </div>
        <div class="field">
          <label>Gender</label>
          <select name="gender">
            <option value="">Not specified</option>
            @foreach(\App\Models\User::GENDERS as $value => $label)
              <option value="{{ $value }}" {{ $gender === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>
      </div>
    </div>

    <div class="panel-card">
      <h4>{{ $isEdit ? 'Change Password' : 'Password' }}</h4>

      @if($isEdit)
        <div class="field-hint" style="margin-bottom:12px">
          Leave both boxes empty to keep the current password.
        </div>
      @endif

      <div class="half-grid">
        <div class="field">
          <label>Password {{ $isEdit ? '' : '*' }}</label>
          <input type="password" name="password" autocomplete="new-password"
                 {{ $isEdit ? '' : 'required' }} placeholder="At least 8 characters">
        </div>
        <div class="field">
          <label>Re-enter Password {{ $isEdit ? '' : '*' }}</label>
          <input type="password" name="password_confirmation" autocomplete="new-password"
                 {{ $isEdit ? '' : 'required' }} placeholder="Type it again">
        </div>
      </div>
      <div class="field-hint">Both boxes must match. You are setting this password, so pass it on securely.</div>
    </div>
  </div>

  {{-- Right: photo and access --}}
  <div>
    <div class="panel-card">
      <h4>Photo</h4>
      <div style="display:flex;align-items:center;gap:12px">
        <div class="photo-drop" onclick="document.getElementById('userPhoto').click()" title="Choose an image">
          <img id="userPhotoPreview" src="{{ $photo ?: '' }}" alt=""
               style="{{ $photo ? '' : 'display:none' }}">
          <i class="fa-solid fa-user" id="userPhotoIcon" style="{{ $photo ? 'display:none' : '' }}"></i>
          <span class="photo-pencil"><i class="fa-solid fa-pen"></i></span>
        </div>
        <div>
          <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('userPhoto').click()">Choose image</button>
          <button type="button" class="btn btn-ghost btn-sm" id="userPhotoClear" onclick="clearUserPhoto()"
                  style="{{ $photo ? '' : 'display:none' }}">Remove</button>
          <div class="field-hint">JPG, PNG or WebP. Max 2 MB.</div>
        </div>
      </div>
      <input type="hidden" name="remove_photo" id="userRemovePhoto" value="0">
      <input type="file" name="photo" id="userPhoto" accept="image/jpeg,image/png,image/webp"
             onchange="previewUserPhoto(this)" style="display:none">
      <div class="field-hint" style="margin-top:8px">Optional — initials are shown when there is no photo.</div>
    </div>

    <div class="panel-card">
      <h4>Access</h4>

      @if($roles->isEmpty())
        <div class="field-hint" style="margin-bottom:12px;color:#FCD34D">
          <i class="fa-solid fa-triangle-exclamation"></i>
          There are no roles yet. Add one on the
          <a href="{{ route('admin.roles.index') }}" style="color:var(--brand)">Roles</a> screen first.
        </div>
      @endif

      <div class="field">
        <label>Role *</label>
        <select name="role_id" required {{ $roles->isEmpty() ? 'disabled' : '' }}>
          <option value="">— Select a role —</option>
          @foreach($roles as $r)
            <option value="{{ $r->id }}" {{ $roleSel === $r->id ? 'selected' : '' }}>
              {{ $r->name }}@unless(\App\Models\User::roleGrantsPanelAccess($r->name)) (no panel access)@endunless
            </option>
          @endforeach
        </select>
        <div class="field-hint">
          Roles are managed on the
          <a href="{{ route('admin.roles.index') }}" style="color:var(--brand)">Roles</a> screen.
          Only
          <strong>{{ collect(\App\Models\User::PANEL_ROLES)->map(fn ($r) => ucfirst($r))->join(' and ') }}</strong>
          currently open the admin panel — any other role saves fine, but that account cannot sign in yet.
        </div>
      </div>

      <div class="field" style="margin-bottom:0">
        <label>Status *</label>
        <select name="status" required>
          <option value="{{ \App\Models\User::STATUS_ACTIVE }}"   {{ $status === \App\Models\User::STATUS_ACTIVE ? 'selected' : '' }}>Active</option>
          <option value="{{ \App\Models\User::STATUS_INACTIVE }}" {{ $status === \App\Models\User::STATUS_INACTIVE ? 'selected' : '' }}>Inactive</option>
        </select>
        <div class="field-hint">An inactive account cannot sign in, and is signed out if already logged in.</div>
      </div>
    </div>
  </div>

</div>

@push('styles')
<style>
  .photo-drop{position:relative;width:76px;height:76px;flex:0 0 auto;display:flex;align-items:center;justify-content:center;background:var(--card);border:1px solid var(--border);border-radius:50%;cursor:pointer;color:var(--ink3);font-size:26px;overflow:hidden;transition:border-color .12s}
  .photo-drop:hover{border-color:var(--brand)}
  .photo-drop img{width:100%;height:100%;object-fit:cover}
  .photo-pencil{position:absolute;bottom:0;right:0;width:24px;height:24px;display:flex;align-items:center;justify-content:center;background:var(--panel);border:1px solid var(--border);border-radius:50%;font-size:10px;color:var(--ink2)}
</style>
@endpush

@push('scripts')
<script>
function setUserPhotoPreview(url) {
  var img  = document.getElementById('userPhotoPreview');
  var icon = document.getElementById('userPhotoIcon');
  var clr  = document.getElementById('userPhotoClear');

  if (url) {
    img.src = url; img.style.display = 'block'; icon.style.display = 'none'; clr.style.display = 'inline-flex';
  } else {
    // removeAttribute, not src = '': an empty src resolves against the page URL
    // and the browser goes off and requests the document again.
    img.removeAttribute('src'); img.style.display = 'none'; icon.style.display = 'block'; clr.style.display = 'none';
  }
}

/*
 * Read the chosen file as a data: URL rather than an object URL.
 *
 * URL.createObjectURL returns a blob: address and the admin
 * Content-Security-Policy allows images from 'self', data: and https: only, so
 * the browser refuses to load it and the frame shows broken. data: is already
 * permitted and needs no widening of the policy.
 */
function previewUserPhoto(input) {
  var file = input.files && input.files[0];
  if (!file) { return; }

  document.getElementById('userRemovePhoto').value = '0';

  var reader = new FileReader();
  reader.onload = function () { setUserPhotoPreview(reader.result); };
  reader.onerror = function () {
    // Keep whatever was showing. The upload is unaffected — the file is still
    // submitted and still validated server side.
    console.warn('[users] Could not read the chosen file to preview it.');
  };
  reader.readAsDataURL(file);
}

function clearUserPhoto() {
  document.getElementById('userPhoto').value = '';
  document.getElementById('userRemovePhoto').value = '1';
  setUserPhotoPreview('');
}
</script>
@endpush
