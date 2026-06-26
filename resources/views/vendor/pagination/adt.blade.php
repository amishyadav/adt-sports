@if ($paginator->hasPages())
    <nav class="adt-pagination" aria-label="Pagination">
        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <span class="pg-link pg-disabled" aria-disabled="true" aria-hidden="true">&lsaquo;</span>
        @else
            <a class="pg-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">&lsaquo;</a>
        @endif

        {{-- Numbers — $elements is only provided by LengthAwarePaginator; guard
             it so a simplePaginate() (which omits it) renders prev/next safely. --}}
        @foreach (($elements ?? []) as $element)
            @if (is_string($element))
                <span class="pg-link pg-dots" aria-disabled="true">{{ $element }}</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="pg-link pg-active" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="pg-link" href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <a class="pg-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">&rsaquo;</a>
        @else
            <span class="pg-link pg-disabled" aria-disabled="true" aria-hidden="true">&rsaquo;</span>
        @endif
    </nav>
@endif
