{{--
    Lapisan pengawasan lembar ujian.

    Perlu dipahami batasnya sebelum membaca kodenya: halaman web tidak dapat
    melarang sistem operasi membelah layar, membuka jendela mengambang, atau
    menekan Ctrl+T. Penguncian di tingkat perangkat adalah tugas ExamBro.

    Yang dikerjakan berkas ini ada tiga:

      1. Mencegah apa yang memang dapat dicegah dari dalam halaman — tautan
         bertarget tab baru, window.open, klik roda tengah, menu klik kanan,
         menempel dari luar, dan pintasan papan ketik yang sampai ke halaman
         (di WebView ExamBro pintasan ini sampai; di Chrome biasa tidak).

      2. Mengenali yang tidak dapat dicegah — layar terbelah dan jendela
         mengambang dikenali dari ukuran jendela terhadap ukuran layar,
         sedangkan keluar dari peramban ujian dikenali dari halaman yang
         disembunyikan atau kehilangan fokus.

      3. Menutup soalnya begitu pelanggaran terjadi. Inilah pertahanan yang
         sebenarnya: membelah layar tetap bisa dilakukan, tetapi tidak lagi
         menguntungkan karena separuh layar berisi tirai, bukan soal.
         Hitung mundur tetap berjalan di baliknya.

    Skripnya sengaja tidak memakai sintaks yang lebih baru dari ES2017,
    mengikuti aturan yang sama dengan halaman pengerjaan.
--}}

{{--
    Gerbang layar penuh.

    Layar penuh tidak bisa dibawa dari tombol "Mulai Ujian" di halaman
    konfirmasi: peramban selalu melepasnya begitu halaman berpindah, dan di
    halaman baru permintaan layar penuh hanya diterima bila lahir dari
    tindakan pengguna. Karena itu halaman soal dibuka dengan gerbang ini —
    soalnya tertutup sampai tombol di bawah ditekan, dan tekanan itulah yang
    dipakai meminta layar penuh.
--}}
<div id="gerbangLayarPenuh" class="gerbang-layar-penuh" hidden>
    <div class="tirai-kotak">
        <div class="tirai-ikon"><i class="bi bi-arrows-fullscreen"></i></div>
        <h2 class="tirai-judul">Ujian dikerjakan dalam layar penuh</h2>
        <p class="tirai-pesan">
            Soal akan tampil setelah layar penuh aktif. Keluar dari layar penuh
            selama ujian terhitung pelanggaran.
        </p>
        <p class="tirai-identitas" id="gerbangIdentitas"></p>

        <button type="button" class="btn btn-lg btn-success fw-semibold mt-3 px-4" id="gerbangMasuk">
            <i class="bi bi-play-fill me-1"></i>Mulai dalam layar penuh
        </button>

        <p class="tirai-catatan" id="gerbangCatatan">Hitung mundur ujian sudah berjalan.</p>
    </div>
</div>

<div id="tiraiPengawas" class="tirai-pengawas" hidden>
    <div class="tirai-kotak">
        <div class="tirai-ikon"><i class="bi bi-shield-lock-fill"></i></div>

        {{-- Keadaannya dinyatakan lebih dulu, alasannya menyusul. Siswa yang
             panik perlu tahu apa yang terjadi pada ujiannya sebelum tahu
             mengapa. --}}
        <p class="tirai-status" id="tiraiStatus">Ujian Terblokir</p>
        <h2 class="tirai-judul" id="tiraiJudul">Pelanggaran terdeteksi</h2>
        <p class="tirai-pesan" id="tiraiPesan"></p>
        <p class="tirai-identitas" id="tiraiIdentitas"></p>
        <p class="tirai-hitung" id="tiraiHitung" hidden></p>

        <div class="d-flex flex-wrap justify-content-center gap-2 mt-3">
            <button type="button" class="btn btn-light fw-semibold" id="tiraiPenuh" hidden>
                <i class="bi bi-arrows-fullscreen me-1"></i>Layar penuh
            </button>
            <button type="button" class="btn btn-warning fw-semibold" id="tiraiLanjut">
                Saya mengerti, lanjutkan
            </button>

            {{-- Hanya muncul saat lembar terkunci. Tanpa ini peserta terpaku
                 pada layar yang tidak punya jalan keluar sama sekali, dan
                 satu-satunya caranya pergi adalah menutup peramban ujian —
                 yang justru terhitung pelanggaran lagi. --}}
            <a class="btn btn-outline-light fw-semibold" id="tiraiDashboard" href="{{ route('siswa.ujian.index') }}" hidden>
                <i class="bi bi-house-door me-1"></i>Kembali ke Ruang Ujian
            </a>
        </div>

        <p class="tirai-catatan" id="tiraiCatatan"></p>
    </div>
</div>

@push('head')
<style>
    /* Isi soal tidak boleh disalin; kolom essay tetap dapat disunting siswa. */
    .kartu-soal, .nav-nomor {
        -webkit-user-select:none; -ms-user-select:none; user-select:none;
        -webkit-touch-callout:none;
    }
    .essay-jawab, .essay-jawab * {
        -webkit-user-select:text; -ms-user-select:text; user-select:text;
    }

    .tirai-pengawas {
        position:fixed; inset:0; z-index:3000;
        display:grid; place-items:center; padding:1.25rem;
        background:rgba(9,20,36,.985);
        -webkit-backdrop-filter:blur(14px); backdrop-filter:blur(14px);
        text-align:center; color:#fff;
    }
    .tirai-kotak { max-width:32rem; }

    /* Di bawah tirai pelanggaran (3000): lembar yang terkunci atau pelanggaran
       yang baru terjadi harus tetap terbaca di atas gerbang. */
    .gerbang-layar-penuh {
        position:fixed; inset:0; z-index:2900;
        display:grid; place-items:center; padding:1.25rem;
        background:#0b1a2e; text-align:center; color:#fff;
    }
    .gerbang-layar-penuh .tirai-ikon {
        background:rgba(0,168,107,.16); color:#3ddc97; border-color:rgba(0,168,107,.45);
    }

    .tirai-ikon {
        width:4.25rem; height:4.25rem; margin:0 auto 1rem;
        display:grid; place-items:center;
        border-radius:50%; font-size:2rem;
        background:rgba(255,193,7,.15); color:#ffc107;
        border:2px solid rgba(255,193,7,.4);
    }
    .tirai-pengawas.terkunci .tirai-ikon {
        background:rgba(220,53,69,.18); color:#ff6b7d; border-color:rgba(220,53,69,.45);
    }

    .tirai-status {
        display:inline-block; margin:0 0 .75rem;
        padding:.3rem 1rem; border-radius:2rem;
        background:#ffc107; color:#3a2c00;
        font-size:.78rem; font-weight:800; letter-spacing:.12em; text-transform:uppercase;
    }
    .tirai-pengawas.terkunci .tirai-status { background:#dc3545; color:#fff; }

    .tirai-judul  { font-size:1.3rem; font-weight:700; margin-bottom:.6rem; }
    .tirai-pesan  { color:#c9d8ea; font-size:.95rem; margin-bottom:0; }
    .tirai-identitas { margin:.7rem 0 0; color:#8fa6c0; font-size:.8rem; }
    .tirai-hitung {
        margin:.9rem auto 0; max-width:26rem;
        border-radius:.6rem; padding:.5rem .8rem;
        background:rgba(255,193,7,.12); color:#ffd75e; font-size:.85rem;
    }
    .tirai-catatan { margin:1rem 0 0; color:#7f93ab; font-size:.75rem; }

    /* Tombol lanjut ditolak selama keadaannya belum pulih. */
    @keyframes tirai-goyang {
        0%,100% { transform:translateX(0); }
        25%     { transform:translateX(-7px); }
        75%     { transform:translateX(7px); }
    }
    .tirai-goyang { animation:tirai-goyang .3s ease-in-out 2; }

    @media (max-width: 575.98px) {
        .tirai-judul { font-size:1.1rem; }
        .tirai-ikon  { width:3.4rem; height:3.4rem; font-size:1.6rem; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    var cfg = @json($pengawasan);
    var metaCsrf = document.querySelector('meta[name=csrf-token]');
    var tirai = document.getElementById('tiraiPengawas');

    if (! cfg || ! cfg.aktif || ! metaCsrf || ! tirai) return;

    // Halaman pengerjaan memeriksa penanda ini agar tidak ikut mencatat
    // kepergian yang sama dengan nama kejadian yang berbeda.
    window.PengawasanUjian = { aktif: true };

    var csrf = metaCsrf.content;
    var elStatus    = document.getElementById('tiraiStatus');
    var elJudul     = document.getElementById('tiraiJudul');
    var elPesan     = document.getElementById('tiraiPesan');
    var elIdentitas = document.getElementById('tiraiIdentitas');
    var elHitung    = document.getElementById('tiraiHitung');
    var elCatatan   = document.getElementById('tiraiCatatan');
    var tblLanjut   = document.getElementById('tiraiLanjut');
    var tblPenuh    = document.getElementById('tiraiPenuh');
    var tautanRuang = document.getElementById('tiraiDashboard');

    var pelanggaran = cfg.pelanggaran;
    var maks        = cfg.maks;
    var terkunci    = cfg.terkunci;

    var JUDUL = {
        tab_baru:         'Membuka tab atau jendela baru tidak diizinkan',
        layar_terbelah:   'Layar terbelah terdeteksi',
        layar_mengambang: 'Jendela mengambang terdeteksi',
        jendela_popup:    'Jendela sembulan terdeteksi',
        keluar_aplikasi:  'Anda meninggalkan lembar ujian',
        hilang_fokus:     'Lembar ujian kehilangan fokus',
        keluar_layar_penuh: 'Anda keluar dari mode layar penuh',
        salin_tempel:     'Menyalin isi soal tidak diizinkan'
    };

    var PESAN = {
        tab_baru:         'Selama ujian berlangsung, lembar ini harus menjadi satu-satunya halaman yang terbuka.',
        layar_terbelah:   'Kembalikan lembar ujian ke layar penuh dan tutup aplikasi yang terbuka berdampingan, lalu lanjutkan.',
        layar_mengambang: 'Tutup jendela mengambang atau gambar dalam gambar, lalu kembalikan lembar ujian ke layar penuh.',
        jendela_popup:    'Membuka jendela sembulan di atas lembar ujian tidak diizinkan.',
        keluar_aplikasi:  'Keluar dari peramban ujian atau berpindah ke aplikasi lain tidak diizinkan selama ujian berlangsung.',
        hilang_fokus:     'Ada jendela atau aplikasi lain yang mengambil alih layar. Kembalikan perhatian ke lembar ujian, lalu lanjutkan.',
        keluar_layar_penuh: 'Ujian harus dikerjakan dalam mode layar penuh. Tekan tombol di bawah untuk kembali.',
        salin_tempel:     'Isi soal tidak boleh disalin, dipindahkan, maupun ditempel dari tempat lain.'
    };

    /*
     * Sebab penguncian yang tidak menunggu batas. Layar terkunci harus
     * menyebut alasan yang sebenarnya — "pelanggaran sudah mencapai batas"
     * membingungkan peserta yang baru sekali melanggar.
     */
    var SEBAB_KUNCI = {
        jendela_popup:    'Membuka jendela sembulan di atas lembar ujian langsung mengunci lembar jawaban.',
        layar_mengambang: 'Memakai jendela mengambang di atas lembar ujian langsung mengunci lembar jawaban.'
    };

    // =====================================================================
    // Pelaporan ke server
    // =====================================================================

    var redam = {};

    /*
     * Pembaca balasan server untuk seluruh permintaan lapisan ini.
     *
     * 401 berarti akun ini masuk dari perangkat lain dan sesi di sini sudah
     * diakhiri. Siapa pun yang pertama menerimanya harus membawa siswa keluar
     * saat itu juga — permintaan pertama sesudah kejadian itulah satu-satunya
     * yang membawa alasannya, dan menunggu denyut berikutnya berarti soal
     * tetap tampil sampai 15 detik di perangkat yang sudah dikalahkan.
     */
    function bacaBalasan(res) {
        if (res.status === 401) {
            return res.json().then(function (data) {
                if (data && data.sesi_berakhir) {
                    document.body.innerHTML = '';
                    window.location.href = data.alihkan;
                }
                return null;
            }, function () { return null; });
        }

        return res.ok ? res.json() : null;
    }

    /**
     * Laporkan satu pelanggaran.
     *
     * Yang dikirim hanya kabar bahwa satu kejadian terjadi. Jumlah dan
     * keputusan mengunci sepenuhnya dihitung server — angka dari peramban
     * tidak pernah dipercaya.
     */
    function lapor(jenis, keterangan) {
        if (terkunci) return;

        // Satu kali membelah layar memicu beberapa kejadian resize berturut-
        // turut. Semuanya adalah pelanggaran yang sama, jadi hanya yang
        // pertama dalam lima detik yang dikirim.
        var kini = Date.now();
        if (redam[jenis] && kini - redam[jenis] < 5000) return;
        redam[jenis] = kini;

        var badan = new URLSearchParams();
        badan.append('_token', csrf);
        badan.append('jenis', jenis);
        badan.append('keterangan', String(keterangan || '').slice(0, 255));

        fetch(cfg.url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: badan,
            credentials: 'same-origin'
        })
        .then(bacaBalasan)
        .then(function (data) {
            if (! data) return;

            pelanggaran = data.pelanggaran;
            maks = data.maks;

            if (data.terkunci) kunci(jenis);
            else perbaruiHitung();
        })
        .catch(function () {
            // Jaringan putus tidak boleh menghentikan ujian. Tirainya sudah
            // terbuka; pelanggarannya saja yang luput tercatat.
        });
    }

    // =====================================================================
    // Tirai
    // =====================================================================

    /** Identitas peserta dan waktu kejadian, untuk pengawas yang dipanggil. */
    function tulisIdentitas() {
        var bagian = [];
        if (cfg.nama) bagian.push(cfg.nama);
        if (cfg.kelas) bagian.push('Kelas ' + cfg.kelas);
        bagian.push('pukul ' + new Date().toLocaleTimeString('id-ID'));

        elIdentitas.textContent = bagian.join(' · ');
    }

    function bukaTirai(jenis) {
        if (terkunci) return;

        tirai.classList.remove('terkunci');
        elStatus.textContent = 'Ujian Terblokir';
        elJudul.textContent = JUDUL[jenis] || 'Pelanggaran terdeteksi';
        elPesan.textContent = PESAN[jenis] || '';
        elCatatan.textContent = 'Soal disembunyikan selama pelanggaran berlangsung. Kejadian ini tercatat pada layar pengawas, dan hitung mundur ujian tetap berjalan.';
        tblLanjut.hidden = false;
        tblPenuh.hidden = ! bisaLayarPenuh()
            || jenis === 'tab_baru' || jenis === 'salin_tempel' || jenis === 'hilang_fokus';
        if (tautanRuang) tautanRuang.hidden = true;

        tulisIdentitas();
        perbaruiHitung();
        tirai.hidden = false;
    }

    function tutupTirai() {
        if (terkunci) return;
        tirai.hidden = true;
    }

    function kunci(jenis) {
        terkunci = true;

        tirai.classList.add('terkunci');
        elStatus.textContent = 'Ujian Terblokir';
        elJudul.textContent = 'Lembar jawaban dikunci';
        elPesan.textContent = (jenis && SEBAB_KUNCI[jenis])
            ? SEBAB_KUNCI[jenis] + ' Panggil pengawas ruang untuk membukanya kembali.'
            : 'Pelanggaran sudah mencapai batas. Panggil pengawas ruang untuk membuka kembali lembar jawaban Anda.';
        elCatatan.textContent = 'Jawaban yang sudah tersimpan tidak hilang dan hitung mundur ujian tetap berjalan. '
            + 'Anda boleh menunggu di sini, atau kembali ke Ruang Ujian sambil memanggil pengawas.';
        elHitung.hidden = true;
        tblLanjut.hidden = true;
        tblPenuh.hidden = true;
        if (tautanRuang) tautanRuang.hidden = false;

        tulisIdentitas();
        tirai.hidden = false;
    }

    function perbaruiHitung() {
        if (! maks || ! pelanggaran) { elHitung.hidden = true; return; }

        var sisa = Math.max(0, maks - pelanggaran);
        elHitung.textContent = sisa > 0
            ? 'Pelanggaran ke-' + pelanggaran + ' dari ' + maks + '. Tersisa ' + sisa + ' kesempatan sebelum lembar jawaban dikunci.'
            : 'Pelanggaran ke-' + pelanggaran + ' dari ' + maks + '.';
        elHitung.hidden = false;
    }

    function bisaLayarPenuh() {
        return !! (document.fullscreenEnabled && document.documentElement.requestFullscreen);
    }

    tblLanjut.addEventListener('click', function () {
        if (terkunci) return;

        // Tirai hanya boleh ditutup kalau keadaannya benar-benar sudah pulih;
        // kalau tidak, siswa cukup menekan tombol ini untuk terus membaca soal
        // di separuh layar.
        if (periksaGeometri()) {
            tirai.classList.remove('tirai-goyang');
            void tirai.offsetWidth;             // paksa animasinya dimulai ulang
            tirai.classList.add('tirai-goyang');
            return;
        }

        pelanggaranGeometri = null;
        tutupTirai();
    });

    tblPenuh.addEventListener('click', function () {
        if (! bisaLayarPenuh()) return;
        var janji = document.documentElement.requestFullscreen();
        if (janji && janji.catch) janji.catch(function () {});
    });

    // =====================================================================
    // 1. Tab & jendela baru
    // =====================================================================

    /*
     * Jendela sembulan tidak pernah lahir dari lembar ujian ini — tidak ada
     * satu pun tautan atau tombol di sini yang memanggilnya. Kalau window.open
     * dipanggil, panggilannya datang dari luar: konsol peramban, bookmarklet,
     * atau skrip yang disuntikkan. Karena itu diperlakukan berat.
     */
    window.open = function () {
        lapor('jendela_popup', 'Pemanggilan window.open dari halaman ujian');
        bukaTirai('jendela_popup');
        return null;
    };

    function elemenDari(e) {
        var t = e.target;
        return t && t.nodeType === 1 ? t : null;
    }

    document.addEventListener('click', function (e) {
        var el = elemenDari(e);
        var a = el && el.closest ? el.closest('a') : null;
        if (! a) return;

        // Ctrl/Cmd/Shift + klik membuka tautan di tab atau jendela baru.
        var keTabBaru = a.target === '_blank' || e.ctrlKey || e.metaKey || e.shiftKey;
        if (! keTabBaru) return;

        e.preventDefault();
        e.stopPropagation();
        lapor('tab_baru', 'Tautan dibuka di tab baru: ' + (a.getAttribute('href') || '-'));
        bukaTirai('tab_baru');
    }, true);

    // Roda tengah tetikus juga membuka tab baru, tanpa melalui kejadian click.
    document.addEventListener('auxclick', function (e) {
        if (e.button !== 1) return;
        e.preventDefault();
        lapor('tab_baru', 'Klik tombol tengah tetikus');
        bukaTirai('tab_baru');
    }, true);

    var PINTASAN = { t:'tab baru', n:'jendela baru', w:'menutup tab', p:'cetak halaman', s:'menyimpan halaman', u:'melihat kode sumber' };

    document.addEventListener('keydown', function (e) {
        var k = String(e.key || '').toLowerCase();

        // Alat pengembang membuka jalan membaca kunci jawaban langsung dari
        // HTML halaman, jadi diperlakukan sama beratnya dengan tab baru.
        var alatPengembang = e.key === 'F12'
            || ((e.ctrlKey || e.metaKey) && e.shiftKey && (k === 'i' || k === 'j' || k === 'c'));

        if (alatPengembang) {
            e.preventDefault();
            lapor('tab_baru', 'Percobaan membuka alat pengembang');
            bukaTirai('tab_baru');
            return;
        }

        if ((e.ctrlKey || e.metaKey) && ! e.shiftKey && PINTASAN[k]) {
            // Ctrl+T dan Ctrl+N dipesan peramban dan biasanya tidak sampai ke
            // halaman. Di WebView ExamBro justru sampai — di sanalah
            // preventDefault ini benar-benar mencegah, bukan sekadar mencatat.
            e.preventDefault();
            lapor('tab_baru', 'Pintasan Ctrl+' + k.toUpperCase() + ' — ' + PINTASAN[k]);
            bukaTirai('tab_baru');
        }
    }, true);

    // =====================================================================
    // 2. Keluar dari peramban ujian / berpindah aplikasi
    // =====================================================================

    /*
     * Menekan tombol keluar ExamBro, menekan tombol Beranda, menarik panel
     * notifikasi, atau berpindah ke aplikasi lain sama-sama menyembunyikan
     * halaman ini. Tidak satu pun dapat dicegah dari dalam halaman — yang bisa
     * dilakukan hanyalah mencatatnya dan memblokir soal saat peserta kembali.
     *
     * Laporannya dikirim lewat sendBeacon, bukan fetch: pada saat itu halaman
     * sedang ditinggalkan dan peramban membatalkan permintaan fetch yang belum
     * selesai. Konsekuensinya tidak ada balasan yang bisa dibaca, jadi
     * hitungan dan status kuncinya ditanyakan ulang lewat periksaKeadaan()
     * begitu halaman aktif lagi.
     */

    var keluarSah = false;              // meninggalkan halaman karena mengumpulkan lembar
    var bernavigasi = false;            // halaman sedang dimuat ulang / berpindah alamat
    var perluDitagih = false;           // ada laporan terkirim yang belum diketahui hasilnya

    // Mengumpulkan lembar, dan pengumpulan otomatis saat waktu habis, sama-sama
    // mengirim formulir. Keduanya sah dan tidak boleh terhitung pelanggaran.
    document.addEventListener('submit', function () { keluarSah = true; }, true);

    /*
     * Menyegarkan halaman juga menyembunyikannya sesaat sebelum ditutup, jadi
     * tanpa pembeda setiap refresh akan terhitung sebagai kabur dari ujian —
     * tiga kali menyegarkan sudah cukup mengunci lembar siswa yang tidak
     * berbuat apa-apa.
     *
     * Pembedanya urutan kejadian. Memuat ulang selalu didahului beforeunload;
     * berpindah aplikasi dan menekan tombol keluar ExamBro tidak, karena di
     * sana halamannya hanya dijeda, bukan ditinggalkan alamatnya.
     *
     * Penyimaknya sengaja pasif — tidak memanggil preventDefault dan tidak
     * mengisi returnValue — supaya dialog "Reload site?" tetap tidak muncul.
     */
    window.addEventListener('beforeunload', function () {
        bernavigasi = true;

        // Jaring pengaman: bila halamannya ternyata tetap hidup, penandanya
        // dikembalikan supaya kepergian berikutnya tidak ikut terlewat.
        setTimeout(function () { bernavigasi = false; }, 3000);
    });

    window.addEventListener('pageshow', function () { bernavigasi = false; });

    /*
     * Satu kepergian kerap memicu dua kejadian sekaligus — jendela kehilangan
     * fokus lebih dulu, lalu halamannya disembunyikan. Keduanya perbuatan yang
     * sama, jadi peredamnya satu ember bersama; kalau tidak, satu kali
     * berpindah aplikasi terhitung dua pelanggaran.
     */
    var jenisTerakhirPergi = 'keluar_aplikasi';

    function laporKeluar(jenis, sebab) {
        if (terkunci || keluarSah || bernavigasi) return;

        var kini = Date.now();
        if (redam.pergi && kini - redam.pergi < 5000) return;
        redam.pergi = kini;

        jenisTerakhirPergi = jenis;

        if (! navigator.sendBeacon) return;

        var data = new URLSearchParams();
        data.append('_token', csrf);
        data.append('jenis', jenis);
        data.append('keterangan', sebab);

        navigator.sendBeacon(cfg.url, data);
        perluDitagih = true;
    }

    function periksaKeadaan() {
        if (! cfg.url_status) return;

        fetch(cfg.url_status, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
        .then(bacaBalasan)
        .then(function (data) {
            if (! data) return;

            pelanggaran = data.pelanggaran;
            maks = data.maks;

            if (data.terkunci) { kunci(data.terakhir); return; }

            if (perluDitagih) {
                perluDitagih = false;
                bukaTirai(jenisTerakhirPergi);
            } else if (! tirai.hidden) {
                perbaruiHitung();
            }
        })
        .catch(function () {});
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            laporKeluar('keluar_aplikasi', 'Halaman ujian disembunyikan — berpindah aplikasi atau keluar peramban ujian');
        } else {
            periksaKeadaan();
        }
    });

    /*
     * Kehilangan fokus tanpa halaman disembunyikan.
     *
     * Inilah satu-satunya jejak yang ditinggalkan panel yang melayang di atas
     * lembar ujian — asisten Gemini, gelembung obrolan, jendela kecil aplikasi
     * lain. Halamannya tidak disembunyikan dan ukurannya tidak berubah, jadi
     * pemeriksaan kepergian maupun pemeriksaan ukuran layar tidak melihatnya.
     * Yang terjadi hanya satu: fokus jendela pindah ke panel itu.
     *
     * Versi sebelumnya memastikan kehilangan fokus lewat document.hasFocus().
     * Di WebView Android fungsi itu mengembalikan false walaupun halamannya
     * tampil penuh dan sedang disentuh — pengujian lapangan 10 September 2026
     * mencatat 10 pelanggaran palsu karenanya — sehingga pemeriksaan ini
     * sempat dimatikan di perangkat sentuh. Akibatnya panel Gemini lolos.
     *
     * Sekarang keadaan fokus dilacak dari pasangan kejadian blur dan focus
     * sendiri, bukan ditanyakan ke document.hasFocus(), dan kehilangan fokus
     * baru dilaporkan bila BERTAHAN:
     *
     *  - fokus tidak kembali dalam TUNDA_FOKUS milidetik — kedipan fokus
     *    sesaat, yang lazim di WebView, tidak pernah sampai selama itu;
     *  - siswa tidak menyentuh halaman selama itu — siapa pun yang sedang
     *    menyentuh lembar ujian jelas sedang berada di lembar ujian;
     *  - dan fokusnya tidak sedang dipegang kotak pilihan. Di Android, kotak
     *    <select> pada soal penjodohan dibuka sebagai dialog sistem yang ikut
     *    mengambil fokus jendela — itu bagian dari mengerjakan soal.
     */
    var TUNDA_FOKUS = 1500;
    var penantiFokus = null;

    function batalkanPenantiFokus() {
        if (penantiFokus) {
            clearTimeout(penantiFokus);
            penantiFokus = null;
        }
    }

    window.addEventListener('blur', function () {
        var aktif = document.activeElement;
        if (aktif && aktif.tagName === 'SELECT') return;

        batalkanPenantiFokus();

        penantiFokus = setTimeout(function () {
            penantiFokus = null;

            if (document.hidden) return;        // sudah ditangani visibilitychange

            laporKeluar('hilang_fokus', 'Lembar ujian kehilangan fokus lebih dari '
                + (TUNDA_FOKUS / 1000).toString().replace('.', ',')
                + ' detik — ada jendela atau panel lain di atasnya');
            bukaTirai('hilang_fokus');

            // Halamannya masih hidup, jadi hitungannya bisa ditanyakan
            // sekarang juga — tanpa ini tirainya tampil tanpa menyebut sisa
            // kesempatan, justru saat peringatan itu paling perlu terbaca.
            setTimeout(periksaKeadaan, 800);
        }, TUNDA_FOKUS);
    });

    // Menyentuh halaman berarti siswa sedang berada di lembar ujian.
    document.addEventListener('pointerdown', batalkanPenantiFokus, true);

    // Kehilangan fokus tidak mengubah visibilitas, jadi kepulangannya juga
    // tidak memicu visibilitychange — hasil laporannya ditanyakan di sini.
    window.addEventListener('focus', function () {
        batalkanPenantiFokus();
        if (perluDitagih || ! tirai.hidden) periksaKeadaan();
    });

    // Kesempatan terakhir bila aplikasinya benar-benar ditutup.
    window.addEventListener('pagehide', function () {
        laporKeluar('keluar_aplikasi', 'Halaman ujian ditutup atau ditinggalkan');
    });

    // =====================================================================
    // 3. Mode layar penuh
    // =====================================================================

    /*
     * Layar penuh menutup bilah alamat, bilah tab, dan bilah tugas sekaligus —
     * ketiganya jalan keluar termudah dari lembar ujian.
     *
     * Penegakannya bersyarat, dan syaratnya penting: yang dituntut hanya
     * peserta yang layar penuhnya pernah benar-benar aktif. Fullscreen API
     * tidak tersedia di semua WebView, dan ExamBro sendiri sudah berjalan
     * sebagai kios tanpa melewati API itu — menuntutnya di sana hanya akan
     * memunculkan tirai yang tidak pernah bisa dipenuhi siswa.
     */
    var pernahPenuh = false;

    function sedangPenuh() {
        return !! (document.fullscreenElement || document.webkitFullscreenElement);
    }


    var gerbang = document.getElementById('gerbangLayarPenuh');
    var tblGerbang = document.getElementById('gerbangMasuk');

    function tutupGerbang() {
        if (gerbang) gerbang.hidden = true;
    }

    /*
     * Gerbang dibuka setiap kali lembar dimuat tanpa layar penuh — saat baru
     * mulai, setelah tombol muat ulang, atau setelah kembali dari Ruang Ujian.
     * Memuat ulang selalu melepas layar penuh, jadi tanpa ini siswa cukup
     * menyegarkan halaman untuk mengerjakan di luar layar penuh.
     */
    function bukaGerbang() {
        if (! gerbang || terkunci || ! bisaLayarPenuh() || sedangPenuh()) return;

        var ident = document.getElementById('gerbangIdentitas');
        if (ident) {
            var bagian = [];
            if (cfg.nama) bagian.push(cfg.nama);
            if (cfg.kelas) bagian.push('Kelas ' + cfg.kelas);
            ident.textContent = bagian.join(' · ');
        }

        gerbang.hidden = false;
    }

    if (tblGerbang) {
        tblGerbang.addEventListener('click', function () {
            tblGerbang.disabled = true;

            var janji;
            try {
                janji = document.documentElement.requestFullscreen();
            } catch (e) {
                janji = null;
            }

            /*
             * Bila peramban menolak — izin dicabut, WebView yang mengaku
             * mendukung padahal tidak — siswa tetap dipersilakan masuk.
             * Gerbang yang tidak bisa dilewati berarti ujian yang tidak bisa
             * dikerjakan, dan itu jauh lebih merugikan daripada satu lembar
             * yang dikerjakan di luar layar penuh. Penegakan selanjutnya tetap
             * berlaku: layar penuh yang sudah aktif lalu dilepas tetap
             * terhitung pelanggaran.
             */
            var sudahLepas = false;
            var lepasGerbang = function () {
                if (sudahLepas) return;
                sudahLepas = true;
                tblGerbang.disabled = false;
                tutupGerbang();
            };

            if (janji && janji.then) {
                janji.then(lepasGerbang, lepasGerbang);
            }

            /*
             * Batas waktu, apa pun jawaban perambannya. Ada lingkungan yang
             * janji requestFullscreen()-nya tidak pernah selesai — tidak
             * diterima, tidak ditolak. Tanpa ini tombolnya mati dan gerbangnya
             * tertutup selamanya; ketahuan saat pengujian, bukan dugaan.
             */
            setTimeout(lepasGerbang, 2000);
        });
    }

    if (bisaLayarPenuh()) {
        var masukPenuhPada = 0;

        document.addEventListener('fullscreenchange', function () {
            if (sedangPenuh()) {
                pernahPenuh = true;
                masukPenuhPada = Date.now();

                // Kembali ke layar penuh menutup tirainya bila itu memang yang
                // sedang diminta.
                if (! tirai.hidden && ! terkunci) tutupTirai();
                return;
            }

            if (! pernahPenuh) return;      // belum pernah aktif, tidak dituntut

            // Mengumpulkan lembar dan memuat ulang halaman sama-sama melepas
            // layar penuh. Keduanya sah — halaman berikutnya membuka gerbangnya
            // sendiri — dan tidak boleh terhitung pelanggaran.
            if (keluarSah || bernavigasi) return;

            // Sebagian WebView menerima permintaan layar penuh lalu langsung
            // membatalkannya sendiri. Pantulan seperti itu bukan perbuatan
            // peserta dan tidak boleh dihitung.
            if (Date.now() - masukPenuhPada < 1500) {
                pernahPenuh = false;
                return;
            }

            lapor('keluar_layar_penuh', 'Mode layar penuh dimatikan');
            bukaTirai('keluar_layar_penuh');
        });
    }

    // =====================================================================
    // 4. Menyalin & menempel
    // =====================================================================

    document.addEventListener('contextmenu', function (e) { e.preventDefault(); });
    document.addEventListener('dragstart', function (e) { e.preventDefault(); });

    document.addEventListener('copy', function (e) {
        // Menyalin tulisan sendiri di kolom essay tidak merugikan siapa pun,
        // dan tidak ada tempat untuk menempelkannya di lembar ini.
        var el = elemenDari(e);
        if (el && el.classList && el.classList.contains('essay-jawab')) return;

        e.preventDefault();
        lapor('salin_tempel', 'Percobaan menyalin isi soal');
        bukaTirai('salin_tempel');
    }, true);

    document.addEventListener('paste', function (e) {
        // Menempel dilarang di mana pun, termasuk kolom essay: di situlah
        // jawaban yang disiapkan sebelumnya akan masuk.
        e.preventDefault();
        lapor('salin_tempel', 'Percobaan menempel dari luar lembar ujian');
        bukaTirai('salin_tempel');
    }, true);

    document.addEventListener('cut', function (e) {
        // Memotong dilarang termasuk di kolom essay. Kalau dibiarkan, siswa
        // yang memotong satu paragraf tidak akan pernah bisa menempelkannya
        // kembali — tulisannya hilang begitu saja.
        e.preventDefault();
        lapor('salin_tempel', 'Percobaan memotong teks');
        bukaTirai('salin_tempel');
    }, true);

    // =====================================================================
    // 5. Layar terbelah & jendela mengambang
    // =====================================================================

    /*
     * Keduanya dikenali dari ukuran jendela: layar terbelah menyusutkan satu
     * sumbu, jendela mengambang menyusutkan keduanya sekaligus.
     */
    /*
     * Ambangnya relatif terhadap ukuran istirahat perangkat, bukan angka tetap
     * terhadap ukuran layar.
     *
     * Angka tetap tidak bisa dipakai karena tiap peramban memakan porsi layar
     * yang berbeda-beda. Pada perangkat yang diuji 10 September 2026, bilah
     * ExamBro dan bilah sistem Android menyisakan viewport setinggi 0,61 layar
     * dalam keadaan diam — sementara ambang tetapnya 0,62. Perangkat itu
     * dinyatakan "layar terbelah" hanya karena duduk diam, selisih satu
     * perseratus.
     *
     * Yang dipakai sekarang: seberapa jauh jendela MENYUSUT dari ukuran
     * terbesar yang pernah teramati di perangkat ini. Jendela tidak pernah
     * bisa lebih besar dari ukuran utuhnya, jadi nilai terbesar itu perkiraan
     * yang baik untuk "penuh".
     */
    var SUSUT = 0.80;          // 80% dari ukuran istirahat masih dianggap wajar

    /*
     * Lantai mutlak, untuk peserta yang sudah membelah layar sebelum lembar
     * ujian dibuka — ukuran istirahatnya terlanjur terukur kecil, sehingga
     * penyusutan relatif tidak akan pernah terpicu. Di bawah angka ini,
     * porsi layar yang dimakan peramban tidak lagi masuk akal.
     */
    var LANTAI_LEBAR  = 0.60;
    var LANTAI_TINGGI = 0.45;

    // Ukuran terbesar yang pernah teramati; menjadi acuan "penuh".
    var puncak = null;

    var dprAwal = window.devicePixelRatio || 1;

    /*
     * Lebar dibandingkan dengan lebar, tinggi dengan tinggi.
     *
     * Sebelumnya sisi panjang jendela dibandingkan dengan sisi panjang layar.
     * Cara itu gugur justru pada kasus yang paling sering: layar ponsel yang
     * dibelah ke bawah menyisakan jendela 392×260 — lebih lebar daripada
     * tinggi, padahal layarnya tetap potret. Sisi "panjang" jendela lalu
     * dibandingkan dengan tinggi layar, kedua sumbu terbaca menyusut, dan
     * layar terbelah biasa salah dikenali sebagai jendela mengambang yang
     * mengunci seketika.
     *
     * Orientasi layar diambil dari screen.orientation bila ada; peramban yang
     * tidak menyediakannya diperkirakan dari sisi mana yang lebih panjang.
     */
    function rasio() {
        var sw = screen.width, sh = screen.height;
        if (! sw || ! sh) return null;

        // Perbesaran halaman menyusutkan innerWidth dalam piksel CSS tanpa
        // mengubah screen.width, dan itu akan terbaca seperti layar terbelah.
        // devicePixelRatio bergerak seiring perbesaran, jadi dipakai
        // mengembalikan ukurannya ke skala semula.
        var koreksi = (window.devicePixelRatio || 1) / dprAwal;
        var w = window.innerWidth * koreksi;
        var h = window.innerHeight * koreksi;

        function coba(lebarLayar, tinggiLayar) {
            return { lebar: w / lebarLayar, tinggi: h / tinggiLayar };
        }

        var pendek = Math.min(sw, sh), panjang = Math.max(sw, sh);
        var sebagaiPotret  = coba(pendek, panjang);
        var sebagaiLanskap = coba(panjang, pendek);

        function masukAkal(p) { return p.lebar <= 1.05 && p.tinggi <= 1.05; }

        /*
         * Orientasi layar tidak diambil dari screen.orientation: nilainya tidak
         * selalu sepakat dengan screen.width/height di WebView, dan sekali
         * keduanya bertentangan, seluruh perhitungan ikut salah — jendela
         * pernah terukur setinggi 1,32 layar, yang mustahil.
         *
         * Yang dipakai: dicoba kedua pemetaan, lalu diambil yang masuk akal.
         * Bila keduanya masuk akal, dipilih yang lebarnya paling pas. Jendela
         * peramban praktis selalu memenuhi lebar wadahnya — yang dimakan bilah
         * dan pembelahan layar adalah tingginya, bukan lebarnya.
         */
        if (masukAkal(sebagaiPotret) && masukAkal(sebagaiLanskap)) {
            return sebagaiPotret.lebar >= sebagaiLanskap.lebar ? sebagaiPotret : sebagaiLanskap;
        }

        return masukAkal(sebagaiPotret) ? sebagaiPotret : sebagaiLanskap;
    }

    function sedangMengetik() {
        var el = document.activeElement;
        if (! el) return false;
        var tag = el.tagName;
        return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable === true;
    }

    function periksaGeometri() {
        var r = rasio();
        if (! r) return null;

        // Acuan tumbuh mengikuti ukuran terbesar yang pernah terlihat.
        if (! puncak) puncak = { lebar: r.lebar, tinggi: r.tinggi };
        if (r.lebar  > puncak.lebar)  puncak.lebar  = r.lebar;
        if (r.tinggi > puncak.tinggi) puncak.tinggi = r.tinggi;

        var sempitLebar  = r.lebar  < puncak.lebar  * SUSUT || r.lebar  < LANTAI_LEBAR;
        var sempitTinggi = r.tinggi < puncak.tinggi * SUSUT || r.tinggi < LANTAI_TINGGI;

        // Papan ketik di layar memendekkan jendela persis seperti layar yang
        // dibelah ke bawah. Selama kursor ada di kolom isian, pemeriksaan
        // tinggi ditangguhkan — lebarnya tetap diperiksa, karena papan ketik
        // tidak pernah menyempitkan lebar jendela.
        if (sempitTinggi && sedangMengetik()) sempitTinggi = false;

        if (sempitLebar && sempitTinggi) {
            /*
             * Menyusut di kedua sisi sekaligus berarti jendela mengambang —
             * tetapi hanya di perangkat sentuh, tempat jendela memang selalu
             * memenuhi layar kecuali sengaja dibuat melayang.
             *
             * Di komputer meja, jendela yang lebih kecil dari layar jauh lebih
             * mungkin sekadar belum dimaksimalkan. Perbedaan itu penting
             * karena jendela mengambang mengunci lembar seketika, dan
             * mengunci siswa hanya karena jendelanya belum penuh jelas tidak
             * sebanding.
             */
            return perangkatSentuh() ? 'layar_mengambang' : 'layar_terbelah';
        }

        if (sempitLebar || sempitTinggi) return 'layar_terbelah';
        return null;
    }

    function perangkatSentuh() {
        return (navigator.maxTouchPoints || 0) > 0
            || (window.matchMedia && window.matchMedia('(pointer: coarse)').matches);
    }

    function keteranganGeometri() {
        var r = rasio();
        if (! r) return 'Jendela ' + window.innerWidth + '×' + window.innerHeight;

        // Acuan istirahat ikut dicatat: tanpa itu angka rasionya tidak bisa
        // dinilai, karena tiap perangkat punya porsi bilahnya sendiri.
        return 'Jendela ' + window.innerWidth + '×' + window.innerHeight
            + ' pada layar ' + screen.width + '×' + screen.height
            + ' (kini ' + r.lebar.toFixed(2) + '×' + r.tinggi.toFixed(2)
            + ', istirahat ' + puncak.lebar.toFixed(2) + '×' + puncak.tinggi.toFixed(2) + ')';
    }

    var pelanggaranGeometri = null;
    var calon = null, calonSejak = 0;

    function pantau() {
        var kini = periksaGeometri();

        if (kini === null) {
            calon = null;
            if (pelanggaranGeometri) {
                pelanggaranGeometri = null;
                tutupTirai();
            }
            return;
        }

        if (kini === pelanggaranGeometri) return;

        // Ditahan sejenak supaya perubahan sesaat tidak ikut terhitung:
        // memutar layar, dan bilah alamat yang menyembunyikan diri saat
        // halaman digulir, sama-sama mengubah tinggi jendela sekejap.
        if (kini !== calon) { calon = kini; calonSejak = Date.now(); return; }
        if (Date.now() - calonSejak < 700) return;

        calon = null;
        pelanggaranGeometri = kini;
        lapor(kini, keteranganGeometri());
        bukaTirai(kini);
    }

    window.addEventListener('resize', pantau);
    /*
     * Memutar layar mengubah rasio yang bisa dicapai kedua sisi, sehingga
     * acuan lama menjadi mustahil dipenuhi — potret yang kembali dari lanskap
     * akan terbaca seperti menyusut drastis. Acuannya dipelajari ulang.
     */
    window.addEventListener('orientationchange', function () {
        puncak = null;
        pelanggaranGeometri = null;
        calon = null;
        setTimeout(pantau, 900);
    });
    if (window.visualViewport) window.visualViewport.addEventListener('resize', pantau);

    // Jendela mengambang di Android kerap tidak memicu resize sama sekali,
    // jadi ukurannya tetap diperiksa berkala.
    setInterval(pantau, 1500);

    // Gambar dalam gambar: bentuk lain dari jendela mengambang.
    document.addEventListener('enterpictureinpicture', function () {
        lapor('layar_mengambang', 'Gambar dalam gambar diaktifkan');
        bukaTirai('layar_mengambang');
    }, true);

    if (window.documentPictureInPicture && window.documentPictureInPicture.requestWindow) {
        window.documentPictureInPicture.requestWindow = function () {
            lapor('layar_mengambang', 'Permintaan jendela mengambang ditolak');
            bukaTirai('layar_mengambang');
            return Promise.reject(new Error('Jendela mengambang dilarang selama ujian.'));
        };
    }

    // =====================================================================

    if (terkunci) {
        kunci(cfg.terakhir);
    } else {
        bukaGerbang();

        // Peramban ujian yang ditutup lalu dibuka lagi membuat halaman ini
        // dimuat dari awal, sehingga tirai yang seharusnya muncul saat peserta
        // pergi tidak pernah sempat tampil. Pelanggaran yang baru saja
        // tercatat ditampilkan di sini sebagai gantinya.
        if (cfg.terakhir && cfg.terakhir_detik !== null && cfg.terakhir_detik <= 45) {
            bukaTirai(cfg.terakhir);
        }

        pantau();
    }
})();
</script>
@endpush
