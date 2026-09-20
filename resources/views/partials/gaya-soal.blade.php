{{--
    Gaya tampilan isi butir soal, dipakai bersama oleh layout pengelola dan
    layout ruang ujian siswa supaya soal terlihat persis sama di kedua sisi.

    Font Arab dilayani dari server ini sendiri, bukan dari CDN: ujian kerap
    berjalan di jaringan lokal tanpa internet, dan peramban ujian seperti
    ExamBro memang mengunci akses ke luar. Tumpukan fontnya tetap menyertakan
    font bawaan sistem sebagai lapis terakhir.
--}}
<link href="{{ asset('vendor/font/amiri.css') }}" rel="stylesheet">
<style>
    /* ---------- Teks Arab & arah kanan-ke-kiri ---------- */
    .teks-arab,
    .soal-body [dir="rtl"],
    .opsi-teks[dir="rtl"] {
        font-family: 'Amiri', 'Traditional Arabic', 'Noto Naskh Arabic',
                     'Segoe UI', 'Arabic Typesetting', serif;
        font-size: 1.35em;
        line-height: 2.1;
    }

    /* Tanda baca dan angka ikut menempel pada sisi yang benar. */
    .teks-arab { direction: rtl; unicode-bidi: isolate; display: inline-block; text-align: right; }

    /* Satu butir yang seluruhnya berbahasa Arab: rata kanan sepenuhnya. */
    .soal-body[dir="rtl"], .soal-body [dir="rtl"] { text-align: right; }

    /* ---------- Pecahan bertingkat ---------- */
    .pecahan {
        display: inline-flex;
        flex-direction: column;
        vertical-align: -0.48em;
        text-align: center;
        margin: 0 .18em;
    }
    .pecahan > .pembilang {
        border-bottom: 1px solid currentColor;
        padding: 0 .32em .05em;
        line-height: 1.3;
    }
    .pecahan > .penyebut { padding: .05em .32em 0; line-height: 1.3; }

    /* ---------- Akar ---------- */
    .akar { white-space: nowrap; }
    .akar::before { content: '\221A'; }          /* √ */
    .akar > .radikan {
        border-top: 1px solid currentColor;
        padding: 0 .18em;
        margin-left: -.06em;
    }

    /* ---------- Pangkat & indeks ---------- */
    .soal-body sup, .soal-body sub, .opsi-teks sup, .opsi-teks sub {
        font-size: .72em;
        line-height: 0;
    }

    /* ---------- Gambar dalam soal ---------- */
    .soal-body img, .opsi-teks img {
        max-width: 100%;
        height: auto;
        border-radius: .35rem;
        border: 1px solid #e4e9f0;
    }
    /* Gambar pada badan soal berdiri sendiri sebagai blok; pada opsi jawaban
       ia sebaris dengan teksnya dan dibatasi agar deretan opsi tetap ringkas. */
    .soal-body img { display: block; margin: .6rem 0; }
    .opsi-teks img { display: inline-block; vertical-align: middle; max-height: 9rem; margin: .2rem 0; }

    /* ---------- Tabel dalam soal ---------- */
    .soal-body table { border-collapse: collapse; margin: .6rem 0; }
    .soal-body table td, .soal-body table th {
        border: 1px solid #c9d2e0;
        padding: .3rem .55rem;
    }

    /* Isi opsi jawaban memakai gaya yang sama dengan badan soal. */
    .opsi-teks { display: inline; }
    .opsi-teks .pecahan, .soal-body .pecahan { font-size: .95em; }
</style>
