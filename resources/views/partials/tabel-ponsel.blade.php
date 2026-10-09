{{--
    Tabel di layar ponsel.

    Tabel yang lebih lebar dari layar digeser ke samping di dalam
    .table-responsive. Tanpa aturan ini peramban justru memeras kolom teks
    sampai satu kata per baris — nama ujian setinggi enam baris, tanggal
    terbelah dua — karena kolom lain (angka, lencana, tombol) tidak bisa
    menyusut lagi.

    Karena itu di ponsel isi sel dibuat satu baris, kecuali kolom utama yang
    memuat judul tebal atau teks panjang: kolom itu tetap melipat, tetapi
    dengan lebar minimum yang layak dibaca. Kolom lain yang perlu melipat
    cukup diberi kelas kolom-teks.
--}}
<style>
    @media (max-width: 767.98px) {
        .table-responsive > .table > tbody > tr > td { white-space:nowrap; }
        .table-responsive > .table > tbody > tr > td.kolom-teks { white-space:normal; min-width:13rem; }
        /* Pesan "belum ada data" membentang seluruh kolom; jangan sampai ia
           yang membuat tabel kosong harus digeser. */
        .table-responsive > .table > tbody > tr > td[colspan] { white-space:normal; }
    }

    /* Dipisah dari aturan di atas: peramban yang belum mengenal :has()
       membuang seluruh aturan yang memuatnya, dan aturan lain tidak boleh
       ikut hilang. Di peramban seperti itu kolom utama tetap satu baris —
       lebih lebar, tetapi tidak terhimpit. */
    @media (max-width: 767.98px) {
        .table-responsive > .table > tbody > tr > td:has(> .fw-semibold, > a.fw-semibold, > .text-wrap-2) {
            white-space:normal; min-width:13rem;
        }
    }
</style>
