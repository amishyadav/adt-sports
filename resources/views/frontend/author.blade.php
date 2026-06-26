@extends('layouts.frontend')
@include('partials.pagination_seo', ['paginator' => $articles])
@section('title', $user->name . ' — ' . ($settings['site_name'] ?? 'ADT Sports'))
@section('meta_desc', $user->bio ?: ('Articles, analysis and reporting by ' . $user->name . ' on ' . ($settings['site_name'] ?? 'ADT Sports')))
{{-- Self-reference paginated pages (incl. ?page=N) so deeper pages stay indexable --}}
@section('canonical', $articles->currentPage() > 1 ? $articles->url($articles->currentPage()) : route('author', $user->id))

@push('head_links')
@if($articles->previousPageUrl())<link rel="prev" href="{{ $articles->previousPageUrl() }}">@endif
@if($articles->nextPageUrl())<link rel="next" href="{{ $articles->nextPageUrl() }}">@endif
@endpush

@push('schema')
<script type="application/ld+json">
{!! json_encode([
    '@context'   => 'https://schema.org',
    '@type'      => 'ProfilePage',
    'mainEntity' => array_filter([
        '@type'       => 'Person',
        'name'        => $user->name,
        'description' => $user->bio ?: null,
        'url'         => route('author', $user->id),
    ]),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}
</script>
<script type="application/ld+json">
{!! json_encode([
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $user->name, 'item' => route('author', $user->id)],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}
</script>
@endpush

@section('content')
<div class="wrap">

  {{-- Author Header --}}
  <div style="padding:40px 0 8px;border-bottom:3px solid var(--brand);margin-bottom:32px">
    <div style="display:inline-block;background:var(--brand);color:#fff;font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:3px 12px;border-radius:3px;margin-bottom:12px">
      Author
    </div>
    <h1 style="font-family:var(--display);font-size:clamp(32px,5vw,52px);font-weight:800;line-height:1.1;color:var(--ink);margin-bottom:10px">
      {{ $user->name }}
    </h1>
    @if($user->bio)
      <p style="font-size:16px;color:var(--ink2);max-width:600px;line-height:1.6">{{ $user->bio }}</p>
    @endif
    <div style="font-size:13px;color:var(--ink3);margin-top:10px">
      {{ $articles->total() }} {{ Str::plural('article', $articles->total()) }}
    </div>
  </div>

  <div class="content-grid">
    <main>
      @foreach($articles as $a)
      @include('frontend.partials.article_row', ['a' => $a, 'hideAuthor' => true])
      @endforeach

      @include('frontend.partials.load_more', ['paginator' => $articles])
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
    </aside>
  </div>
</div>
@endsection
