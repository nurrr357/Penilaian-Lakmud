<nav class="pagination" aria-label="Halaman tabel">
<p class="pagination__label" role="status">Menampilkan {{ $listing->firstItem() ?? 0 }} sampai {{ $listing->lastItem() ?? 0 }} dari {{ $listing->total() }} data</p>
@if($listing->onFirstPage())<span class="pagination__item is-disabled">Sebelumnya</span>@else<a class="pagination__item" href="{{ $listing->previousPageUrl() }}" rel="prev">Sebelumnya</a>@endif
<span class="pagination__item is-current" aria-current="page">Halaman {{ $listing->currentPage() }} dari {{ $listing->lastPage() }}</span>
@if($listing->hasMorePages())<a class="pagination__item" href="{{ $listing->nextPageUrl() }}" rel="next">Berikutnya</a>@else<span class="pagination__item is-disabled">Berikutnya</span>@endif
</nav>
