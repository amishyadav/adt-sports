@extends('layouts.admin')
@section('title','Site Settings')
@section('content')

<div class="page-hd">
  <div><h1>Settings</h1><div class="page-hd-sub">Configure your site</div></div>
</div>

<div class="tabs">
  @if(auth()->user()->isAdmin())
  <button class="tab-btn active" onclick="switchTab('general',this)"><i class="fa-solid fa-sliders"></i> General</button>
  <button class="tab-btn" onclick="switchTab('social',this)"><i class="fa-solid fa-share-nodes"></i> Social Media</button>
  @endif
  <button class="tab-btn {{ auth()->user()->isAdmin() ? '' : 'active' }}" onclick="switchTab('profile',this)" id="profileTabBtn"><i class="fa-solid fa-circle-user"></i> My Profile</button>
</div>

@if(auth()->user()->isAdmin())
{{-- GENERAL TAB --}}
<div id="tab-general" class="tab-pane active">
  <form action="{{ route('admin.settings.update') }}" method="POST">
    @csrf @method('PUT')

    <div class="settings-section">
      <div class="ss-hd"><i class="fa-solid fa-circle-info"></i> Site Information</div>
      <div class="ss-body">
        <div class="settings-row">
          <div><div class="sr-label">Site Name</div><div class="sr-desc">Shown in browser tab and navbar</div></div>
          <input type="text" name="site_name" class="field" style="width:100%;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:10px 13px;font-size:14px;color:var(--ink);outline:none"
            value="{{ $settings['site_name'] ?? 'ADT Sports' }}" placeholder="ADT Sports">
        </div>
        <div class="settings-row">
          <div><div class="sr-label">Tagline</div><div class="sr-desc">Subtitle shown on the site</div></div>
          <input type="text" name="site_tagline"
            style="width:100%;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:10px 13px;font-size:14px;color:var(--ink);outline:none"
            value="{{ $settings['site_tagline'] ?? '' }}" placeholder="India's #1 Kabaddi Media Platform">
        </div>
        <div class="settings-row">
          <div><div class="sr-label">Contact Email</div></div>
          <input type="email" name="site_email"
            style="width:100%;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:10px 13px;font-size:14px;color:var(--ink);outline:none"
            value="{{ $settings['site_email'] ?? '' }}" placeholder="admin@adtsports.com">
        </div>
        <div class="settings-row">
          <div><div class="sr-label">Phone Number</div></div>
          <input type="text" name="site_phone"
            style="width:100%;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:10px 13px;font-size:14px;color:var(--ink);outline:none"
            value="{{ $settings['site_phone'] ?? '' }}" placeholder="+91 9979269732">
        </div>
        <div class="settings-row">
          <div><div class="sr-label">WhatsApp Number</div><div class="sr-desc">Digits only — shown as a wa.me link</div></div>
          <input type="text" name="site_whatsapp"
            style="width:100%;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:10px 13px;font-size:14px;color:var(--ink);outline:none"
            value="{{ $settings['site_whatsapp'] ?? '' }}" placeholder="919979269732">
        </div>
        <div class="settings-row">
          <div><div class="sr-label">Address</div><div class="sr-desc">Shown in the footer contact block</div></div>
          <input type="text" name="site_address"
            style="width:100%;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:10px 13px;font-size:14px;color:var(--ink);outline:none"
            value="{{ $settings['site_address'] ?? '' }}" placeholder="Jaipur, Rajasthan, India">
        </div>
        <div class="settings-row">
          <div><div class="sr-label">Footer Tagline</div><div class="sr-desc">Italic line shown in footer</div></div>
          <input type="text" name="footer_tagline"
            style="width:100%;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:10px 13px;font-size:14px;color:var(--ink);outline:none"
            value="{{ $settings['footer_tagline'] ?? '' }}">
        </div>
      </div>
    </div>

    <div class="settings-section">
      <div class="ss-hd"><i class="fa-solid fa-bullhorn"></i> Breaking News Ticker</div>
      <div class="ss-body">
        <div class="settings-row">
          <div>
            <div class="sr-label">Ticker Text</div>
            <div class="sr-desc">Scrolls across the top. Separate stories with " | "</div>
          </div>
          <textarea name="breaking_ticker" rows="3"
            style="width:100%;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:10px 13px;font-size:13px;color:var(--ink);outline:none;resize:vertical;font-family:'Inter',sans-serif;line-height:1.6"
            placeholder="Breaking story one | Breaking story two | Another update">{{ $settings['breaking_ticker'] ?? '' }}</textarea>
        </div>
      </div>
    </div>

    <div class="settings-section">
      <div class="ss-hd"><i class="fa-solid fa-table-cells-large"></i> Article Display</div>
      <div class="ss-body">
        <div class="settings-row">
          <div><div class="sr-label">Articles Per Page</div><div class="sr-desc">Homepage pagination count</div></div>
          <input type="number" name="articles_per_page" min="5" max="50"
            style="width:100px;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:10px 13px;font-size:14px;color:var(--ink);outline:none"
            value="{{ $settings['articles_per_page'] ?? 10 }}">
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-floppy-disk"></i> Save Settings</button>
  </form>
</div>

{{-- SOCIAL TAB --}}
<div id="tab-social" class="tab-pane">
  <form action="{{ route('admin.settings.update') }}" method="POST">
    @csrf @method('PUT')
    <div class="settings-section">
      <div class="ss-hd"><i class="fa-solid fa-share-nodes"></i> Social Media Links</div>
      <div class="ss-body">
        @foreach([
          'facebook_url'  => ['fa-brands fa-facebook', 'Facebook URL'],
          'instagram_url' => ['fa-brands fa-instagram', 'Instagram URL'],
          'youtube_url'   => ['fa-brands fa-youtube', 'YouTube URL'],
          'twitter_url'   => ['fa-brands fa-x-twitter', 'Twitter / X URL'],
        ] as $key => [$icon, $label])
        <div class="settings-row">
          <div><div class="sr-label"><i class="{{ $icon }}"></i> {{ $label }}</div></div>
          <input type="url" name="{{ $key }}"
            style="width:100%;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:10px 13px;font-size:14px;color:var(--ink);outline:none"
            value="{{ $settings[$key] ?? '' }}" placeholder="https://...">
        </div>
        @endforeach
      </div>
    </div>
    <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-floppy-disk"></i> Save Social Links</button>
  </form>
</div>
@endif

{{-- PROFILE TAB --}}
<div id="tab-profile" class="tab-pane {{ auth()->user()->isAdmin() ? '' : 'active' }}">
  <form action="{{ route('admin.profile.update') }}" method="POST">
    @csrf @method('PUT')
    <div class="settings-section">
      <div class="ss-hd"><i class="fa-solid fa-circle-user"></i> My Account — {{ auth()->user()->name }}</div>
      <div class="ss-body">
        <div class="settings-row">
          <div><div class="sr-label">Display Name</div></div>
          <input type="text" name="name"
            style="width:100%;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:10px 13px;font-size:14px;color:var(--ink);outline:none"
            value="{{ auth()->user()->name }}" required>
        </div>
        <div class="settings-row">
          <div><div class="sr-label">Email Address</div></div>
          <input type="email" name="email"
            style="width:100%;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:10px 13px;font-size:14px;color:var(--ink);outline:none"
            value="{{ auth()->user()->email }}" required>
        </div>
        <div class="settings-row">
          <div><div class="sr-label">New Password</div><div class="sr-desc">Leave blank to keep current password</div></div>
          <input type="password" name="password"
            style="width:100%;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:10px 13px;font-size:14px;color:var(--ink);outline:none"
            placeholder="Min 8 characters…">
        </div>
        <div class="settings-row">
          <div><div class="sr-label">Role</div></div>
          <div style="padding-top:10px">
            <span class="badge badge-{{ auth()->user()->role }}">{{ auth()->user()->role }}</span>
          </div>
        </div>
      </div>
    </div>
    <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-check"></i> Update Profile</button>
  </form>
</div>

@endsection

@push('scripts')
<script>
function activateTab(id){
  if(!document.getElementById('tab-'+id)) return;
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('active'));
  document.getElementById('tab-'+id).classList.add('active');
  var btn = document.querySelector('.tab-btn[onclick*="\''+id+'\'"]');
  if(btn) btn.classList.add('active');
}
function switchTab(id,btn){
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('active'));
  document.getElementById('tab-'+id).classList.add('active');
}
// Open the tab named in the URL hash — on load AND when the hash changes while
// already on this page (e.g. clicking "My Profile" in the sidebar from here).
function tabFromHash(){
  var h = (window.location.hash || '').replace('#','');
  if(['general','social','profile'].indexOf(h) !== -1) activateTab(h);
}
tabFromHash();
window.addEventListener('hashchange', tabFromHash);
</script>
@endpush
