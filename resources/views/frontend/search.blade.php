@extends('layouts.frontend')
@include('partials.pagination_seo', ['paginator' => $articles])
@php $seoSite = $settings['site_name'] ?? 'ADT Sports'; @endphp
@section('title', $q ? "Search: {$q} — {$seoSite}" : "Search — {$seoSite}")
{{-- Search result pages are thin/duplicate content — keep out of the index but follow links --}}
@section('robots', 'noindex, follow')

@section('content')
<div class="wrap" style="padding-top:40px">

  {{-- Search Header --}}
  <div style="max-width:600px;margin:0 auto 48px">
    <h1 style="font-family:var(--display);font-size:clamp(28px,4vw,40px);font-weight:800;line-height:1.2;color:var(--ink);margin-bottom:20px;text-align:center">
      Search Kabaddi Stories
    </h1>
    <form action="{{ route('search') }}" method="GET">
      <div style="display:flex;background:var(--surface);border:2px solid var(--rule);border-radius:50px;overflow:hidden;transition:border-color .2s;padding:4px 4px 4px 20px"
           onfocusin="this.style.borderColor='var(--brand)'" onfocusout="this.style.borderColor='var(--rule)'">
        <input type="text" name="q" value="{{ $q }}" placeholder="Search articles, players, leagues…"
          autofocus
          style="flex:1;background:none;border:none;outline:none;font-size:15px;font-family:var(--sans);color:var(--ink);padding:8px 0">
        <button type="submit"
          style="background:var(--brand);color:#fff;border:none;border-radius:40px;padding:10px 24px;font-size:13px;font-weight:600;cursor:pointer;transition:background .15s"
          onmouseover="this.style.background='var(--brand-h)'" onmouseout="this.style.background='var(--brand)'">
          Search
        </button>
      </div>
    </form>
  </div>

  @if($q)
  <div class="content-grid">
    <main>
      @if($articles->count())
      <div class="sec-hd">
        <div class="sec-hd-left">
          <div class="sec-hd-bar"></div>
          <span class="sec-hd-label">{{ $articles->total() }} results for "{{ $q }}"</span>
        </div>
      </div>

      @foreach($articles as $a)
      @include('frontend.partials.article_row', ['a' => $a])
      @endforeach

      @include('frontend.partials.load_more', ['paginator' => $articles])

      @else
      <div style="text-align:center;padding:64px 20px;color:var(--ink3)">
        <div style="font-size:44px;margin-bottom:14px">🔍</div>
        <p style="font-size:16px;margin-bottom:8px">No results for <strong style="color:var(--ink)">"{{ $q }}"</strong></p>
        <p style="font-size:14px">Try different keywords or browse categories below.</p>
        <div class="tag-cloud" style="justify-content:center;margin-top:20px">
          @foreach($categories as $cat)
            <a href="{{ route('category', $cat->slug) }}" class="tag">{{ $cat->name }}</a>
          @endforeach
        </div>
      </div>
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
          <div class="sec-hd-left"><div class="sec-hd-bar"></div><span class="sec-hd-label">Browse Topics</span></div>
        </div>
        <div class="tag-cloud">
          @foreach($categories as $cat)
            <a href="{{ route('category', $cat->slug) }}" class="tag">{{ $cat->name }}</a>
          @endforeach
        </div>
      </div>
    </aside>
  </div>
  @endif

</div>
@endsection
