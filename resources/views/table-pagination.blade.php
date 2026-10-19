<nav class="pagination" aria-label="Halaman tabel">
<p role="status">Menampilkan {{ $listing->firstItem() ?? 0 }} sampai {{ $listing->lastItem() ?? 0 }} dari {{ $listing->total() }} data</p>
<div class="actions">
@if($listing->onFirstPage())<span>Sebelumnya</span>@else<a class="button secondary" href="{{ $listing->previousPageUrl() }}">Sebelumnya</a>@endif
<span aria-current="page">Halaman {{ $listing->currentPage() }} dari {{ $listing->lastPage() }}</span>
@if($listing->hasMorePages())<a class="button secondary" href="{{ $listing->nextPageUrl() }}">Berikutnya</a>@else<span>Berikutnya</span>@endif
</div></nav>
