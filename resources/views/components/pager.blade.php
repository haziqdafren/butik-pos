@props(['paginator'])

@if($paginator->hasPages())
    <div class="pagination">
        <div>Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}</div>
        <div class="row-actions">
            @if($paginator->onFirstPage())
                <span class="page-disabled">Sebelumnya</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}">Sebelumnya</a>
            @endif

            @if($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}">Berikutnya</a>
            @else
                <span class="page-disabled">Berikutnya</span>
            @endif
        </div>
    </div>
@endif
