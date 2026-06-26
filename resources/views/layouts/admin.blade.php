<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title','Admin') — ADT Sports</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
{{-- Font Awesome — needed for the category icon picker (and any FA icons in admin views). --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer">
<style>
:root{--bg:#0F0E0D;--panel:#161412;--card:#1C1917;--border:rgba(255,255,255,.07);--border2:rgba(255,255,255,.04);--ink:#F5F0EB;--ink2:#A8A09A;--ink3:#6B6560;--brand:#D4420A;--brand-h:#B83808;--green:#16A34A;--amber:#D97706;--red:#DC2626;--sidebar:230px;--topbar:56px}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}html{font-size:14px}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--ink);height:100vh;overflow:hidden;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}button{cursor:pointer;border:none;background:none;font:inherit;color:inherit}input,textarea,select{font:inherit}
::-webkit-scrollbar{width:4px;height:4px}::-webkit-scrollbar-track{background:var(--panel)}::-webkit-scrollbar-thumb{background:var(--ink3);border-radius:2px}

/* TOPBAR */
.topbar{height:var(--topbar);background:var(--panel);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 20px;gap:16px;flex-shrink:0}
.tl-logo{display:flex;align-items:center;gap:8px;width:var(--sidebar);flex-shrink:0}
.tl-dot{width:30px;height:30px;border-radius:50%;background:var(--brand);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;color:#fff}
.tl-name{font-size:15px;font-weight:700}.tl-name em{font-style:normal;color:var(--brand)}
.tl-right{margin-left:auto;display:flex;align-items:center;gap:10px}
.user-chip{display:flex;align-items:center;gap:8px;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:5px 10px}
.user-av{width:26px;height:26px;border-radius:50%;background:var(--brand);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0}
/* Equal-height top-right controls (View Site / user chip / Sign Out) */
.tl-right .btn,.tl-right .user-chip,.tl-right .logout-btn{height:36px;display:inline-flex;align-items:center;border-radius:8px}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;text-decoration:none;border:none}
.btn-primary{background:var(--brand);color:#fff}.btn-primary:hover{background:var(--brand-h)}
.btn-ghost{background:var(--card);color:var(--ink);border:1px solid var(--border)}.btn-ghost:hover{background:var(--border)}
.btn-danger{background:rgba(220,38,38,.15);color:#FCA5A5;border:1px solid rgba(220,38,38,.2)}.btn-danger:hover{background:rgba(220,38,38,.25)}
.btn-success{background:rgba(22,163,74,.15);color:#86EFAC;border:1px solid rgba(22,163,74,.2)}.btn-success:hover{background:rgba(22,163,74,.25)}
.btn-amber{background:rgba(217,119,6,.15);color:#FCD34D;border:1px solid rgba(217,119,6,.2)}
.btn-sm{padding:5px 10px;font-size:12px}
.logout-btn{background:rgba(220,38,38,.12);border:1px solid rgba(220,38,38,.2);color:#FCA5A5;padding:6px 12px;border-radius:6px;font-size:12px;transition:all .15s;cursor:pointer}
.logout-btn:hover{background:rgba(220,38,38,.2)}

/* LAYOUT */
.app-shell{display:flex;height:100vh;flex-direction:column}
.main-row{display:flex;flex:1;overflow:hidden}

/* SIDEBAR */
.sidebar{width:var(--sidebar);flex-shrink:0;background:var(--panel);border-right:1px solid var(--border);display:flex;flex-direction:column;overflow-y:auto;padding:12px 0}
.nav-section{padding:0 10px;margin-bottom:4px}
.nav-sec-label{font-size:10px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;color:var(--ink3);padding:8px 10px 4px}
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:7px;font-size:13.5px;font-weight:500;color:var(--ink2);cursor:pointer;margin-bottom:1px;transition:all .15s;text-decoration:none}
.nav-item:hover{background:var(--card);color:var(--ink)}.nav-item.active{background:rgba(212,66,10,.15);color:var(--brand)}
.nav-icon{font-size:16px;width:20px;text-align:center;flex-shrink:0}
.nav-badge{margin-left:auto;background:var(--brand);color:#fff;font-size:10px;font-weight:700;padding:1px 7px;border-radius:10px;min-width:20px;text-align:center}
.content-area{flex:1;overflow-y:auto;padding:24px;background:var(--bg)}

/* PAGE HEADER */
.page-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.page-hd h1{font-size:20px;font-weight:700}.page-hd-sub{font-size:13px;color:var(--ink2);margin-top:2px}

/* ALERTS */
.alert{padding:12px 16px;border-radius:8px;margin-bottom:18px;font-size:13px;display:flex;align-items:center;gap:10px}
.alert-success{background:rgba(22,163,74,.12);border:1px solid rgba(22,163,74,.2);color:#86EFAC}
.alert-error{background:rgba(220,38,38,.12);border:1px solid rgba(220,38,38,.2);color:#FCA5A5}

/* STAT CARDS */
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}
.stat-card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:18px 20px}
.stat-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.stat-label{font-size:12px;font-weight:500;color:var(--ink2);letter-spacing:.3px}
.stat-icon{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px}
.stat-value{font-size:26px;font-weight:700;line-height:1;margin-bottom:4px}
.stat-sub{font-size:11px;color:var(--ink3)}

/* TABLE */
.table-wrap{background:var(--card);border:1px solid var(--border);border-radius:10px;overflow:hidden}
.table-hd{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.table-hd h3{font-size:14px;font-weight:600}
.table-filters{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
table{width:100%;border-collapse:collapse}
th{padding:10px 16px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.8px;text-transform:uppercase;color:var(--ink3);border-bottom:1px solid var(--border);white-space:nowrap}
td{padding:12px 16px;font-size:13px;color:var(--ink2);border-bottom:1px solid var(--border2);vertical-align:middle}
tr:last-child td{border-bottom:none}tr:hover td{background:rgba(255,255,255,.015)}
.td-title{color:var(--ink);font-weight:500;max-width:300px}
.td-title small{display:block;font-size:11px;color:var(--ink3);margin-top:2px;font-weight:400}
.actions{display:flex;gap:4px;flex-wrap:wrap}
.badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:600;letter-spacing:.5px;text-transform:uppercase}
.badge-published{background:rgba(22,163,74,.15);color:#86EFAC}
.badge-draft{background:rgba(217,119,6,.15);color:#FCD34D}
.badge-admin{background:rgba(147,51,234,.15);color:#D8B4FE}
.badge-editor{background:rgba(212,66,10,.15);color:#FCA5A5}

/* FORM */
.field{margin-bottom:16px}
.field label{display:block;font-size:11px;font-weight:600;letter-spacing:.8px;text-transform:uppercase;color:var(--ink3);margin-bottom:7px}
.field input,.field textarea,.field select{width:100%;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:10px 13px;font-size:14px;color:var(--ink);outline:none;transition:border-color .2s}
.field input:focus,.field textarea:focus,.field select:focus{border-color:var(--brand)}
.field input::placeholder,.field textarea::placeholder{color:var(--ink3)}
.field textarea{resize:vertical;min-height:80px;line-height:1.6;font-family:'Inter',sans-serif}
.field select{cursor:pointer}
.field-hint{font-size:11px;color:var(--ink3);margin-top:5px}

/* PANEL CARD */
.panel-card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:18px;margin-bottom:14px}
.panel-card h4{font-size:12px;font-weight:600;letter-spacing:.8px;text-transform:uppercase;color:var(--ink3);margin-bottom:14px}
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border2)}
.toggle-row:last-child{border-bottom:none}
.toggle-label{font-size:13px;color:var(--ink2)}
input[type=checkbox].toggle-cb{appearance:none;width:38px;height:22px;background:var(--border);border-radius:11px;position:relative;cursor:pointer;transition:background .2s;flex-shrink:0;border:none}
input[type=checkbox].toggle-cb:checked{background:var(--brand)}
input[type=checkbox].toggle-cb::after{content:'';position:absolute;width:16px;height:16px;border-radius:50%;background:#fff;top:3px;left:3px;transition:left .2s}
input[type=checkbox].toggle-cb:checked::after{left:19px}

/* RTE */
.rte-wrap{background:var(--card);border:1px solid var(--border);border-radius:8px;overflow:hidden}
.rte-toolbar{padding:8px 12px;border-bottom:1px solid var(--border);display:flex;gap:4px;flex-wrap:wrap;background:var(--panel)}
.rte-btn{width:30px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:5px;font-size:13px;font-weight:600;color:var(--ink2);cursor:pointer;transition:all .15s;background:none;border:none}
.rte-btn:hover{background:var(--border);color:var(--ink)}
.rte-sep{width:1px;background:var(--border);margin:0 4px;align-self:stretch}
#rteEditor{min-height:380px;max-height:600px;overflow-y:auto;padding:18px 20px;font-size:15px;line-height:1.8;color:var(--ink);outline:none;font-family:'Inter',sans-serif}
#rteEditor:empty::before{content:'Start writing your article here…';color:var(--ink3);pointer-events:none}
#rteEditor h2{font-size:20px;font-weight:700;margin:24px 0 12px;color:var(--ink)}
#rteEditor h3{font-size:17px;font-weight:600;margin:20px 0 10px;color:var(--ink)}
#rteEditor p{margin-bottom:14px;color:var(--ink2)}
#rteEditor blockquote{border-left:3px solid var(--brand);padding-left:16px;margin:18px 0;font-style:italic;color:var(--ink2)}
#rteEditor strong{color:var(--ink);font-weight:600}
#rteEditor ul,#rteEditor ol{padding-left:24px;margin-bottom:14px;color:var(--ink2)}
#rteEditor li{margin-bottom:4px}
.rte-footer{padding:6px 14px;font-size:11px;color:var(--ink3);border-top:1px solid var(--border);background:var(--panel);display:flex;justify-content:space-between}

/* EDITOR GRID */
.editor-grid{display:grid;grid-template-columns:1fr 300px;gap:18px;align-items:start}

/* COVER */
.cover-preview{width:100%;aspect-ratio:16/9;border-radius:8px;overflow:hidden;background:linear-gradient(145deg,#1A1410,#221808);display:flex;align-items:center;justify-content:center;font-size:60px;margin-bottom:12px;border:1px solid var(--border);position:relative;cursor:pointer}
.cover-overlay{position:absolute;inset:0;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .2s;font-size:14px;color:#fff;font-weight:600;gap:6px}
.cover-preview:hover .cover-overlay{opacity:1}
.emoji-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:4px}
.eg-btn{aspect-ratio:1;display:flex;align-items:center;justify-content:center;font-size:20px;border-radius:6px;cursor:pointer;transition:background .15s;background:none;border:none}
.eg-btn:hover,.eg-btn.sel{background:rgba(212,66,10,.2)}
.bg-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-top:8px}
.bg-swatch{aspect-ratio:16/9;border-radius:5px;cursor:pointer;border:2px solid transparent;transition:border-color .15s}
.bg-swatch.sel{border-color:var(--brand)}
.tag-wrap{display:flex;flex-wrap:wrap;gap:6px;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:8px 10px;min-height:42px;cursor:text;align-items:center}
.tag-chip{display:inline-flex;align-items:center;gap:4px;background:rgba(212,66,10,.15);color:#FCA5A5;border-radius:4px;padding:2px 8px;font-size:12px}
.tag-chip .rx{cursor:pointer;opacity:.6;font-size:11px;line-height:1}.tag-chip .rx:hover{opacity:1}
.tag-inp{flex:1;background:none;border:none;outline:none;font-size:13px;color:var(--ink);min-width:80px}

/* MEDIA */
.media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px}
.media-item{background:var(--card);border:1px solid var(--border);border-radius:8px;overflow:hidden;transition:border-color .15s}
.media-item:hover{border-color:var(--brand)}
.media-thumb{aspect-ratio:1;overflow:hidden;display:flex;align-items:center;justify-content:center;background:var(--panel);font-size:32px}
.media-thumb img{width:100%;height:100%;object-fit:cover}
.media-info{padding:8px 10px}
.media-name{font-size:11px;color:var(--ink2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.media-size{font-size:10px;color:var(--ink3);margin-top:2px}
.drop-zone{border:2px dashed var(--border);border-radius:10px;padding:40px 24px;text-align:center;cursor:pointer;transition:all .2s;margin-bottom:18px}
.drop-zone:hover,.drop-zone.over{border-color:var(--brand);background:rgba(212,66,10,.03)}
.drop-icon{font-size:36px;margin-bottom:12px}

/* SETTINGS */
.settings-section{background:var(--card);border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:18px}
.ss-hd{padding:14px 18px;border-bottom:1px solid var(--border);font-size:14px;font-weight:600;display:flex;align-items:center;gap:8px}
.ss-body{padding:18px}
.settings-row{display:grid;grid-template-columns:220px 1fr;align-items:start;gap:16px;padding:12px 0;border-bottom:1px solid var(--border2)}
.settings-row:last-child{border-bottom:none}
.sr-label{font-size:13px;font-weight:500;color:var(--ink);padding-top:10px}
.sr-desc{font-size:11px;color:var(--ink3);margin-top:3px}

/* TABS */
.tabs{display:flex;gap:2px;margin-bottom:20px;border-bottom:1px solid var(--border)}
.tab-btn{padding:9px 16px;font-size:13px;font-weight:500;color:var(--ink2);cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px;transition:all .15s;background:none;border-top:none;border-left:none;border-right:none;border-bottom:2px solid transparent}
.tab-btn:hover{color:var(--ink)}.tab-btn.active{color:var(--brand);border-bottom-color:var(--brand)}
.tab-pane{display:none}.tab-pane.active{display:block}

/* MISC */
.search-input{background:var(--panel);border:1px solid var(--border);color:var(--ink);border-radius:6px;padding:6px 10px;font-size:12px;outline:none;width:180px}
.search-input:focus{border-color:var(--brand)}
.filter-select{background:var(--panel);border:1px solid var(--border);color:var(--ink);border-radius:6px;padding:6px 10px;font-size:12px;outline:none;cursor:pointer}
input[type=color]{width:36px;height:36px;padding:2px;border:1px solid var(--border);border-radius:6px;background:var(--card);cursor:pointer}
.pagination-wrap{padding:12px 16px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;font-size:12px;color:var(--ink3)}
.empty-state{text-align:center;padding:48px 20px;color:var(--ink3)}
/* Pagination */
nav[aria-label="Pagination"] { padding:12px 16px; border-top:1px solid var(--border); }
nav[aria-label="Pagination"] span, nav[aria-label="Pagination"] a { display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:5px;font-size:12px;background:var(--panel);border:1px solid var(--border);color:var(--ink2);margin:0 2px;transition:all .15s; }
nav[aria-label="Pagination"] a:hover { border-color:var(--brand);color:var(--brand); }
nav[aria-label="Pagination"] span[aria-current] { background:var(--brand);border-color:var(--brand);color:#fff; }
nav[aria-label="Pagination"] .pg-disabled { opacity:.4; }
nav[aria-label="Pagination"] .pg-dots { border:none;background:none; }

/* ── UI POLISH (icons + subtle depth; no layout changes) ── */
.nav-icon i{font-size:15px}
.nav-item{position:relative}
.nav-item.active::before{content:'';position:absolute;left:0;top:6px;bottom:6px;width:3px;border-radius:0 3px 3px 0;background:var(--brand)}
.stat-card{transition:border-color .18s,transform .18s,box-shadow .18s}
.stat-card:hover{border-color:rgba(212,66,10,.28);transform:translateY(-2px);box-shadow:0 6px 22px rgba(0,0,0,.22)}
.table-wrap,.panel-card,.settings-section{box-shadow:0 1px 3px rgba(0,0,0,.16)}
.btn:active,.logout-btn:active{transform:translateY(1px)}
.ss-hd i,.page-hd i{color:var(--brand)}
.media-thumb{color:var(--ink3)}
.drop-icon{color:var(--brand)}
.empty-state i{font-size:40px;color:var(--ink3);margin-bottom:12px;display:block}
/* Status badges → soft pills with a leading status dot */
.badge{display:inline-flex;align-items:center;gap:5px;border-radius:20px;padding:3px 9px;line-height:1;vertical-align:middle}
.badge-published::before,.badge-draft::before{content:'';width:6px;height:6px;border-radius:50%;background:currentColor;flex-shrink:0}
/* Table readability — subtle zebra striping + brand-tinted hover */
.table-wrap tbody tr:nth-child(even) td{background:rgba(255,255,255,.014)}
.table-wrap tbody tr:hover td{background:rgba(212,66,10,.06)}
</style>
@stack('styles')
</head>
<body>
<div class="app-shell">
  <div class="topbar">
    <a href="{{ route('admin.dashboard') }}" class="tl-logo" style="text-decoration:none">
      <img src="/uploads/logo.png" width="30" height="30" alt="ADT">
      <span class="tl-name"><em>ADT</em> Sports</span>
    </a>
    <div class="tl-right">
      <a href="{{ route('home') }}" target="_blank" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-up-right-from-square"></i> View Site</a>
      <div class="user-chip">
        <div class="user-av">{{ auth()->user()->initials }}</div>
        <span style="font-size:13px;font-weight:500">{{ auth()->user()->name }}</span>
      </div>
      <form action="{{ route('admin.logout') }}" method="POST" style="display:inline">
        @csrf <button type="submit" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out</button>
      </form>
    </div>
  </div>

  <div class="main-row">
    <nav class="sidebar">
      <div class="nav-section">
        <div class="nav-sec-label">Main</div>
        <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active':'' }}">
          <span class="nav-icon"><i class="fa-solid fa-gauge-high"></i></span> Dashboard
        </a>
        <a href="{{ route('admin.articles.index') }}" class="nav-item {{ request()->routeIs('admin.articles.*') && !request()->routeIs('admin.articles.create') ? 'active':'' }}">
          <span class="nav-icon"><i class="fa-solid fa-newspaper"></i></span> All Articles
          @php $dc = \App\Models\Article::where('status','draft')->count() @endphp
          @if($dc) <span class="nav-badge">{{ $dc }}</span> @endif
        </a>
        <a href="{{ route('admin.articles.create') }}" class="nav-item {{ request()->routeIs('admin.articles.create') ? 'active':'' }}">
          <span class="nav-icon"><i class="fa-solid fa-pen-nib"></i></span> Write Article
        </a>
      </div>
      <div class="nav-section">
        <div class="nav-sec-label">Content</div>
        <a href="{{ route('admin.categories.index') }}" class="nav-item {{ request()->routeIs('admin.categories.*') ? 'active':'' }}">
          <span class="nav-icon"><i class="fa-solid fa-tag"></i></span> Categories
        </a>
        <a href="{{ route('admin.media.index') }}" class="nav-item {{ request()->routeIs('admin.media.*') ? 'active':'' }}">
          <span class="nav-icon"><i class="fa-solid fa-images"></i></span> Media Library
        </a>
      </div>
      <div class="nav-section">
        <div class="nav-sec-label">Admin</div>
        @if(auth()->user()->isAdmin())
        <a href="{{ route('admin.comments.index') }}" class="nav-item {{ request()->routeIs('admin.comments.*') ? 'active':'' }}">
          <span class="nav-icon"><i class="fa-solid fa-comments"></i></span> Comments
        </a>
        <a href="{{ route('admin.users.index') }}" class="nav-item {{ request()->routeIs('admin.users.*') ? 'active':'' }}">
          <span class="nav-icon"><i class="fa-solid fa-users"></i></span> Team Members
        </a>
        <a href="{{ route('admin.subscribers.index') }}" class="nav-item {{ request()->routeIs('admin.subscribers.*') ? 'active':'' }}">
          <span class="nav-icon"><i class="fa-solid fa-envelope"></i></span> Subscribers
        </a>
        <a href="{{ route('admin.activity.index') }}" class="nav-item {{ request()->routeIs('admin.activity.*') ? 'active':'' }}">
          <span class="nav-icon"><i class="fa-solid fa-clock-rotate-left"></i></span> Activity Log
        </a>
        <a href="{{ route('admin.settings.index') }}" class="nav-item {{ request()->routeIs('admin.settings.*') ? 'active':'' }}">
          <span class="nav-icon"><i class="fa-solid fa-gear"></i></span> Settings
        </a>
        @endif
        <a href="{{ route('admin.settings.index') }}#profile" class="nav-item">
          <span class="nav-icon"><i class="fa-solid fa-circle-user"></i></span> My Profile
        </a>
      </div>
    </nav>

    <div class="content-area">
      @if(session('success'))
        <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> {{ session('success') }}</div>
      @endif
      @if(session('error'))
        <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> {{ session('error') }}</div>
      @endif
      @if($errors->any())
        <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> {{ $errors->first() }}</div>
      @endif
      @yield('content')
    </div>
  </div>
</div>
@stack('scripts')
</body>
</html>
