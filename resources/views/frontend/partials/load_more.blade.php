{{-- "Load more" button — replaces numbered pagination on the listing feeds.
       $paginator — the LengthAwarePaginator (required)
       $label     — optional button text

     Progressive enhancement: the button is a real link to the next page, so without
     JS (or for crawlers) it navigates normally and pagination still works. The script
     below intercepts the click, fetches the next page as a fragment (?partial=1) and
     appends the rows in place. --}}
@php $loadMoreLabel = $label ?? 'Load more articles'; @endphp
@if($paginator->hasMorePages())
<div class="pagination-wrap load-more-wrap">
  <a href="{{ $paginator->nextPageUrl() }}" class="load-more-btn" rel="next" data-load-more>
    <span class="lm-text">{{ $loadMoreLabel }}</span>
    <i class="fa-solid fa-arrow-down-long lm-icon" aria-hidden="true"></i>
  </a>
</div>
@endif

@once
@push('scripts')
<script>
(function () {
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-load-more]');
    if (!btn || btn.dataset.loading) return;
    e.preventDefault();

    btn.dataset.loading = '1';
    btn.classList.add('is-loading');
    var label = btn.querySelector('.lm-text');
    if (label) label.textContent = 'Loading…';
    var icon = btn.querySelector('.lm-icon');
    if (icon) icon.className = 'fa-solid fa-circle-notch lm-icon';

    var url = new URL(btn.href, window.location.origin);
    url.searchParams.set('partial', '1');

    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
      .then(function (html) {
        var wrap = btn.closest('.load-more-wrap');
        var feed = wrap.parentNode;
        var tmp  = document.createElement('div');
        tmp.innerHTML = html.trim();

        var nextWrap = tmp.querySelector('.load-more-wrap');
        // New rows go above the optional anchor (so a featured/highlight block
        // stays pinned below them); otherwise straight above the button.
        var anchor = feed.querySelector('[data-load-more-anchor]') || wrap;
        Array.prototype.slice.call(tmp.childNodes).forEach(function (node) {
          if (node === nextWrap) return;
          feed.insertBefore(node, anchor);
        });
        // Swap the old button for the next page's (or drop it when no pages remain).
        if (nextWrap) wrap.replaceWith(nextWrap); else wrap.remove();
      })
      .catch(function () {
        // Network/server hiccup — fall back to a normal page load. Strip ?partial
        // so we land on the full styled page, not the bare fragment (a fragment
        // button's href carries partial=1 from the request it was rendered for).
        var fb = new URL(btn.href, window.location.origin);
        fb.searchParams.delete('partial');
        window.location.href = fb.toString();
      });
  });
})();
</script>
@endpush
@endonce
