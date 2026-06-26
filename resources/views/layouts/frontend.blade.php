<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
{{-- Refreshed per-request even on cached pages via responsecache's CsrfTokenReplacer --}}
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#ffffff" id="themeColorMeta">
{{-- Apply theme before first paint (saved pref, else the OS setting) to avoid a flash --}}
<script>(function(){var s=localStorage.getItem('adt-theme');var d=s?s==='dark':(window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.setAttribute('data-theme',d?'dark':'light');var m=document.getElementById('themeColorMeta');if(m)m.content=d?'#140e0a':'#ffffff';})();</script>
<title>@yield('title', ($settings['site_name'] ?? 'ADT Sports'))</title>
<meta name="description" content="@yield('meta_desc', $settings['site_description'] ?? "India's #1 Kabaddi media platform.")">

{{-- ── SEO METADATA ───────────────────────────────────────── --}}
@php
    $seoSiteName  = $settings['site_name'] ?? 'ADT Sports';
    $seoTitle     = trim($__env->yieldContent('title', $seoSiteName));
    $seoDesc      = trim($__env->yieldContent('meta_desc', $settings['site_description'] ?? "India's #1 Kabaddi media platform."));
    // Each list view already sets a page-aware @section('canonical'); article
    // pages set their own. Fall back to the current URL otherwise.
    $seoCanonical = trim($__env->yieldContent('canonical')) ?: url()->current();
    $seoRobots    = trim($__env->yieldContent('robots', 'index, follow'));
    $seoType      = trim($__env->yieldContent('og_type', 'website'));
    $seoImage     = trim($__env->yieldContent('og_image')) ?: '/uploads/logo.png';
    if (! \Illuminate\Support\Str::startsWith($seoImage, ['http://', 'https://'])) {
        $seoImage = url($seoImage);
    }
    $seoSocials = array_values(array_filter([
        $settings['facebook_url']  ?? null,
        $settings['instagram_url'] ?? null,
        $settings['youtube_url']   ?? null,
        $settings['twitter_url']   ?? null,
    ]));
@endphp
<link rel="canonical" href="{{ $seoCanonical }}">
<meta name="robots" content="{{ $seoRobots }}">
<link rel="alternate" type="application/rss+xml" title="{{ $seoSiteName }} RSS" href="{{ url('/feed.xml') }}">
@stack('head_links')

{{-- Open Graph --}}
<meta property="og:type" content="{{ $seoType }}">
<meta property="og:site_name" content="{{ $seoSiteName }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDesc }}">
<meta property="og:url" content="{{ $seoCanonical }}">
<meta property="og:image" content="{{ $seoImage }}">
@stack('og_meta')

{{-- Twitter Card --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDesc }}">
<meta name="twitter:image" content="{{ $seoImage }}">
@if(!empty($settings['twitter_url']))
<meta name="twitter:site" content="{{ '@' . \Illuminate\Support\Str::afterLast(rtrim($settings['twitter_url'], '/'), '/') }}">
@endif

{{-- Organization + WebSite structured data (site-wide) --}}
<script type="application/ld+json">
{!! json_encode([
    '@context'    => 'https://schema.org',
    '@type'       => 'Organization',
    'name'        => $seoSiteName,
    'url'         => url('/'),
    'logo'        => url('/uploads/logo.png'),
    'description' => $settings['site_description'] ?? "India's #1 Kabaddi media platform.",
] + (count($seoSocials) ? ['sameAs' => $seoSocials] : []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}
</script>
<script type="application/ld+json">
{!! json_encode([
    '@context'        => 'https://schema.org',
    '@type'           => 'WebSite',
    'name'            => $seoSiteName,
    'url'             => url('/'),
    'potentialAction' => [
        '@type'       => 'SearchAction',
        'target'      => url('/search') . '?q={search_term_string}',
        'query-input' => 'required name=search_term_string',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}
</script>
@stack('schema')

  <link rel="shortcut icon" href="/uploads/logo.png" data-inertia="favicon-shortcut">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="apple-touch-icon" href="/icons/icon-192.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="ADT Sports">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,700;0,800;1,600&display=swap" rel="stylesheet">
{{-- Font Awesome is decorative-only — load it non-render-blocking to protect LCP --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" media="print" onload="this.media='all'" />
<noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" /></noscript>
@vite(['resources/css/app.css'])
@stack('styles')
</head>
<body>
<a href="#main" class="skip-link">Skip to content</a>
<div id="readBar"></div>

<div class="search-overlay" id="srchOverlay">
  <div class="search-box">
    <div class="search-row">
      <span class="s-icon">🔍</span>
      <form action="{{ route('search') }}" method="GET" style="flex:1;display:flex">
        <input type="text" name="q" placeholder="Search Kabaddi news, players, leagues…" autofocus
          value="{{ request('q') }}" style="flex:1">
      </form>
      <button class="s-close" onclick="document.getElementById('srchOverlay').classList.remove('open')">✕</button>
    </div>
    <p class="search-hint">Press ESC to close · Enter to search</p>
  </div>
</div>

{{-- Subscribe dialog — opened from any "Subscribe" button --}}
<div class="subscribe-modal" id="subscribeModal" role="dialog" aria-modal="true" aria-label="Subscribe to the newsletter">
  <div class="subscribe-card">
    <button class="sm-close" type="button" aria-label="Close" onclick="closeSubscribe()">✕</button>
    <h3 class="sm-title">Join the Daily Digest</h3>
    <p class="sm-desc">Top Kabaddi stories straight to your inbox — free. We'll email a link to confirm; unsubscribe anytime. We send at most {{ \App\Support\SubscribeThrottle::MAX_PER_DAY }} emails a day.</p>
    <div class="nl-note" role="status" style="display:none;margin-bottom:12px;padding:10px 12px;border-radius:8px;font-size:13px;line-height:1.45"></div>
    <input type="text" class="nl-hp" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">
    <input type="text" class="nl-input nl-name" placeholder="Your name" maxlength="80" autocomplete="name">
    <input type="email" class="nl-input nl-email" placeholder="your@email.com" autocomplete="email">
    <button class="nl-btn" type="button" onclick="adtSubscribe(this, 'modal')">Subscribe →</button>
  </div>
</div>

<div class="mobile-overlay" id="mobileOverlay"></div>
<div class="mobile-nav" id="mobileNav">
  <a href="{{ route('home') }}"><i class="fa-solid fa-house"></i> Home</a>
  @foreach($categories as $cat)
    <a href="{{ route('category', $cat->slug) }}"><i class="fa-solid {{ $cat->display_icon }}"></i> {{ $cat->name }}</a>
  @endforeach
  <a href="{{ route('search') }}"><i class="fa-solid fa-magnifying-glass"></i> Search</a>
</div>

{{-- TICKER --}}
<div class="ticker-strip">
  <div class="ticker-container">
    <span class="ticker-label">Live</span>

    @php
      $ticker = $settings['breaking_ticker'] ?? 'ADT Sports — India\'s #1 Kabaddi Platform';
      $items = array_filter(array_map('trim', explode('|', $ticker)));
      $doubled = array_merge($items, $items);
    @endphp

    <div class="ticker-wrapper">
      <div class="ticker-inner">
        @foreach($doubled as $t)
          <span class="ticker-item">{{ $t }}</span>
          <span class="ticker-sep">◆</span>
        @endforeach
      </div>
    </div>
  </div>
</div>

{{-- NAV --}}
<nav id="mainNav" aria-label="Primary">
  <div class="nav-wrap">
    <a href="{{ route('home') }}" class="logo">
      <div class="logo-img"><img src="/uploads/logo.png" onerror="this.style.display='none'" alt="ADT"></div>
      <div class="logo-wordmark"><span class="brand">ADT</span> Sports</div>
    </a>
    <div class="nav-links">
      <a href="{{ route('home') }}" class="{{ request()->routeIs('home') && !request('category') ? 'active':'' }}">Home</a>
      @foreach($categories->take(5) as $cat)
        <a href="{{ route('category',$cat->slug) }}"
          class="{{ request()->route('slug')===$cat->slug ? 'active':'' }}">{{ $cat->name }}</a>
      @endforeach
      @if($categories->count() > 5)
      <div class="nav-drop">
        <button type="button" class="nav-drop-toggle" aria-haspopup="true" aria-expanded="false">More <span style="font-size:9px;opacity:.5">▾</span></button>
        <div class="drop-menu">
          @foreach($categories->skip(5) as $cat)
            <a href="{{ route('category',$cat->slug) }}">{{ $cat->name }}</a>
          @endforeach
        </div>
      </div>
      @endif
    </div>
    <div class="nav-right">
      <button class="icon-btn" aria-label="Search" onclick="document.getElementById('srchOverlay').classList.add('open')"><i class="fa-solid fa-magnifying-glass"></i></button>
      <button class="icon-btn" id="themeBtn" aria-label="Toggle dark mode" aria-pressed="false"><i class="fa-solid fa-moon"></i></button>
      <a href="{{ route('home') }}" class="btn-sub" id="navSubscribe">Subscribe</a>
      <button class="hamburger" id="hamburger" aria-label="Open menu" aria-expanded="false" aria-controls="mobileNav"><span></span><span></span><span></span></button>
    </div>
  </div>
</nav>

{{-- Mobile bottom navigation (thumb-reachable; hidden on desktop) --}}
<nav class="bottom-nav" aria-label="Mobile primary">
  <a href="{{ route('home') }}" class="bn-item {{ request()->routeIs('home') ? 'active' : '' }}">
    <i class="fa-solid fa-house"></i><span>Home</span>
  </a>
  <button type="button" class="bn-item" id="bnSearch" aria-label="Search">
    <i class="fa-solid fa-magnifying-glass"></i><span>Search</span>
  </button>
  <button type="button" class="bn-item" id="bnTopics" aria-label="Browse topics">
    <i class="fa-solid fa-layer-group"></i><span>Topics</span>
  </button>
  <button type="button" class="bn-item" id="bnSubscribe" aria-label="Subscribe">
    <i class="fa-regular fa-envelope"></i><span>Subscribe</span>
  </button>
</nav>

<main id="main">
@yield('content')
</main>

{{-- FOOTER --}}
<footer>
  <div class="footer-grid">
    <div>
      <div class="ft-logo">
        <div class="fl-img"><img src="/uploads/logo.png" onerror="this.style.display='none'" alt="ADT"></div>
        <span><em>ADT</em> Sports</span>
      </div>
      <p class="ft-desc">{{ $settings['site_description'] ?? "India's #1 Kabaddi-focused digital media brand." }}</p>
      <div class="ft-socials">
        @if(!empty($settings['facebook_url']))  <a href="{{ $settings['facebook_url'] }}"  target="_blank" class="ft-soc icon-facebook"><i class="fa-brands fa-facebook"></i></a> @endif
        @if(!empty($settings['instagram_url'])) <a href="{{ $settings['instagram_url'] }}" target="_blank" class="ft-soc icon-instagram"><i class="fa-brands fa-instagram"></i></a> @endif
        @if(!empty($settings['youtube_url']))   <a href="{{ $settings['youtube_url'] }}"   target="_blank" class="ft-soc icon-youtube"><i class="fa-brands fa-youtube"></i></a>  @endif
        @if(!empty($settings['twitter_url']))   <a href="{{ $settings['twitter_url'] }}"   target="_blank" class="ft-soc icon-twitter"><i class="fa-brands fa-twitter"></i></a> @endif
      </div>
    </div>
    <div class="ft-col">
      <h4>Coverage</h4>
      <ul>
        @foreach($categories->take(5) as $cat)
          <li><a href="{{ route('category',$cat->slug) }}">{{ $cat->name }}</a></li>
        @endforeach
      </ul>
    </div>
    <div class="ft-col">
      <h4>More</h4>
      <ul>
        @foreach($categories->skip(5)->take(5) as $cat)
          <li><a href="{{ route('category',$cat->slug) }}">{{ $cat->name }}</a></li>
        @endforeach
        <li><a href="{{ route('search') }}">Search</a></li>
      </ul>
    </div>
    <div class="ft-col">
      <h4>ADT Sports</h4>
      <ul>
        @if(!empty($settings['site_email'])) <li><a href="mailto:{{ $settings['site_email'] }}">{{ $settings['site_email'] }}</a></li> @endif
        @if(!empty($settings['site_phone'])) <li><a href="tel:{{ $settings['site_phone'] }}">{{ $settings['site_phone'] }}</a></li> @endif
        @if(!empty($settings['site_whatsapp'])) <li><a href="https://wa.me/{{ preg_replace('/\D+/', '', $settings['site_whatsapp']) }}" target="_blank" rel="noopener">WhatsApp</a></li> @endif
        @if(!empty($settings['site_address'])) <li class="ft-address">{{ $settings['site_address'] }}</li> @endif
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <span>© {{ date('Y') }} {{ $settings['site_name'] ?? 'ADT Sports' }}. All rights reserved.</span>
    <span class="footer-tagline">"{{ $settings['footer_tagline'] ?? 'ADT Sports is not covering Kabaddi. It is building its future.' }}"</span>
  </div>
</footer>

<script>
let dark = document.documentElement.getAttribute('data-theme')==='dark';
const themeBtn = document.getElementById('themeBtn');
function setTheme(d){dark=d;document.documentElement.setAttribute('data-theme',d?'dark':'light');themeBtn.innerHTML=d?'<i class="fa-solid fa-sun"></i>':'<i class="fa-solid fa-moon"></i>';themeBtn.setAttribute('aria-pressed',d?'true':'false');var m=document.getElementById('themeColorMeta');if(m)m.content=d?'#140e0a':'#ffffff';localStorage.setItem('adt-theme',d?'dark':'light')}
themeBtn.onclick=()=>setTheme(!dark);
setTheme(dark); // sync icon/meta with the theme already applied in <head>
const hamburger=document.getElementById('hamburger');const mobileNav=document.getElementById('mobileNav');const mobileOverlay=document.getElementById('mobileOverlay');
function setMenu(open){mobileNav.classList.toggle('open',open);mobileOverlay.classList.toggle('open',open);hamburger.setAttribute('aria-expanded',open?'true':'false')}
hamburger.onclick=()=>setMenu(!mobileNav.classList.contains('open'));
mobileOverlay.onclick=()=>setMenu(false);
// "More" category dropdown: click/tap toggle (CSS hover still covers desktop).
function closeDrops(){document.querySelectorAll('.nav-drop.open').forEach(d=>{d.classList.remove('open');d.querySelector('.nav-drop-toggle')?.setAttribute('aria-expanded','false')})}
document.querySelectorAll('.nav-drop-toggle').forEach(btn=>{
  btn.addEventListener('click',e=>{e.stopPropagation();const d=btn.closest('.nav-drop');const open=d.classList.toggle('open');btn.setAttribute('aria-expanded',open?'true':'false')});
});
document.addEventListener('click',e=>{if(!e.target.closest('.nav-drop'))closeDrops()});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){document.getElementById('srchOverlay').classList.remove('open');document.getElementById('subscribeModal')?.classList.remove('open');closeDrops()}});

// "Subscribe": open the subscribe dialog from anywhere.
window.openSubscribe=function(){
  var m=document.getElementById('subscribeModal'); if(!m) return;
  var note=m.querySelector('.nl-note'); if(note){ note.style.display='none'; note.textContent=''; } // clear any prior result
  m.classList.add('open');
  var n=m.querySelector('.nl-name'); if(n) setTimeout(function(){n.focus()},80);
};
window.closeSubscribe=function(){ document.getElementById('subscribeModal')?.classList.remove('open'); };
window.goSubscribe=function(e){ if(e) e.preventDefault(); openSubscribe(); };
document.getElementById('navSubscribe')?.addEventListener('click',goSubscribe);
// Close the dialog on backdrop click.
document.getElementById('subscribeModal')?.addEventListener('click',function(e){ if(e.target===this) closeSubscribe(); });

// Mobile bottom nav
document.getElementById('bnSearch')?.addEventListener('click',function(){document.getElementById('srchOverlay').classList.add('open')});
document.getElementById('bnTopics')?.addEventListener('click',function(){setMenu(true)});
document.getElementById('bnSubscribe')?.addEventListener('click',goSubscribe);

window.addEventListener('scroll',()=>{
  document.getElementById('mainNav').classList.toggle('shadow',window.scrollY>30);
  const d=document.documentElement;document.getElementById('readBar').style.width=((window.scrollY/(d.scrollHeight-d.clientHeight))*100)+'%';
},{passive:true});

// Shared newsletter sign-up — reads name + email from the widget, posts to
// /subscribe with the CSRF token from <meta>.
window.adtSubscribe = function (btnEl, source) {
  const root = (btnEl && btnEl.closest('.widget-nl, .subscribe-card')) || document;
  const nameEl = root.querySelector('.nl-name');
  const emailEl = root.querySelector('.nl-email');
  const hpEl = root.querySelector('.nl-hp');
  const noteEl = root.querySelector('.nl-note');
  const name = nameEl ? (nameEl.value || '').trim() : '';
  const email = (emailEl ? emailEl.value : '').trim();

  // Inline feedback in the card (already subscribed, check inbox, errors) —
  // no browser alert(). Falls back to alert() if a card has no note element.
  function note(ok, msg) {
    if (!noteEl) { alert(msg); return; }
    noteEl.style.display = 'block';
    noteEl.style.background = ok ? 'rgba(22,128,60,.14)' : 'rgba(224,36,94,.12)';
    noteEl.style.border = '1px solid ' + (ok ? 'rgba(22,128,60,.4)' : 'rgba(224,36,94,.4)');
    noteEl.style.color = 'var(--ink)';
    noteEl.textContent = msg; // server message already carries its own emoji
  }

  if (!name) { note(false, 'Please enter your name.'); if (nameEl) nameEl.focus(); return; }
  if (!email || !email.includes('@')) { note(false, 'Please enter a valid email address.'); if (emailEl) emailEl.focus(); return; }

  const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const original = btnEl ? btnEl.textContent : '';
  if (btnEl) { btnEl.disabled = true; btnEl.textContent = 'Subscribing…'; }
  fetch('{{ route('subscribe') }}', {
    method: 'POST',
    headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':token},
    body: JSON.stringify({name, email, hp_url: hpEl ? hpEl.value : '', source: source || 'site'})
  })
  // Read the JSON body whatever the status, so the server's message (already
  // subscribed, rate-limited, mail failure, validation) is shown — not a blanket error.
  .then(r => r.json().then(d => ({ ok: r.ok, d })).catch(() => ({ ok: r.ok, d: {} })))
  .then(({ ok, d }) => {
    note(ok, d.message || (ok ? "You're all set!" : 'Something went wrong. Please try again.'));
    if (ok) { if (nameEl) nameEl.value = ''; if (emailEl) emailEl.value = ''; }
  })
  .catch(() => note(false, 'Something went wrong. Please try again.'))
  .finally(() => { if (btnEl) { btnEl.disabled = false; btnEl.textContent = original; } });
};
// Service worker: PWA/offline in production only. On localhost it fights
// `php artisan serve` (one request at a time → connection-refused under load),
// which makes the SW serve stale cached pages/CSS — so in dev we actively
// unregister it and purge its caches instead.
if ('serviceWorker' in navigator) {
  var isLocal = location.hostname === 'localhost' || location.hostname === '127.0.0.1';
  if (isLocal) {
    navigator.serviceWorker.getRegistrations().then(function(rs){ rs.forEach(function(r){ r.unregister(); }); });
    if (window.caches) caches.keys().then(function(ks){ ks.forEach(function(k){ caches.delete(k); }); });
  } else {
    window.addEventListener('load', function(){ navigator.serviceWorker.register('/sw.js').catch(function(){}); });
  }
}
</script>
@stack('scripts')
</body>
</html>
