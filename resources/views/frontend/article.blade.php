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
        : url('/uploads/logo.png');
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
            'logo'  => ['@type' => 'ImageObject', 'url' => url('/uploads/logo.png')],
        ],
    ];
    if ($article->category) {
        $blogPosting['articleSection'] = $article->category->name;
    }
    if ($article->tags->isNotEmpty()) {
        $blogPosting['keywords'] = $article->tags->pluck('name')->implode(', ');
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
{!! json_encode($blogPosting, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}
</script>
<script type="application/ld+json">
{!! json_encode($breadcrumb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}
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
@foreach($article->tags as $tag)
<meta property="article:tag" content="{{ $tag->name }}">
@endforeach
@endpush

@push('styles')
<style>
.share-row .share-btn{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:600;
  padding:7px 12px;border-radius:8px;border:1px solid var(--line,#2a2118);background:var(--card);
  color:var(--ink);cursor:pointer;text-decoration:none;transition:transform .08s,border-color .15s}
.share-row .share-btn:hover{transform:translateY(-1px);border-color:var(--brand)}
#likeBtn[aria-pressed="true"]{border-color:#e0245e}
#likeBtn[aria-pressed="true"] .fa-heart{color:#e0245e}
/* Stop Chrome's autofill from repainting the comment inputs grey/blue — keep our own bg + text colour */
#commentGateForm input:-webkit-autofill,
#commentGateForm input:-webkit-autofill:hover,
#commentGateForm input:-webkit-autofill:focus,
#commentGateForm input:-webkit-autofill:active{
  -webkit-box-shadow:0 0 0 1000px var(--bg2,#fff) inset;
  box-shadow:0 0 0 1000px var(--bg2,#fff) inset;
  -webkit-text-fill-color:var(--ink);
  caret-color:var(--ink);
  transition:background-color 9999s ease-in-out 0s;
}
</style>
@endpush

@section('content')
<div class="article-wrap">

  {{-- ── MAIN ARTICLE ─────────────────────────────────────── --}}
  <article class="article-main">
    <a href="{{ route('home') }}" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Back to Home</a>

    <div class="art-hero-img" style="background:{{ $article->cover_bg }}">
      @if($article->cover_image)
        <x-responsive-image :src="$article->cover_image" :alt="$article->title" eager />
      @else
        <x-cover-placeholder :article="$article" />
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
      <div class="byline-av"><i class="fa-solid fa-user-pen"></i></div>
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
        <button class="action-btn" id="likeBtn" onclick="toggleLike()" title="Like this article" aria-pressed="false" aria-label="Like this article"><i class="fa-regular fa-heart" id="likeIcon"></i> <span id="likeCount">{{ number_format($article->likes) }}</span></button>
        <button class="action-btn" onclick="shareArticle()" title="Share" aria-label="Share this article"><i class="fa-solid fa-arrow-up-from-bracket"></i></button>
        <button class="action-btn" onclick="cycleFontSize()" title="Adjust font size" aria-label="Adjust font size"><i class="fa-solid fa-text-height"></i></button>
      </div>
    </div>

    {{-- Social share --}}
    @php
      $shareUrl  = route('article', $article->slug);
      $shareText = rawurlencode($article->title);
      $shareLink = rawurlencode($shareUrl);
    @endphp
    <div class="share-row" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:24px">
      <a class="share-btn" href="https://wa.me/?text={{ $shareText }}%20{{ $shareLink }}" target="_blank" rel="noopener" aria-label="Share on WhatsApp"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
      <a class="share-btn" href="https://twitter.com/intent/tweet?url={{ $shareLink }}&text={{ $shareText }}" target="_blank" rel="noopener" aria-label="Share on X"><i class="fa-brands fa-x-twitter"></i> Post</a>
      <a class="share-btn" href="https://www.facebook.com/sharer/sharer.php?u={{ $shareLink }}" target="_blank" rel="noopener" aria-label="Share on Facebook"><i class="fa-brands fa-facebook"></i> Share</a>
      <button class="share-btn" type="button" onclick="copyArticleLink()" aria-label="Copy link"><i class="fa-solid fa-link"></i> Copy link</button>
    </div>

    {{-- Tags --}}
    @if($article->tags->isNotEmpty())
    <div style="display:flex;flex-wrap:wrap;gap:7px;margin-bottom:28px">
      @foreach($article->tags as $tag)
        <a href="{{ route('tag', $tag) }}" class="tag" style="font-size:11px">{{ $tag->name }}</a>
      @endforeach
    </div>
    @endif

    {{-- Article body --}}
    <div class="art-body" id="artBody">
      {!! $article->body !!}
    </div>

    @if($article->isPublished())
    {{-- Async view-count beacon — keeps this page fully cacheable --}}
    <script>fetch(@json(route('article.hit', $article)), {cache:'no-store'}).catch(function(){});</script>
    @endif

    {{-- More from this author — the name is already in the byline above, so just
         the link here (plus the bio if there is one, which isn't redundant). --}}
    @if($article->author)
    <div style="margin-top:32px">
      @if($article->author->bio)
        <p style="font-size:13.5px;line-height:1.6;color:var(--ink2);margin-bottom:8px">{{ $article->author->bio }}</p>
      @endif
      <a href="{{ route('author', $article->author->id) }}" rel="author" style="font-size:14px;font-weight:700;color:var(--brand)">More by {{ $article->author->name }} →</a>
    </div>
    @endif

    {{-- Previous / next article navigation --}}
    @if($prev || $next)
    <nav class="art-prevnext" aria-label="More articles"
         style="display:flex;gap:12px;justify-content:space-between;margin-top:36px;flex-wrap:wrap">
      @if($prev)
        <a href="{{ route('article', $prev->slug) }}" rel="prev"
           style="flex:1;min-width:220px;padding:14px 16px;border:1px solid var(--line,#2a2118);border-radius:8px;color:var(--ink)">
          <div style="font-size:11px;color:var(--ink3)">← Previous</div>
          <div style="font-weight:600;font-size:14px">{{ Str::limit($prev->title, 60) }}</div>
        </a>
      @else <span></span> @endif
      @if($next)
        <a href="{{ route('article', $next->slug) }}" rel="next"
           style="flex:1;min-width:220px;padding:14px 16px;border:1px solid var(--line,#2a2118);border-radius:8px;color:var(--ink)">
          <div style="font-size:11px;color:var(--ink3)">Next →</div>
          <div style="font-weight:600;font-size:14px">{{ Str::limit($next->title, 60) }}</div>
        </a>
      @endif
    </nav>
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
              <x-responsive-image :src="$r->cover_image" :alt="$r->title" style="width:100%;height:100%;object-fit:cover" />
            @else
              <x-cover-placeholder :article="$r" />
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

    {{-- ── COMMENTS ─────────────────────────────────────────── --}}
    <section class="comments-section" id="comments" style="margin-top:40px">
      <div class="sec-hd">
        <div class="sec-hd-left">
          <div class="sec-hd-bar"></div>
          <span class="sec-hd-label">Comments (<span id="commentCount">{{ $comments->count() }}</span>)</span>
        </div>
      </div>

      @if(request('comment') === 'check')
        <div class="comment-note" role="status" style="margin:16px 0;padding:12px 14px;border-radius:8px;background:rgba(212,66,10,.1);border:1px solid rgba(212,66,10,.3);font-size:13.5px;color:var(--ink)">
          <i class="fa-solid fa-envelope" style="color:var(--brand)"></i> Check your inbox (and spam) for the link to start commenting. We send at most {{ \App\Support\SubscribeThrottle::MAX_PER_DAY }} emails a day to an address.
        </div>
      @elseif(request('comment') === 'error')
        <div class="comment-note" role="alert" style="margin:16px 0;padding:12px 14px;border-radius:8px;background:rgba(224,36,94,.1);border:1px solid rgba(224,36,94,.3);font-size:13.5px;color:var(--ink)">
          <i class="fa-solid fa-circle-exclamation" style="color:#e0245e"></i> Please check your comment (under 5000 characters), then try again.
        </div>
      @elseif(request('comment') === 'subscribe')
        <div class="comment-note" role="alert" style="margin:16px 0;padding:12px 14px;border-radius:8px;background:rgba(224,36,94,.1);border:1px solid rgba(224,36,94,.3);font-size:13.5px;color:var(--ink)">
          <i class="fa-solid fa-circle-exclamation" style="color:#e0245e"></i> Please subscribe below before commenting.
        </div>
      @elseif(request('comment') === 'expired')
        <div class="comment-note" role="alert" style="margin:16px 0;padding:12px 14px;border-radius:8px;background:rgba(224,36,94,.1);border:1px solid rgba(224,36,94,.3);font-size:13.5px;color:var(--ink)">
          <i class="fa-solid fa-circle-exclamation" style="color:#e0245e"></i> That confirmation link has already been used or expired. Subscribe again to get a new one.
        </div>
      @elseif(request('comment') === 'mailfail')
        <div class="comment-note" role="alert" style="margin:16px 0;padding:12px 14px;border-radius:8px;background:rgba(224,36,94,.1);border:1px solid rgba(224,36,94,.3);font-size:13.5px;color:var(--ink)">
          <i class="fa-solid fa-circle-exclamation" style="color:#e0245e"></i> We couldn't send the confirmation email just now. Please try again in a moment.
        </div>
      @endif

      {{-- Inline feedback for JS submissions (no-JS users get the ?comment= notice above). --}}
      <div id="commentAjaxNote"></div>

      <div class="comment-list" id="commentList" style="margin:18px 0">
        @forelse($comments as $comment)
          @include('frontend.partials.comment', ['comment' => $comment])
        @empty
          <p id="commentEmpty" style="font-size:13.5px;color:var(--ink3)">Be the first to comment.</p>
        @endforelse
      </div>

      {{-- Subscribe gate (shown to visitors who haven't subscribed). JS hides this
           and reveals the comment box once the adt_commenter_name cookie is set. --}}
      <div id="commentGate" class="comment-gate" style="margin-top:8px;padding:18px;border:1px solid var(--border,#2a2118);border-radius:12px;background:var(--card)">
        <div style="font-family:var(--display);font-size:17px;font-weight:700;color:var(--ink);margin-bottom:4px">
          <i class="fa-regular fa-comments" style="color:var(--brand)"></i> Join the conversation
        </div>
        <p style="font-size:13px;color:var(--ink2);margin-bottom:12px">Subscribe once to comment — we'll email you a link to confirm. You'll also get our Kabaddi newsletter, unsubscribe anytime. We send at most {{ \App\Support\SubscribeThrottle::MAX_PER_DAY }} emails a day.</p>
        <div id="gateNote" role="status" style="display:none;margin-bottom:12px;padding:10px 12px;border-radius:8px;font-size:13px;line-height:1.45"></div>
        <form method="POST" action="{{ route('article.commenter.subscribe', $article) }}" id="commentGateForm">
          @csrf
          {{-- Honeypot: hidden from real users; non-semantic name to dodge autofill --}}
          <input type="text" name="hp_url" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
          <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px">
            {{-- Name not HTML-required: a returning verified subscriber can sign in with email alone (server enforces it for new subscribers). --}}
            <input type="text" name="name" placeholder="Your name" maxlength="80" aria-label="Your name"
                   style="flex:1;min-width:180px;background:var(--bg2,#fff);border:1px solid var(--border,#2a2118);border-radius:8px;padding:10px 12px;font-size:14px;color:var(--ink)">
            <input type="email" name="email" placeholder="Your email" required maxlength="255" aria-label="Your email"
                   style="flex:1;min-width:180px;background:var(--bg2,#fff);border:1px solid var(--border,#2a2118);border-radius:8px;padding:10px 12px;font-size:14px;color:var(--ink)">
          </div>
          <button type="submit" class="btn-sub" style="border:none;cursor:pointer">Subscribe to comment →</button>
          <p style="font-size:11.5px;color:var(--ink3);margin:10px 0 0">Already subscribed? Enter the same email — we'll send you a quick sign-in link.</p>
        </form>
      </div>

      {{-- Comment box (revealed by JS for identified/subscribed visitors). --}}
      <div id="commentBox" style="display:none;margin-top:8px">
        <p style="font-size:13px;color:var(--ink2);margin-bottom:8px">
          Commenting as <strong id="commenterName" style="color:var(--ink)"></strong>
          · <form method="POST" action="{{ route('article.commenter.forget', $article) }}" style="display:inline">@csrf<button type="submit" style="background:none;border:0;color:var(--brand);font-size:13px;cursor:pointer;padding:0">not you?</button></form>
        </p>
        <form method="POST" action="{{ route('article.comments.store', $article) }}" class="comment-form">
          @csrf
          {{-- Honeypot: hidden from real users; non-semantic name to dodge autofill --}}
          <input type="text" name="hp_url" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
          <textarea name="body" rows="4" placeholder="Add a comment…" required maxlength="5000" aria-label="Comment"
                    style="width:100%;background:var(--card);border:1px solid var(--border,#2a2118);border-radius:8px;padding:10px 12px;font-size:14px;color:var(--ink);resize:vertical"></textarea>
          <p style="font-size:11.5px;color:var(--ink3);margin:6px 0 10px">Comments appear right away. Please keep it respectful — anything abusive may be removed.</p>
          <button type="submit" class="btn-sub" style="border:none;cursor:pointer">Post comment →</button>
        </form>
      </div>
    </section>
  </article>

  {{-- ── ARTICLE SIDEBAR ─────────────────────────────────── --}}
  <aside class="art-sidebar">
    <div class="art-sidebar-sticky">

      {{-- Newsletter --}}

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
              <x-responsive-image :src="$t->cover_image" :alt="$t->title" style="width:100%;height:100%;object-fit:cover" />
            @else
              <x-cover-placeholder :article="$t" />
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
// The comment notice is server-rendered from ?comment=posted|error (cache-safe).
// Strip it from the URL after first paint so a reload/back doesn't keep showing it.
if (location.search.includes('comment=')) {
  const u = new URL(location.href);
  u.searchParams.delete('comment');
  history.replaceState(null, '', u.pathname + (u.search || '') + '#comments');
}

// Subscribe-gate ↔ comment-box toggle. The page is full-page cached, so this is
// personalised client-side from the readable adt_commenter_name cookie.
(function () {
  const match = document.cookie.match(/(?:^|;\s*)adt_commenter_name=([^;]*)/);
  let name = '';
  try { name = match ? decodeURIComponent(match[1]) : ''; } catch (e) { name = ''; }
  const gate = document.getElementById('commentGate');
  const box  = document.getElementById('commentBox');
  if (name && gate && box) {
    gate.style.display = 'none';
    box.style.display = 'block';
    const el = document.getElementById('commenterName');
    if (el) el.textContent = name;
  }
})();

// Post a comment without reloading the page: submit via fetch and inject the
// rendered comment inline. Falls back to a normal form POST if JS is disabled.
(function () {
  const form = document.querySelector('.comment-form');
  if (!form) return;
  const list    = document.getElementById('commentList');
  const note    = document.getElementById('commentAjaxNote');
  const countEl = document.getElementById('commentCount');
  const btn     = form.querySelector('button[type="submit"]');
  const ta      = form.querySelector('textarea[name="body"]');

  function showNote(ok, msg) {
    const css  = ok ? 'background:rgba(22,128,60,.12);border:1px solid rgba(22,128,60,.3)'
                    : 'background:rgba(224,36,94,.1);border:1px solid rgba(224,36,94,.3)';
    const icon = ok ? '<i class="fa-solid fa-circle-check" style="color:#16803c"></i>'
                    : '<i class="fa-solid fa-circle-exclamation" style="color:#e0245e"></i>';
    note.innerHTML = '<div class="comment-note" role="status" style="margin:0 0 16px;padding:12px 14px;'
      + 'border-radius:8px;font-size:13.5px;color:var(--ink);' + css + '">' + icon + ' ' + msg + '</div>';
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (btn) btn.disabled = true;
    note.innerHTML = '';
    fetch(form.action, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: new FormData(form),
    })
    .then(r => r.json().then(d => ({ ok: r.ok, d })))
    .then(({ ok, d }) => {
      if (ok && d.ok) {
        if (d.html) {
          const empty = document.getElementById('commentEmpty');
          if (empty) empty.remove();
          list.insertAdjacentHTML('beforeend', d.html);
          const added = list.lastElementChild;
          if (added) added.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        if (typeof d.count !== 'undefined' && countEl) countEl.textContent = d.count;
        if (ta) ta.value = '';
        // No success banner — the comment appearing in the list is the confirmation.
      } else {
        showNote(false, (d && d.message) || 'Something went wrong. Please try again.');
      }
    })
    .catch(() => showNote(false, "Couldn't post your comment. Please try again."))
    .finally(() => { if (btn) btn.disabled = false; });
  });
})();

// Comment-gate (subscribe-to-comment) without a reload: post via fetch and show
// the result inline. Returning verified subscribers can sign in with email only.
(function () {
  const form = document.getElementById('commentGateForm');
  if (!form) return;
  const note = document.getElementById('gateNote');
  const btn  = form.querySelector('button[type="submit"]');

  function showNote(ok, msg) {
    if (!note) return;
    note.style.display = 'block';
    note.style.background = ok ? 'rgba(22,128,60,.14)' : 'rgba(224,36,94,.12)';
    note.style.border = '1px solid ' + (ok ? 'rgba(22,128,60,.4)' : 'rgba(224,36,94,.4)');
    note.style.color = 'var(--ink)';
    note.textContent = msg;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (btn) btn.disabled = true;
    fetch(form.action, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: new FormData(form),
    })
    .then(r => r.json().then(d => ({ ok: r.ok, d })).catch(() => ({ ok: r.ok, d: {} })))
    .then(({ ok, d }) => {
      showNote(ok && d.ok, d.message || (ok ? 'Check your inbox to continue.' : 'Something went wrong. Please try again.'));
    })
    .catch(() => showNote(false, 'Something went wrong. Please try again.'))
    .finally(() => { if (btn) btn.disabled = false; });
  });
})();

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
    copyArticleLink();
  }
}
function copyArticleLink() {
  navigator.clipboard.writeText(window.location.href).then(() => alert('Link copied to clipboard!'));
}

// ── Likes ─────────────────────────────────────────────
const LIKE_KEY = 'liked-{{ $article->id }}';
function paintLike(liked) {
  const btn = document.getElementById('likeBtn');
  const icon = document.getElementById('likeIcon');
  if (!btn) return;
  btn.setAttribute('aria-pressed', liked ? 'true' : 'false');
  if (icon) icon.className = liked ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
}
function toggleLike() {
  const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const btn = document.getElementById('likeBtn');
  if (btn) btn.disabled = true;
  fetch(@json(route('article.like', $article)), {
    method: 'POST',
    headers: {'Accept':'application/json','X-CSRF-TOKEN':token},
  })
  .then(r => r.ok ? r.json() : Promise.reject(r))
  .then(d => {
    document.getElementById('likeCount').textContent = new Intl.NumberFormat().format(d.likes);
    paintLike(d.liked);
    try { localStorage.setItem(LIKE_KEY, d.liked ? '1' : '0'); } catch (e) {}
  })
  .catch(() => {})
  .finally(() => { if (btn) btn.disabled = false; });
}
// Reflect the visitor's persisted like state on the (cached) page.
try { if (localStorage.getItem(LIKE_KEY) === '1') paintLike(true); } catch (e) {}
</script>
@endpush
