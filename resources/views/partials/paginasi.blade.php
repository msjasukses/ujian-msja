{{--
    Tampilan paginasi seluruh aplikasi (dipasang di AppServiceProvider).

    Bawaan Laravel memakai kelas Tailwind, sedangkan aplikasi ini memakai
    Bootstrap — tanpa gayanya, ikon panah SVG tampil sebesar layar. Teksnya
    ditulis langsung dalam bahasa Indonesia karena aplikasi tidak memuat
    berkas terjemahan.
--}}
@if ($paginator->hasPages())
    <nav class="d-flex flex-wrap gap-2 justify-content-between align-items-center" aria-label="Navigasi halaman">
        <div class="small text-muted">
            Menampilkan <span class="fw-semibold">{{ $paginator->firstItem() }}</span>–<span class="fw-semibold">{{ $paginator->lastItem() }}</span>
            dari <span class="fw-semibold">{{ $paginator->total() }}</span> data
        </div>

        <ul class="pagination pagination-sm mb-0 flex-wrap">
            @if ($paginator->onFirstPage())
                <li class="page-item disabled" aria-disabled="true">
                    <span class="page-link" aria-label="Sebelumnya"><i class="bi bi-chevron-left"></i></span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Sebelumnya"><i class="bi bi-chevron-left"></i></a>
                </li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                        @else
                            <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Berikutnya"><i class="bi bi-chevron-right"></i></a>
                </li>
            @else
                <li class="page-item disabled" aria-disabled="true">
                    <span class="page-link" aria-label="Berikutnya"><i class="bi bi-chevron-right"></i></span>
                </li>
            @endif
        </ul>
    </nav>
@endif
