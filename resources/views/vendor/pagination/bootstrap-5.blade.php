@if ($paginator->total() > 0)
    <div class="ds-pagination">
        <div class="table-info">
            Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }} entries
        </div>

        @if ($paginator->hasPages())
        <nav aria-label="{{ __('Pagination Navigation') }}">
            <ul class="pagination pagination-sm mb-0">
                @if ($paginator->onFirstPage())
                    <li class="page-item disabled" aria-disabled="true">
                        <a class="page-link" href="#" aria-label="Previous">Previous</a>
                    </li>
                @else
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous">Previous</a>
                    </li>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <li class="page-item active" aria-current="page"><a class="page-link" href="#">{{ $page }}</a></li>
                            @else
                                <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next">Next</a>
                    </li>
                @else
                    <li class="page-item disabled" aria-disabled="true">
                        <a class="page-link" href="#" aria-label="Next">Next</a>
                    </li>
                @endif
            </ul>
        </nav>
        @endif
    </div>
@endif
