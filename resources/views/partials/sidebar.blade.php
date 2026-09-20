@php use App\Support\Pengguna; @endphp
<div class="sidebar" id="sidebar">
    <div class="brand d-flex align-items-center justify-content-between">
        <span><i class="bi bi-clipboard-check-fill me-2"></i>Aplikasi Ujian</span>
        <button type="button" class="btn btn-sm text-white d-lg-none p-0" onclick="tutupSidebar()" aria-label="Tutup menu">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <nav class="nav flex-column py-2" id="sidebarNav">

        <a class="nav-link px-3 py-2 {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
            <i class="bi bi-speedometer2 me-2"></i>Dashboard
        </a>

        <div class="menu-group-title">Penyusunan Soal</div>

        <a class="nav-link px-3 py-2 {{ request()->routeIs('topik.*') ? 'active' : '' }}" href="{{ route('topik.index') }}">
            <i class="bi bi-diagram-3 me-2"></i>Topik
        </a>

        @php $open = request()->routeIs('soal.*'); @endphp
        <button class="menu-toggle nav-link px-3 py-2 d-flex align-items-center {{ $open ? '' : 'collapsed' }}"
                data-bs-toggle="collapse" data-bs-target="#grpSoal">
            <i class="bi bi-journal-text me-2"></i>Bank Soal <i class="bi bi-chevron-down ms-auto chev"></i>
        </button>
        <div class="collapse menu-sub {{ $open ? 'show' : '' }}" id="grpSoal">
            <a class="nav-link px-3 py-2 {{ request()->routeIs('soal.index') || request()->routeIs('soal.show') ? 'active' : '' }}"
               href="{{ route('soal.index') }}"><i class="bi bi-list-ul me-2"></i>Daftar Soal</a>
            <a class="nav-link px-3 py-2 {{ request()->routeIs('soal.create') || request()->routeIs('soal.edit') ? 'active' : '' }}"
               href="{{ route('soal.create') }}"><i class="bi bi-plus-square me-2"></i>Input Soal</a>
            <a class="nav-link px-3 py-2 {{ request()->routeIs('soal.import.*') ? 'active' : '' }}"
               href="{{ route('soal.import.form') }}"><i class="bi bi-upload me-2"></i>Import Word / Excel</a>
        </div>

        <a class="nav-link px-3 py-2 {{ request()->routeIs('paket-soal.*') ? 'active' : '' }}" href="{{ route('paket-soal.index') }}">
            <i class="bi bi-check2-square me-2"></i>Pemilihan Soal Diujikan
        </a>

        <div class="menu-group-title">Pelaksanaan</div>

        <a class="nav-link px-3 py-2 {{ request()->routeIs('ujian.*') ? 'active' : '' }}" href="{{ route('ujian.index') }}">
            <i class="bi bi-calendar-check me-2"></i>Registrasi Ujian
        </a>

        <a class="nav-link px-3 py-2 {{ request()->routeIs('monitoring.*') ? 'active' : '' }}" href="{{ route('monitoring.index') }}">
            <i class="bi bi-display me-2"></i>Monitoring Ujian
        </a>

        <div class="menu-group-title">Hasil &amp; Laporan</div>

        @php $open = request()->routeIs('laporan.*'); @endphp
        <button class="menu-toggle nav-link px-3 py-2 d-flex align-items-center {{ $open ? '' : 'collapsed' }}"
                data-bs-toggle="collapse" data-bs-target="#grpLaporan">
            <i class="bi bi-file-earmark-bar-graph me-2"></i>Hasil &amp; Laporan Ujian <i class="bi bi-chevron-down ms-auto chev"></i>
        </button>
        <div class="collapse menu-sub {{ $open ? 'show' : '' }}" id="grpLaporan">
            <a class="nav-link px-3 py-2 {{ request()->routeIs('laporan.nilai.*') ? 'active' : '' }}"
               href="{{ route('laporan.nilai.index') }}"><i class="bi bi-card-checklist me-2"></i>Daftar Nilai Ujian</a>
            <a class="nav-link px-3 py-2 {{ request()->routeIs('laporan.statistik.*') ? 'active' : '' }}"
               href="{{ route('laporan.statistik.index') }}"><i class="bi bi-bar-chart-line me-2"></i>Statistik Ujian</a>
            <a class="nav-link px-3 py-2 {{ request()->routeIs('laporan.analisis.*') ? 'active' : '' }}"
               href="{{ route('laporan.analisis.index') }}"><i class="bi bi-graph-up me-2"></i>Analisis Butir Soal</a>
            <a class="nav-link px-3 py-2 {{ request()->routeIs('laporan.remidial.*') ? 'active' : '' }}"
               href="{{ route('laporan.remidial.index') }}"><i class="bi bi-arrow-repeat me-2"></i>Remidial</a>
            <a class="nav-link px-3 py-2 {{ request()->routeIs('laporan.pengayaan.*') ? 'active' : '' }}"
               href="{{ route('laporan.pengayaan.index') }}"><i class="bi bi-stars me-2"></i>Pengayaan</a>
        </div>

        {{-- Data Center hanya untuk admin/operator: isinya data seluruh sekolah
             (siswa, guru, wali kelas), di luar keperluan guru menyusun soal. --}}
        @if (Pengguna::isAdmin())
        <div class="menu-group-title">Data Referensi</div>

        @php $open = request()->routeIs('referensi.*'); @endphp
        <button class="menu-toggle nav-link px-3 py-2 d-flex align-items-center {{ $open ? '' : 'collapsed' }}"
                data-bs-toggle="collapse" data-bs-target="#grpReferensi">
            <i class="bi bi-database me-2"></i>Data Center <i class="bi bi-chevron-down ms-auto chev"></i>
        </button>
        <div class="collapse menu-sub {{ $open ? 'show' : '' }}" id="grpReferensi">
            <a class="nav-link px-3 py-2 {{ request()->routeIs('referensi.siswa') ? 'active' : '' }}"
               href="{{ route('referensi.siswa') }}"><i class="bi bi-people me-2"></i>Data Siswa</a>
            <a class="nav-link px-3 py-2 {{ request()->routeIs('referensi.guru') ? 'active' : '' }}"
               href="{{ route('referensi.guru') }}"><i class="bi bi-person-badge me-2"></i>Data Guru</a>
            <a class="nav-link px-3 py-2 {{ request()->routeIs('referensi.wali-kelas') ? 'active' : '' }}"
               href="{{ route('referensi.wali-kelas') }}"><i class="bi bi-person-check me-2"></i>Wali Kelas</a>
            <a class="nav-link px-3 py-2 {{ request()->routeIs('referensi.guru-mapel') ? 'active' : '' }}"
               href="{{ route('referensi.guru-mapel') }}"><i class="bi bi-easel me-2"></i>Guru Mata Pelajaran</a>
            <a class="nav-link px-3 py-2 {{ request()->routeIs('referensi.mapel') ? 'active' : '' }}"
               href="{{ route('referensi.mapel') }}"><i class="bi bi-journal-bookmark me-2"></i>Mata Pelajaran</a>
            <a class="nav-link px-3 py-2 {{ request()->routeIs('referensi.tingkat-kelas') ? 'active' : '' }}"
               href="{{ route('referensi.tingkat-kelas') }}"><i class="bi bi-stack me-2"></i>Tingkat Kelas</a>
            <a class="nav-link px-3 py-2 {{ request()->routeIs('referensi.tahun-ajaran') ? 'active' : '' }}"
               href="{{ route('referensi.tahun-ajaran') }}"><i class="bi bi-calendar-range me-2"></i>Tahun Ajaran</a>
        </div>
        @endif

        @if (Pengguna::isAdmin())
            <div class="menu-group-title">Sistem</div>

            <a class="nav-link px-3 py-2 {{ request()->routeIs('log-login.*') ? 'active' : '' }}" href="{{ route('log-login.index') }}">
                <i class="bi bi-shield-lock me-2"></i>Log Login
            </a>
            <a class="nav-link px-3 py-2 {{ request()->routeIs('pengguna.*') ? 'active' : '' }}" href="{{ route('pengguna.index') }}">
                <i class="bi bi-person-gear me-2"></i>Pengguna
            </a>
        @endif

        <div class="py-3"></div>
    </nav>
</div>
