<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0d1f38">
    <title>@yield('title', 'Ruang Ujian') &middot; {{ config('app.name') }}</title>
    @include('partials.aset')
    <style>
        /*
            Ruang ujian siswa.

            Tampilannya sengaja dibuat tenang dan lapang: yang dikerjakan siswa
            di sini adalah ujian, jadi tidak ada yang boleh bersaing perhatian
            dengan badan soal. Warna kuat disimpan untuk tiga hal saja — sisa
            waktu, opsi yang sedang dipilih, dan tombol mengumpulkan.

            Ukuran sentuh dijaga lapang karena sebagian siswa mengerjakan lewat
            ponsel atau tablet, bukan komputer sekolah.
        */
        :root {
            --biru-tua:#0d1f38; --biru:#1b3a63; --biru-muda:#2f6fb5;
            --hijau:#00a86b; --hijau-gelap:#008d59; --hijau-lembut:#e7f7f0;
            --kuning:#f0a500; --merah:#dc3545;
            --dasar:#f3f6fb; --kartu:#fff; --garis:#e3e9f2;
            --teks:#16243a; --teks-lembut:#64748b;
            --bayang:0 1px 2px rgba(16,32,64,.05), 0 8px 24px -12px rgba(16,32,64,.15);
        }

        body {
            background:var(--dasar);
            color:var(--teks);
            font-family:'Segoe UI',system-ui,-apple-system,'Helvetica Neue',Arial,sans-serif;
            -webkit-text-size-adjust:100%;
        }

        /* ---------------- Bilah atas ---------------- */
        .bilah-atas {
            background:linear-gradient(120deg,var(--biru-tua) 0%,var(--biru) 100%);
            box-shadow:0 1px 0 rgba(255,255,255,.06), 0 2px 16px rgba(13,31,56,.25);
        }
        .bilah-atas .merek { color:#fff; font-weight:700; letter-spacing:-.01em; }
        .bilah-atas .merek i { color:var(--hijau); }
        .bilah-atas .nama-pengguna { color:#c8d6e8; }
        .bilah-atas .btn-keluar {
            border-color:rgba(255,255,255,.25); color:#e6edf7;
        }
        .bilah-atas .btn-keluar:hover { background:rgba(255,255,255,.12); border-color:rgba(255,255,255,.4); color:#fff; }

        /* ---------------- Kartu ---------------- */
        .card {
            border:1px solid var(--garis);
            border-radius:.85rem;
            box-shadow:var(--bayang);
        }
        .card-header {
            background:var(--kartu);
            border-bottom:1px solid var(--garis);
            font-weight:600;
            padding:.85rem 1.1rem;
        }
        .card-body { padding:1.1rem; }

        /* ---------------- Badan soal ---------------- */
        .soal-body { font-size:1.05rem; line-height:1.75; }
        .soal-body img { max-width:100%; height:auto; }

        /* ---------------- Opsi jawaban ---------------- */
        /*
            Seluruh baris opsi dapat diketuk, bukan hanya lingkaran radionya —
            sasaran sekecil itu menyulitkan di layar sentuh.

            Keadaan terpilih ditandai dua kali: lewat :has() dan lewat kelas
            .opsi-terpilih yang dipasang JavaScript. Kelas itu bukan
            pengulangan yang sia-sia — :has() baru dikenal Chrome 105, Safari
            15.4, dan Firefox 121, sedangkan komputer sekolah kerap memakai
            peramban yang jauh lebih lama. Bagi siswa, opsi yang tidak terlihat
            terpilih berarti keraguan di tengah ujian.
        */
        .opsi-label {
            border:1.5px solid var(--garis) !important;
            border-radius:.7rem !important;
            padding:.85rem 1rem !important;
            cursor:pointer;
            transition:border-color .12s, background-color .12s, box-shadow .12s;
            background:var(--kartu);
        }
        .opsi-label:hover { border-color:#b9cbe4 !important; background:#fbfdff; }
        .opsi-label:has(input:checked),
        .opsi-label.opsi-terpilih {
            border-color:var(--hijau) !important;
            background:var(--hijau-lembut) !important;
            box-shadow:0 0 0 3px rgba(0,168,107,.12);
        }
        .opsi-label .form-check-input { width:1.15rem; height:1.15rem; }
        .opsi-label .form-check-input:checked { background-color:var(--hijau); border-color:var(--hijau); }
        .opsi-huruf {
            flex:none; width:1.9rem; height:1.9rem; border-radius:.5rem;
            display:grid; place-items:center; font-weight:700; font-size:.85rem;
            background:#eef3f9; color:#3f5877;
        }
        .opsi-label:has(input:checked) .opsi-huruf,
        .opsi-label.opsi-terpilih .opsi-huruf { background:var(--hijau); color:#fff; }

        /* ---------------- Identitas peserta pada bilah atas ---------------- */
        .peserta-bilah { min-width:0; line-height:1.25; }
        .peserta-bilah .nama { color:#fff; font-weight:600; font-size:.92rem; }
        .peserta-bilah .rincian { color:#a9bdd6; font-size:.74rem; }

        /* ---------------- Sisa waktu ---------------- */
        .jam-ujian {
            font-family:ui-monospace,SFMono-Regular,'Cascadia Mono',Consolas,monospace;
            font-weight:700; font-size:1.05rem; letter-spacing:.04em;
            padding:.4rem .8rem; border-radius:.6rem;
            background:rgba(255,255,255,.14); color:#fff;
            border:1px solid rgba(255,255,255,.2);
        }
        .jam-ujian.waktu-hampir { background:var(--kuning); border-color:var(--kuning); color:#3d2a00; }
        .jam-ujian.text-bg-danger { animation:denyut 1.1s ease-in-out infinite; }
        @keyframes denyut { 50% { opacity:.55; } }

        /* ---------------- Tombol nomor soal ---------------- */
        .nav-nomor {
            width:2.7rem; height:2.7rem; padding:0;
            border-radius:.6rem; font-weight:600; font-size:.9rem;
            border-width:1.5px;
        }
        .nav-nomor.border-3 {
            box-shadow:0 0 0 3px rgba(47,111,181,.25);
            border-color:var(--biru-muda) !important;
        }

        /* ---------------- Lencana keadaan ---------------- */
        .pil {
            display:inline-flex; align-items:center; gap:.35rem;
            padding:.28rem .7rem; border-radius:2rem;
            font-size:.78rem; font-weight:600; line-height:1.2;
        }
        .pil-hijau { background:var(--hijau-lembut); color:#046c48; }
        .pil-kuning { background:#fff4dc; color:#8a5a00; }
        .pil-abu { background:#eef2f7; color:#516074; }
        .pil-merah { background:#fdeaec; color:#9b1c28; }

        /* ---------------- Tombol utama ---------------- */
        .btn-ujian {
            background:var(--hijau); border-color:var(--hijau); color:#fff; font-weight:600;
        }
        .btn-ujian:hover, .btn-ujian:focus { background:var(--hijau-gelap); border-color:var(--hijau-gelap); color:#fff; }

        /* ---------------- Titik berdenyut ---------------- */
        .titik-hidup {
            width:.55rem; height:.55rem; border-radius:50%; background:var(--hijau);
            display:inline-block; box-shadow:0 0 0 0 rgba(0,168,107,.6);
            animation:pancar 1.8s infinite;
        }
        @keyframes pancar {
            70% { box-shadow:0 0 0 .5rem rgba(0,168,107,0); }
            100% { box-shadow:0 0 0 0 rgba(0,168,107,0); }
        }

        @media (max-width: 575.98px) {
            /* Bilah atas dirapatkan ke tepi: pada layar 360 px, tepi yang
               lapang membuat sisa waktu tampak menggantung di tengah,
               bukan di pojok. */
            .bilah-atas { padding-left:.6rem !important; padding-right:.6rem !important; }
            .bilah-atas .container-fluid { padding-left:0; padding-right:0; }
            .jam-ujian { font-size:1rem; padding:.35rem .6rem; }

            .card-body { padding:.9rem; }
            .soal-body { font-size:1rem; }
            .nav-nomor { width:2.5rem; height:2.5rem; }
        }
    </style>
    @include('partials.gaya-soal')
    @stack('head')
</head>
<body>

<nav class="bilah-atas sticky-top px-3 py-2">
    <div class="container-fluid d-flex align-items-center gap-3" style="max-width:1400px">
        <span class="merek d-flex align-items-center gap-2 flex-shrink-0">
            <i class="bi bi-mortarboard-fill fs-5"></i>
            <span class="d-none d-sm-inline">{{ config('app.name') }}</span>
        </span>

        @yield('navbar')

        {{-- Selama ujian berlangsung, blok ini disembunyikan seluruhnya:
             halaman pengerjaan menyusun sendiri bilah atasnya agar identitas
             siswa dan sisa waktu muat berdampingan di layar ponsel. --}}
        @unless (View::hasSection('sembunyikan-keluar'))
            <div class="dropdown ms-auto">
                <button class="btn btn-sm btn-outline-light btn-keluar d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                    {{-- Tata letak ini hanya dipakai halaman peserta, jadi yang
                         ditampilkan pesertanya — bukan siapa pun yang kebetulan
                         juga sedang masuk di peramban yang sama. --}}
                    <i class="bi bi-person-circle"></i>
                    <span class="d-none d-md-inline text-truncate" style="max-width:12rem">
                        {{ \App\Support\Pengguna::siswa()?->nama_siswa ?? \App\Support\Pengguna::nama() }}
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="px-3 py-2 small text-muted border-bottom">Masuk sebagai <strong>Siswa</strong></li>
                    <li>
                        <a class="dropdown-item {{ request()->routeIs('siswa.profil') ? 'active' : '' }}"
                           href="{{ route('siswa.profil') }}">
                            <i class="bi bi-person-gear me-2"></i>Profil Saya
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i>Keluar
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        @endunless
    </div>
</nav>

<main class="container-fluid py-3 py-lg-4" style="max-width:1400px">
    @include('partials.flash')
    @yield('content')
</main>

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
@include('partials.select2')
@stack('scripts')
</body>
</html>
