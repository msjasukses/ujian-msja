{{--
    Penempatan soal mengikuti topik yang dipilih.

    Topik sudah memuat mata pelajaran, tingkat kelas, dan jurusannya sendiri —
    diisi saat topik dibuat, atau ikut tertarik dari pemetaan CP-TP-ATP
    kurikulum lewat fitur Sinkron. Meminta guru memilih ketiganya sekali lagi
    di bawah topik yang sama hanya membuka satu pintu kekeliruan: soal
    bertopik "Trigonometri" tersimpan pada mapel yang salah, lalu tidak pernah
    muncul ketika paket soalnya disusun — tanpa satu galat pun yang
    menyebutkannya.

    Dua hal yang sengaja TIDAK dikerjakan di sini:

      - Tidak pernah mengosongkan. Topik yang mapelnya belum diisi membiarkan
        pilihan guru apa adanya; isian yang sudah dipilih dengan sadar tidak
        boleh lenyap hanya karena topiknya kebetulan belum lengkap.
      - Tidak memaksakan nilai yang tidak ada di dropdown. Mapel di luar
        pengampuan guru memang tidak muncul sebagai pilihan, dan memasukkannya
        dengan paksa justru membuat kolomnya berubah kosong tanpa sebab yang
        terlihat.

    Isian hanya diubah ketika guru mengganti topik, tidak saat halaman dimuat:
    pada halaman ubah, soal boleh saja sengaja ditempatkan berbeda dari
    topiknya, dan pilihan yang sudah tersimpan itu bukan milik partial ini.

    Nilainya dibaca dari atribut data-mapel, data-tingkat, dan data-jurusan
    pada tiap <option> topik — jadi tidak ada permintaan tambahan ke server.
    Halaman yang memakai partial ini wajib menuliskan ketiga atribut itu.
--}}
@push('scripts')
<script data-penempatan-ikut-topik>
    (function () {
        const topik = document.querySelector('select[name="topik_id"]');
        if (! topik) return;

        function isi(nama, nilai) {
            if (! nilai) return;

            const kolom = document.querySelector('select[name="' + nama + '"]');
            if (! kolom) return;
            if (! kolom.querySelector('option[value="' + nilai + '"]')) return;

            kolom.value = nilai;

            // Select2 melukis kotaknya sendiri dan hanya memperbaruinya ketika
            // diberi tahu lewat jQuery. Tanpa baris ini nilainya benar-benar
            // terpasang — formulir pun mengirimkannya — tetapi yang terbaca
            // guru tetap "— pilih —", dan ia akan memilihnya sekali lagi.
            //
            // Namanya bernamespace dengan sengaja: hanya penangan milik
            // Select2 yang terpanggil, bukan pendengar change biasa di
            // halaman, jadi pengisian ini tidak menjalar ke mana-mana.
            if (window.jQuery && kolom.classList.contains('select2-hidden-accessible')) {
                window.jQuery(kolom).trigger('change.select2');
            }
        }

        topik.addEventListener('change', function () {
            const dipilih = topik.selectedOptions[0];
            if (! dipilih) return;

            isi('mata_pelajaran_id', dipilih.dataset.mapel);
            isi('tingkat_kelas_id', dipilih.dataset.tingkat);
            isi('jurusan_id', dipilih.dataset.jurusan);
        });
    })();
</script>
@endpush
