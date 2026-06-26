@props(['article'])
@php
    // Polished fallback when an article has no cover image: the category's
    // chosen icon (or its slug default) over the article's gradient.
    $icon = $article->category?->display_icon ?? 'fa-newspaper';
@endphp
<div class="cover-ph" aria-hidden="true">
    <i class="fa-solid {{ $icon }}"></i>
    @if($article->category)<span class="cover-ph-cat">{{ $article->category->name }}</span>@endif
</div>
