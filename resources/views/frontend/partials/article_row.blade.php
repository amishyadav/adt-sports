{{-- One article "card-row". Shared by the inline feed and the "Load more" fragment
     so page 1 and appended pages render identically.
       $a          — the article (required)
       $rowCat     — optional category to force as the label (e.g. on a category page);
                     falls back to the article's own primary category
       $hideAuthor — optional bool; hides the author name in the meta (e.g. author pages) --}}
@php
    $rowCat     = $rowCat ?? $a->category;
    $hideAuthor = $hideAuthor ?? false;
@endphp
<a href="{{ route('article', $a->slug) }}" class="card-row" style="text-decoration:none;display:grid">
  <div>
    <span class="cr-cat" style="{{ $rowCat ? 'color:'.$rowCat->color : '' }}">{{ $rowCat?->name ?? 'Article' }}</span>
    <h2 class="cr-title">{{ $a->title }}</h2>
    @if($a->excerpt)<div class="cr-excerpt">{{ $a->excerpt }}</div>@endif
    <div class="cr-meta">
      @unless($hideAuthor)
        <span>{{ $a->author?->name ?? 'ADT Sports' }}</span>
        <span class="sep"></span>
      @endunless
      <span>{{ $a->formatted_date }}</span>
      <span class="sep"></span>
      <span>{{ $a->read_time }} read</span>
    </div>
  </div>
  <div class="cr-thumb" style="background:{{ $a->cover_bg }}">
    @if($a->cover_image)<img src="{{ $a->cover_image }}" style="width:100%;height:100%;object-fit:cover" alt="{{ $a->title }}" loading="lazy" decoding="async">
    @else <x-cover-placeholder :article="$a" /> @endif
  </div>
</a>
