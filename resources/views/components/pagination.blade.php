@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();

    $start = max(1, $current - 2);
    $end = min($last, $current + 2);
@endphp

@if ($paginator->total() > 0)
    <nav class="sim-pagination" aria-label="Navigasi halaman">
        <div class="sim-pagination-info">
            Menampilkan
            <strong>{{ $paginator->firstItem() }}</strong>
            sampai
            <strong>{{ $paginator->lastItem() }}</strong>
            dari
            <strong>{{ $paginator->total() }}</strong>
            data
        </div>

        @if ($paginator->hasPages())
            <div class="sim-pagination-links">

                {{-- Tombol sebelumnya --}}
                @if ($paginator->onFirstPage())
                    <span class="sim-page-link is-disabled">
                        ‹ Sebelumnya
                    </span>
                @else
                    <a class="sim-page-link"
                       href="{{ $paginator->previousPageUrl() }}"
                       rel="prev">
                        ‹ Sebelumnya
                    </a>
                @endif

                {{-- Halaman pertama --}}
                @if ($start > 1)
                    <a class="sim-page-link"
                       href="{{ $paginator->url(1) }}">
                        1
                    </a>

                    @if ($start > 2)
                        <span class="sim-page-ellipsis">…</span>
                    @endif
                @endif

                {{-- Nomor halaman --}}
                @for ($page = $start; $page <= $end; $page++)
                    @if ($page == $current)
                        <span class="sim-page-link is-active"
                              aria-current="page">
                            {{ $page }}
                        </span>
                    @else
                        <a class="sim-page-link"
                           href="{{ $paginator->url($page) }}">
                            {{ $page }}
                        </a>
                    @endif
                @endfor

                {{-- Halaman terakhir --}}
                @if ($end < $last)
                    @if ($end < $last - 1)
                        <span class="sim-page-ellipsis">…</span>
                    @endif

                    <a class="sim-page-link"
                       href="{{ $paginator->url($last) }}">
                        {{ $last }}
                    </a>
                @endif

                {{-- Tombol berikutnya --}}
                @if ($paginator->hasMorePages())
                    <a class="sim-page-link"
                       href="{{ $paginator->nextPageUrl() }}"
                       rel="next">
                        Berikutnya ›
                    </a>
                @else
                    <span class="sim-page-link is-disabled">
                        Berikutnya ›
                    </span>
                @endif

            </div>
        @endif
    </nav>
@endif
