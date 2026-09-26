{{--
    Penyaring kelas di halaman detail laporan. Dipakai Statistik, Analisis
    Butir, Remidial, dan Pengayaan.

    Angka pada halamannya dihitung ulang dari kelas yang dipilih — bukan
    sekadar menyembunyikan baris — sehingga rata-rata, ketuntasan, dan daya
    pembeda butir benar-benar milik kelas itu.

    $ujian : ujian yang sedang dibuka
--}}
<form class="card mb-3">
    <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center">
        <label class="form-label mb-0 small fw-semibold" for="filterKelas">
            <i class="bi bi-people me-1"></i>Kelas
        </label>

        <div style="min-width:12rem">
            <x-pilih-kelas :ujian="$ujian" id="filterKelas" />
        </div>

        @if (request('rombongan_belajar_id'))
            <span class="small text-muted">
                Semua angka di halaman ini dihitung dari kelas yang dipilih saja.
            </span>
            <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-secondary ms-auto">
                <i class="bi bi-x-lg me-1"></i>Semua kelas
            </a>
        @endif
    </div>
</form>
