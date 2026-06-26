{{-- rel=prev/next hints for paginated list pages. Pass a paginator as $paginator. --}}
@isset($paginator)
    @if($paginator instanceof \Illuminate\Contracts\Pagination\Paginator)
        @push('head_links')
            @if($paginator->currentPage() > 1)
                <link rel="prev" href="{{ $paginator->previousPageUrl() }}">
            @endif
            @if($paginator->hasMorePages())
                <link rel="next" href="{{ $paginator->nextPageUrl() }}">
            @endif
        @endpush
    @endif
@endisset
