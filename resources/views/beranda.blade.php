<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beranda &middot; {{ config('app.name') }}</title>
    @include('partials.aset')
    <style>
        /*
            Beranda sengaja memakai bahasa rupa yang sama dengan halaman login
            — warna, lengkung, dan jarak yang serupa — supaya perpindahan di
            antara keduanya tidak terasa seperti berpindah aplikasi.
        */
        :root {
            --navy:#0d2440; --hijau:#00a86b; --hijau-gelap:#008d59;
            --teks:#12233b; --teks-lembut:#6b7b90; --garis:#d8e0ea;
        }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; color:var(--teks); display:flex; flex-direction:column;
               font-family:'Segoe UI',system-ui,-apple-system,'Helvetica Neue',Arial,sans-serif;
               background:linear-gradient(145deg,#0b1f3a 0%,#123f74 55%,#1a5aa0 100%); }

        .bilah { display:flex; align-items:center; gap:.85rem; padding:1.5rem 2.5rem; color:#fff; }
        .bilah .logo { width:2.75rem; height:2.75rem; border-radius:.8rem; flex:none;
                       background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.18);
                       display:grid; place-items:center; font-size:1.2rem; font-weight:700; }
        .bilah .nama { font-weight:700; letter-spacing:-.01em; }
        .bilah .sekolah { font-size:.82rem; color:rgba(255,255,255,.65); }
        .bilah .kanan { margin-left:auto; }

        .tombol-masuk { display:inline-flex; align-items:center; gap:.5rem; padding:.7rem 1.4rem;
                        font-size:.95rem; font-weight:700; color:#fff; text-decoration:none;
                        background:var(--hijau); border-radius:.65rem; transition:background .15s; }
        .tombol-masuk:hover { background:var(--hijau-gelap); color:#fff; }

        .utama { flex:1 1 auto; display:grid; place-items:center; padding:1rem 2.5rem 3.5rem; }
        .isi { width:100%; max-width:62rem; color:#fff; }
        .isi h1 { font-size:3.1rem; line-height:1.14; font-weight:800; letter-spacing:-.02em; margin:0 0 1.25rem; }
        .isi .pengantar { font-size:1.05rem; line-height:1.65; color:rgba(255,255,255,.76); max-width:38rem; margin:0 0 2.25rem; }

        .kartu-guna { display:grid; grid-template-columns:repeat(3, 1fr); gap:1rem; margin-bottom:2.5rem; }
        .kartu { background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.14);
                 border-radius:1rem; padding:1.35rem; }
        .kartu i { display:grid; place-items:center; width:2.4rem; height:2.4rem; border-radius:.7rem;
                   font-size:1.1rem; margin-bottom:.85rem;
                   background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.16); }
        .kartu h2 { font-size:1rem; font-weight:700; margin:0 0 .4rem; }
        .kartu p { font-size:.88rem; line-height:1.55; color:rgba(255,255,255,.72); margin:0; }

        .ajakan { display:flex; flex-wrap:wrap; gap:.75rem; align-items:center; }
        .ajakan .catatan { font-size:.85rem; color:rgba(255,255,255,.6); }

        .kaki { padding:1.25rem 2.5rem; font-size:.82rem; color:rgba(255,255,255,.5);
                border-top:1px solid rgba(255,255,255,.1); }

        @media (max-width: 991.98px) {
            .bilah, .utama, .kaki { padding-left:1.5rem; padding-right:1.5rem; }
            .isi h1 { font-size:2.1rem; }
            .kartu-guna { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>

<header class="bilah">
    <div class="logo">{{ mb_strtoupper(mb_substr(config('app.name'), 0, 1)) }}</div>
    <div>
        <div class="nama">{{ config('app.name') }}</div>
        @if ($sekolah?->nama_sekolah)
            <div class="sekolah">{{ $sekolah->nama_sekolah }}</div>
        @endif
    </div>
    <div class="kanan">
        <a class="tombol-masuk" href="{{ route('login') }}">Masuk <i class="bi bi-arrow-right"></i></a>
    </div>
</header>

<main class="utama">
    <div class="isi">
        <h1>Ujian sekolah, dari bank soal sampai laporan nilai.</h1>
        <p class="pengantar">
            Guru menyusun soal dan menjadwalkan ujian, siswa mengerjakan langsung di layar,
            dan nilainya keluar begitu lembar dikumpulkan. Seluruhnya berjalan di server
            sekolah sendiri — tanpa internet pun ujian tetap bisa dilaksanakan.
        </p>

        <div class="kartu-guna">
            <div class="kartu">
                <i class="bi bi-journal-text"></i>
                <h2>Bank soal</h2>
                <p>Lima jenis soal, lengkap dengan rumus, teks Arab, gambar, serta lampiran audio dan video.</p>
            </div>
            <div class="kartu">
                <i class="bi bi-display"></i>
                <h2>Pelaksanaan</h2>
                <p>Jawaban tersimpan otomatis, waktu berjalan sendiri, dan pengawas memantau dari layar monitoring.</p>
            </div>
            <div class="kartu">
                <i class="bi bi-graph-up"></i>
                <h2>Hasil &amp; laporan</h2>
                <p>Nilai, statistik, analisis butir soal, remidial, dan pengayaan — siap diunduh sebagai Excel.</p>
            </div>
        </div>

        <div class="ajakan">
            <a class="tombol-masuk" href="{{ route('login') }}">Masuk ke aplikasi <i class="bi bi-arrow-right"></i></a>
            <span class="catatan">Admin memakai email, guru memakai NIP, siswa memakai NISN.</span>
        </div>
    </div>
</main>

<footer class="kaki">
    &copy; {{ date('Y') }} {{ $sekolah?->nama_sekolah ?: config('app.name') }}
</footer>

</body>
</html>
