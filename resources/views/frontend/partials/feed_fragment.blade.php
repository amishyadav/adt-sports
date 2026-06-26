{{-- AJAX response for "Load more": the next page's rows followed by the next button.
     Returned by the listing controllers when the request carries ?partial=1.
       $articles   — the paginator slice for this page
       $rowCat     — optional forced category label (category pages)
       $hideAuthor — optional bool (author pages) --}}
@foreach($articles as $a)
@include('frontend.partials.article_row', ['a' => $a, 'rowCat' => $rowCat ?? null, 'hideAuthor' => $hideAuthor ?? false])
@endforeach
@include('frontend.partials.load_more', ['paginator' => $articles])
