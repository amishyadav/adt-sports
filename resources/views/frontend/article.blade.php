@extends('layouts.frontend')
@section('title', $article->meta_title ?: $article->title . ' — ' . ($settings['site_name'] ?? 'ADT Sports'))
@section('meta_desc', $article->meta_desc ?: $article->excerpt)
@section('canonical', route('article', $article->slug))
@section('og_type', 'article')
@if($article->cover_image)
  @section('og_image', $article->cover_image)
@endif
{{-- Keep unpublished/draft articles (viewable by direct slug) out of the index --}}
@if($article->status !== 'published')
  @section('robots', 'noindex, nofollow')
@endif

@push('schema')
@php
    $siteName    = $settings['site_name'] ?? 'ADT Sports';
    $articleImg  = $article->cover_image
        ? (\Illuminate\Support\Str::startsWith($article->cover_image, ['http://','https://']) ? $article->cover_image : url($article->cover_image))
        : url('/public/uploads/logo.png');
    $publishedAt = ($article->published_at ?? $article->created_at)?->toAtomString();
    $modifiedAt  = ($article->updated_at ?? $article->published_at ?? $article->created_at)?->toAtomString();

    $blogPosting = [
        '@context'         => 'https://schema.org',
        '@type'            => 'NewsArticle',
        'headline'         => $article->title,
        'description'      => $article->meta_desc ?: $article->excerpt,
        'image'            => $articleImg,
        'datePublished'    => $publishedAt,
        'dateModified'     => $modifiedAt,
        'wordCount'        => str_word_count(strip_tags((string) $article->body)),
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => route('article', $article->slug)],
        'author'           => array_filter([
            '@type' => 'Person',
            'name'  => $article->author?->name ?? $siteName . ' Desk',
            'url'   => $article->author ? route('author', $article->author->id) : null,
        ]),
        'publisher'        => [
            '@type' => 'Organization',
            'name'  => $siteName,
            'logo'  => ['@type' => 'ImageObject', 'url' => url('/public/uploads/logo.png')],
        ],
    ];
    if ($article->category) {
        $blogPosting['articleSection'] = $article->category->name;
    }
    if (is_array($article->tags) && count($article->tags)) {
        $blogPosting['keywords'] = implode(', ', $article->tags);
    }

    $crumbs = [['name' => 'Home', 'item' => url('/')]];
    if ($article->category) {
        $crumbs[] = ['name' => $article->category->name, 'item' => route('category', $article->category->slug)];
    }
    $crumbs[] = ['name' => $article->title, 'item' => route('article', $article->slug)];
    $breadcrumb = [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => collect($crumbs)->map(fn ($c, $i) => [
            '@type'    => 'ListItem',
            'position' => $i + 1,
            'name'     => $c['name'],
            'item'     => $c['item'],
        ])->values()->all(),
    ];
@endphp
<script type="application/ld+json">
{!! json_encode($blogPosting, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
<script type="application/ld+json">
{!! json_encode($breadcrumb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@push('og_meta')
<meta property="article:published_time" content="{{ $publishedAt }}">
<meta property="article:modified_time" content="{{ $modifiedAt }}">
@if($article->author?->name)
<meta property="article:author" content="{{ $article->author->name }}">
@endif
@if($article->category)
<meta property="article:section" content="{{ $article->category->name }}">
@endif
@if(is_array($article->tags))
@foreach($article->tags as $tag)
<meta property="article:tag" content="{{ $tag }}">
@endforeach
@endif
@endpush

@section('content')
<div class="article-wrap">

  {{-- ── MAIN ARTICLE ─────────────────────────────────────── --}}
  <article class="article-main">
    <a href="{{ route('home') }}" class="back-btn">← Back to Home</a>

    <div class="art-hero-img" style="background:{{ $article->cover_bg }}">
      @if($article->cover_image)
        <img src="{{ $article->cover_image }}" alt="{{ $article->title }}" fetchpriority="high" decoding="async">
      @else
        <span style="position:relative;z-index:1">{{ $article->cover_emoji }}</span>
      @endif
    </div>

    @if($article->category)
      <a href="{{ route('category', $article->category->slug) }}" class="art-cat"
         style="background:{{ $article->category->color }}">
        {{ $article->category->name }}
      </a>
    @endif

    <h1 class="art-title">{{ $article->title }}</h1>

    @if($article->excerpt)
      <p class="art-deck">{{ $article->excerpt }}</p>
    @endif

    <div class="art-byline">
      <div class="byline-av">✍️</div>
      <div>
        <div class="byline-name">
        @if($article->author)
          <a href="{{ route('author', $article->author->id) }}" style="color:inherit" rel="author">{{ $article->author->name }}</a>
        @else
          ADT Sports Desk
        @endif
      </div>
        <div class="byline-info">{{ $article->formatted_date }} · {{ $article->read_time }} read · {{ number_format($article->views) }} views</div>
      </div>
      <div class="byline-actions">
        <button class="action-btn" onclick="shareArticle()" title="Share">📤</button>
        <button class="action-btn" onclick="cycleFontSize()" title="Adjust font size">Aa</button>
      </div>
    </div>

    {{-- Tags --}}
    @if($article->tags && count($article->tags))
    <div style="display:flex;flex-wrap:wrap;gap:7px;margin-bottom:28px">
      @foreach($article->tags as $tag)
        <a href="{{ route('tag', $tag) }}" class="tag" style="font-size:11px">{{ $tag }}</a>
      @endforeach
    </div>
    @endif

    {{-- Article body --}}
    <div class="art-body" id="artBody">
      {!! $article->body !!}
    </div>

    {{-- About the author (E-E-A-T) --}}
    @if($article->author)
    <div class="widget" style="margin-top:36px;display:flex;gap:14px;align-items:flex-start">
      <div class="byline-av" style="width:46px;height:46px;font-size:18px">✍️</div>
      <div>
        <div style="font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--ink3);margin-bottom:3px">Written by</div>
        <a href="{{ route('author', $article->author->id) }}" rel="author" style="font-family:var(--display);font-size:17px;font-weight:700;color:var(--ink)">{{ $article->author->name }}</a>
        @if($article->author->bio)
          <p style="font-size:13.5px;line-height:1.6;color:var(--ink2);margin-top:6px">{{ $article->author->bio }}</p>
        @endif
        <a href="{{ route('author', $article->author->id) }}" style="font-size:12.5px;font-weight:600;color:var(--brand);margin-top:8px;display:inline-block">More from {{ $article->author->name }} →</a>
      </div>
    </div>
    @endif

    {{-- Related articles --}}
    @if($related->count())
    <div class="related-section">
      <div class="sec-hd">
        <div class="sec-hd-left">
          <div class="sec-hd-bar"></div>
          <span class="sec-hd-label">More Stories</span>
        </div>
      </div>
      <div class="cards-grid" style="margin-top:18px">
        @foreach($related as $r)
        <a href="{{ route('article', $r->slug) }}" class="card-box" style="text-decoration:none">
          <div class="cb-thumb" style="background:{{ $r->cover_bg }}">
            @if($r->cover_image)
              <img src="{{ $r->cover_image }}" style="width:100%;height:100%;object-fit:cover" alt="{{ $r->title }}" loading="lazy" decoding="async">
            @else
              {{ $r->cover_emoji }}
            @endif
          </div>
          @if($r->category)
            <span class="cb-cat" style="color:{{ $r->category->color }}">{{ $r->category->name }}</span>
          @endif
          <h2 class="cb-title">{{ $r->title }}</h2>
          <div class="cb-meta">{{ $r->formatted_date }}</div>
        </a>
        @endforeach
      </div>
    </div>
    @endif
  </article>

  {{-- ── ARTICLE SIDEBAR ─────────────────────────────────── --}}
  <aside class="art-sidebar">
    <div class="art-sidebar-sticky">

      {{-- Newsletter --}}
      <div class="widget widget-nl" style="margin-bottom:22px">
        <div class="sec-hd" style="border-bottom-color:rgba(255,255,255,.1);margin-bottom:12px">
          <div class="sec-hd-left">
            <div class="sec-hd-bar"></div>
            <span class="sec-hd-label" style="color:#F0EBE5">Daily Digest</span>
          </div>
        </div>
        <p class="nl-desc" style="font-size:13px">Top Kabaddi stories straight to your inbox — free.</p>
        <input type="email" class="nl-input" placeholder="your@email.com" id="sideNlEmail">
        <button class="nl-btn" onclick="subscribeSide()">Subscribe →</button>
      </div>

      {{-- More Stories --}}
      @if($trending->count())
      <div class="widget">
        <div class="sec-hd" style="margin-bottom:14px">
          <div class="sec-hd-left">
            <div class="sec-hd-bar"></div>
            <span class="sec-hd-label">More Stories</span>
          </div>
        </div>
        @foreach($trending->take(5) as $t)
        <a href="{{ route('article', $t->slug) }}" class="card-num" style="text-decoration:none">
          <div style="width:52px;height:52px;border-radius:6px;background:{{ $t->cover_bg }};display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;overflow:hidden">
            @if($t->cover_image)
              <img src="{{ $t->cover_image }}" style="width:100%;height:100%;object-fit:cover" alt="{{ $t->title }}" loading="lazy" decoding="async">
            @else
              {{ $t->cover_emoji }}
            @endif
          </div>
          <div>
            <div class="cn-title">{{ $t->title }}</div>
            <div class="cn-meta">{{ $t->formatted_date }}</div>
          </div>
        </a>
        @endforeach
      </div>
      @endif

    </div>
  </aside>

</div>
@endsection

@push('scripts')
<script>
const fontSizes = ['16px','18px','20px'];
let fsIdx = 1;
function cycleFontSize() {
  fsIdx = (fsIdx + 1) % fontSizes.length;
  document.getElementById('artBody').style.fontSize = fontSizes[fsIdx];
}
function shareArticle() {
  if (navigator.share) {
    navigator.share({ title: '{{ addslashes($article->title) }}', url: window.location.href });
  } else {
    navigator.clipboard.writeText(window.location.href).then(() => alert('Link copied to clipboard!'));
  }
}
function subscribeSide() {
  const e = document.getElementById('sideNlEmail').value;
  if (!e || !e.includes('@')) { alert('Please enter a valid email address.'); return; }
  alert('✅ Subscribed! You\'ll receive the Daily Digest soon.');
  document.getElementById('sideNlEmail').value = '';
}
</script>
@endpush
