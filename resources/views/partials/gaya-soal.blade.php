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
    /*
        Garis pecahan harus sejajar dengan tanda hitung di sebelahnya — pada
        "7/8 + 3/4" tanda + duduk di garis pecahan, bukan di samping
        pembilang. Pergeseran tetap (dulu -0.48em) selalu meleset karena
        tinggi pecahan ikut berubah mengikuti isinya, terlebih bila
        pembilangnya sendiri berupa pecahan lagi.

        "middle" menempatkan titik tengah kotak pecahan pada separuh tinggi
        huruf di atas garis alas — persis sumbu tempat tanda +, −, dan =
        digambar. Karena pembilang dan penyebut sama tinggi, titik tengah itu
        jatuh tepat pada garis pecahannya.
    */
    .pecahan {
        display: inline-flex;
        flex-direction: column;
        vertical-align: middle;
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

    /*
        Rumus pada naskah Word kerap bukan huruf melainkan gambar: pecahan,
        akar, dan pangkat dibuat di penyunting persamaan lalu ikut tersalin
        sebagai potongan gambar setinggi satu-dua baris. Diperlakukan sebagai
        blok seperti gambar soal lainnya, tiap rumus melompat ke barisnya
        sendiri dan satu kalimat pecah menjadi tiga baris: "Bentuk dari",
        pecahannya, lalu "dalam pangkat positif adalah".

        Gambar sekecil itu karena itu ditandai saat masuk ke kolom soal, lalu
        dikembalikan mengalir bersama tulisannya — tanpa bingkai, karena yang
        digambar sebenarnya tulisan juga. Lihat partials/editor-kaya.blade.php.
    */
    .soal-body img.gambar-sebaris,
    .opsi-teks img.gambar-sebaris {
        display: inline-block;
        vertical-align: middle;
        margin: 0 .15em;
        border: 0;
        border-radius: 0;
    }

    /* ---------- Tabel dalam soal ---------- */
    /*
        Tabel pada soal hampir selalu datang dari tempelan Word, Excel, atau
        Google Dokumen, jadi lebarnya ditentukan di sana dan kerap melebihi
        kolom soal. Dibiarkan apa adanya ia menjorok keluar kartu dan
        mendorong tata letak halaman.

        "display: block" dengan lebar secukupnya membuat tabel berhenti di
        tepi kolom; bila isinya memang tidak muat, tabel itu sendiri yang
        digulir ke samping, bukan halamannya.
    */
    .soal-body table, .opsi-teks table {
        border-collapse: collapse;
        margin: .6rem 0;
        display: block;
        width: max-content;
        max-width: 100%;
        overflow-x: auto;
    }
    .soal-body table td, .soal-body table th,
    .opsi-teks table td, .opsi-teks table th {
        border: 1px solid #c9d2e0;
        padding: .3rem .55rem;
        vertical-align: top;
    }
    .soal-body table th, .opsi-teks table th { background: #f4f7fb; }

    /*
        Word membungkus isi tiap sel dengan <p>. Margin bawaan paragraf
        membuat baris tabel menjadi tinggi dan renggang, padahal di Word-nya
        rapat.
    */
    .soal-body table p, .opsi-teks table p { margin: 0; }
    .soal-body table ul, .soal-body table ol,
    .opsi-teks table ul, .opsi-teks table ol { margin: 0; padding-left: 1.1rem; }

    /* Di atas kertas tidak ada yang bisa digulir; tabel dibiarkan utuh. */
    @media print {
        .soal-body table, .opsi-teks table { display: table; width: auto; overflow-x: visible; }
    }

    /* Isi opsi jawaban memakai gaya yang sama dengan badan soal. */
    .opsi-teks { display: inline; }
    .opsi-teks .pecahan, .soal-body .pecahan { font-size: .95em; }
</style>
