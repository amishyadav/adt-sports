@extends('layouts.frontend')
@section('title', $tag . ' — ' . ($settings['site_name'] ?? 'ADT Sports'))
@section('meta_desc', 'Latest ' . $tag . ' news, analysis and stories on ' . ($settings['site_name'] ?? 'ADT Sports'))
{{-- Self-reference paginated pages (incl. ?page=N) so deeper pages stay indexable --}}
@section('canonical', $articles->currentPage() > 1 ? $articles->url($articles->currentPage()) : route('tag', $tag))

@push('head_links')
@if($articles->previousPageUrl())<link rel="prev" href="{{ $articles->previousPageUrl() }}">@endif
@if($articles->nextPageUrl())<link rel="next" href="{{ $articles->nextPageUrl() }}">@endif
@endpush

@push('schema')
<script type="application/ld+json">
{!! json_encode([
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $tag,  'item' => route('tag', $tag)],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@section('content')
<div class="wrap">

  {{-- Tag Header --}}
  <div style="padding:40px 0 8px;border-bottom:3px solid var(--brand);margin-bottom:32px">
    <div style="display:inline-block;background:var(--brand);color:#fff;font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:3px 12px;border-radius:3px;margin-bottom:12px">
      Tag
    </div>
    <h1 style="font-family:var(--display);font-size:clamp(32px,5vw,52px);font-weight:800;line-height:1.1;color:var(--ink);margin-bottom:10px">
      {{ $tag }}
    </h1>
    <div style="font-size:13px;color:var(--ink3);margin-top:10px">
      {{ $articles->total() }} {{ Str::plural('article', $articles->total()) }}
    </div>
  </div>

  <div class="content-grid">
    <main>
      @foreach($articles as $a)
      <a href="{{ route('article', $a->slug) }}" class="card-row" style="text-decoration:none;display:grid">
        <div>
          <span class="cr-cat" style="{{ $a->category ? 'color:'.$a->category->color : '' }}">{{ $a->category?->name ?? 'Article' }}</span>
          <h2 class="cr-title">{{ $a->title }}</h2>
          @if($a->excerpt)<div class="cr-excerpt">{{ $a->excerpt }}</div>@endif
          <div class="cr-meta">
            <span>{{ $a->author?->name ?? 'ADT Sports' }}</span>
            <span class="sep"></span>
            <span>{{ $a->formatted_date }}</span>
            <span class="sep"></span>
            <span>{{ $a->read_time }} read</span>
          </div>
        </div>
        <div class="cr-thumb" style="background:{{ $a->cover_bg }}">
          @if($a->cover_image)<img src="{{ $a->cover_image }}" style="width:100%;height:100%;object-fit:cover" alt="{{ $a->title }}" loading="lazy" decoding="async">
          @else {{ $a->cover_emoji }} @endif
        </div>
      </a>
      @endforeach

      @if($articles->hasPages())
        <div class="pagination-wrap">{{ $articles->links() }}</div>
      @endif
    </main>

    <aside class="sidebar-col">
      <div class="widget">
        <div class="sec-hd" style="margin-bottom:14px">
          <div class="sec-hd-left"><div class="sec-hd-bar"></div><span class="sec-hd-label">Trending</span></div>
        </div>
        @foreach($trending as $i => $t)
        <a href="{{ route('article', $t->slug) }}" class="card-num" style="text-decoration:none">
          <div class="cn-num">0{{ $i + 1 }}</div>
          <div>
            <div class="cn-title">{{ $t->title }}</div>
            <div class="cn-meta">{{ $t->category?->name }} · {{ $t->formatted_date }}</div>
          </div>
        </a>
        @endforeach
      </div>
      <div class="widget">
        <div class="sec-hd" style="margin-bottom:14px">
          <div class="sec-hd-left"><div class="sec-hd-bar"></div><span class="sec-hd-label">Categories</span></div>
        </div>
        <div class="tag-cloud">
          @foreach($categories as $cat)
            <a href="{{ route('category', $cat->slug) }}" class="tag">{{ $cat->name }}</a>
          @endforeach
        </div>
      </div>
    </aside>
  </div>
</div>
@endsection
