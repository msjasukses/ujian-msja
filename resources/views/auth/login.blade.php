<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk &middot; {{ config('app.name') }}</title>
    @include('partials.aset')
    <style>
        :root {
            --navy:#0d2440; --navy-2:#164e8c; --hijau:#00a86b; --hijau-gelap:#008d59;
            --teks:#12233b; --teks-lembut:#6b7b90; --garis:#d8e0ea;
        }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; background:#f4f6fa; color:var(--teks);
               font-family:'Segoe UI',system-ui,-apple-system,'Helvetica Neue',Arial,sans-serif; }

        .layar { display:flex; min-height:100vh; }

        /* Panel kiri — identitas aplikasi. */
        /* overflow:hidden mengurung bentuk hias di ::before/::after — tanpa itu
           lingkarannya meluber keluar dan halaman jadi bisa digulir mendatar. */
        .panel-kiri { flex:1 1 58%; position:relative; overflow:hidden; padding:3.25rem 3.5rem; color:#fff;
                      background:linear-gradient(145deg,#0b1f3a 0%,#123f74 55%,#1a5aa0 100%);
                      display:flex; flex-direction:column; }
        .panel-kiri .logo { width:3.25rem; height:3.25rem; border-radius:.9rem;
                            background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.18);
                            display:grid; place-items:center; font-size:1.4rem; font-weight:700; }
        .panel-kiri .isi { margin:auto 0; max-width:30rem; }
        .panel-kiri h1 { font-size:3.1rem; line-height:1.14; font-weight:800; letter-spacing:-.02em; margin:0 0 1.5rem; }
        .panel-kiri p { font-size:1.02rem; line-height:1.6; color:rgba(255,255,255,.72); margin:0; }
        .panel-kiri .kaki { font-size:.82rem; color:rgba(255,255,255,.5); }

        /* Tiga kalimat singkat tentang apa yang bisa dikerjakan di sini —
           menjawab "aplikasi apa ini" bagi siswa yang baru pertama masuk. */
        .daftar-guna { list-style:none; margin:2rem 0 0; padding:0; display:grid; gap:1rem; }
        .daftar-guna li { display:flex; gap:.85rem; align-items:flex-start;
                          font-size:.92rem; line-height:1.5; color:rgba(255,255,255,.8); }
        .daftar-guna i { flex:none; width:2.1rem; height:2.1rem; border-radius:.6rem;
                         display:grid; place-items:center; font-size:1rem; color:#fff;
                         background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.16); }

        /* Bentuk lembut di latar panel kiri supaya bidang gelapnya tidak rata
           kosong, tanpa mengganggu keterbacaan tulisan di atasnya. */
        .panel-kiri::before, .panel-kiri::after {
            content:''; position:absolute; border-radius:50%; pointer-events:none;
            background:radial-gradient(circle, rgba(255,255,255,.09) 0%, rgba(255,255,255,0) 70%);
        }
        .panel-kiri::before { width:28rem; height:28rem; right:-8rem; top:-10rem; }
        .panel-kiri::after  { width:22rem; height:22rem; left:-9rem; bottom:-8rem; }
        .panel-kiri > * { position:relative; z-index:1; }

        /* Panel kanan — formulir. */
        .panel-kanan { flex:1 1 42%; background:#f6f8fb; display:grid; place-items:center; padding:2.5rem 1.5rem; }
        .kotak-form { width:100%; max-width:27rem; }
        .kotak-form h2 { font-size:1.75rem; font-weight:800; color:#0f2a4a; margin:0 0 .35rem; }
        .kotak-form .sub { color:var(--hijau); font-size:.9rem; margin:0 0 1.75rem; }

        .kolom { margin-bottom:1.1rem; }
        .kolom label.judul { display:block; font-size:.875rem; font-weight:600; margin-bottom:.45rem; }
        .kolom .isian { width:100%; padding:.8rem 1rem; font-size:.95rem; background:#fff;
                        border:1px solid var(--garis); border-radius:.65rem; outline:none;
                        transition:border-color .15s, box-shadow .15s; }
        .kolom .isian::placeholder { color:#9aa8ba; }
        .kolom .isian:focus { border-color:var(--hijau); box-shadow:0 0 0 3px rgba(0,168,107,.15); }
        .kolom .isian.salah { border-color:#dc3545; }
        .kolom .petunjuk { display:block; margin-top:.4rem; font-size:.8rem; line-height:1.45; color:var(--hijau); }
        .kolom .galat { display:block; margin-top:.4rem; font-size:.8rem; color:#dc3545; }

        .sandi-bungkus { position:relative; }
        .sandi-bungkus .isian { padding-right:2.9rem; }
        .tombol-mata { position:absolute; top:50%; right:.35rem; transform:translateY(-50%);
                       background:none; border:0; padding:.45rem .6rem; color:var(--teks-lembut); cursor:pointer; }

        .ingat { display:flex; align-items:center; gap:.55rem; font-size:.85rem; margin:0 0 1.5rem; }
        .ingat input { width:1rem; height:1rem; accent-color:var(--hijau); }

        .tombol-masuk { width:100%; padding:.9rem 1rem; font-size:1rem; font-weight:700; color:#fff;
                        background:var(--hijau); border:0; border-radius:.65rem; cursor:pointer;
                        transition:background .15s; }
        .tombol-masuk:hover { background:var(--hijau-gelap); }
        .tombol-masuk:active { transform:translateY(1px); }
        .tombol-masuk:focus-visible { outline:3px solid rgba(0,168,107,.35); outline-offset:2px; }

        .kaki-form { margin:1.35rem 0 0; text-align:center; font-size:.85rem; color:var(--hijau); }

        @media (max-width: 991.98px) {
            .layar { flex-direction:column; }
            .panel-kiri { padding:2.25rem 1.75rem; }
            .panel-kiri .isi { margin:1.75rem 0; }
            .panel-kiri h1 { font-size:2rem; margin-bottom:1rem; }
            .panel-kiri .kaki { display:none; }
            .daftar-guna { display:none; }
            .panel-kanan { padding:2rem 1.5rem 3rem; }
        }
    </style>
</head>
<body>

<div class="layar">
    <div class="panel-kiri">
        <div class="logo">{{ mb_strtoupper(mb_substr(config('app.name'), 0, 1)) }}</div>

        <div class="isi">
            <h1>Selamat datang di {{ config('app.name') }}.</h1>
            <p>Bank soal, pelaksanaan ujian, sampai laporan nilai dalam satu sistem terpadu.</p>

            <ul class="daftar-guna">
                <li><i class="bi bi-journal-text"></i><span>Bank soal lima jenis, lengkap dengan rumus, teks Arab, dan gambar</span></li>
                <li><i class="bi bi-display"></i><span>Ujian dikerjakan langsung di layar, jawaban tersimpan otomatis</span></li>
                <li><i class="bi bi-graph-up"></i><span>Nilai, statistik, dan analisis butir soal keluar seketika</span></li>
            </ul>
        </div>

        <div class="kaki">&copy; {{ date('Y') }} {{ config('app.name') }}</div>
    </div>

    <div class="panel-kanan">
        <div class="kotak-form">
            <h2>Masuk ke akun Anda</h2>
            <p class="sub">Untuk admin, guru, dan siswa sekolah.</p>

            @include('partials.flash')

            <form method="POST" action="{{ route('login') }}" autocomplete="off">
                @csrf

                <div class="kolom">
                    <label class="judul" for="username">Email / NIP / NISN</label>
                    <input type="text" name="username" id="username" value="{{ old('username') }}"
                           class="isian @error('username') salah @enderror"
                           placeholder="Masukkan email, NIP, atau NISN" required autofocus>
                    @error('username')
                        <span class="galat">{{ $message }}</span>
                    @else
                        <span class="petunjuk">Guru/Siswa memakai NIP/NISN dan kata sandi yang sama dengan
                            aplikasi Data Center.</span>
                    @enderror
                </div>

                <div class="kolom">
                    <label class="judul" for="password">Kata Sandi</label>
                    <div class="sandi-bungkus">
                        <input type="password" name="password" id="password"
                               class="isian @error('password') salah @enderror"
                               placeholder="Masukkan kata sandi" required>
                        <button class="tombol-mata" type="button" onclick="lihatSandi(this)" tabindex="-1"
                                aria-label="Tampilkan kata sandi">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    @error('password')<span class="galat">{{ $message }}</span>@enderror
                </div>

                <label class="ingat">
                    <input type="checkbox" name="remember" value="1">
                    <span>Ingat saya di perangkat ini</span>
                </label>

                <button class="tombol-masuk" type="submit">Login &rarr;</button>
            </form>

            <p class="kaki-form">Lupa kata sandi? Hubungi administrator sekolah.</p>
        </div>
    </div>
</div>

<script>
    function lihatSandi(tombol) {
        const input = document.getElementById('password');
        const tampil = input.type === 'password';
        input.type = tampil ? 'text' : 'password';
        tombol.querySelector('i').className = tampil ? 'bi bi-eye-slash' : 'bi bi-eye';
    }
</script>
</body>
</html>
