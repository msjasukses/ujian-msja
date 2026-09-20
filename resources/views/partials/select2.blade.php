{{--
    Select2 untuk seluruh <select> di aplikasi.

    Dipasang otomatis — halaman tidak perlu menandai select-nya satu per satu.
    Select yang tidak ingin diubah cukup diberi data-select2="tidak".

    Aset dilayani dari public/vendor/select2, bukan CDN, dengan alasan yang
    sama seperti Bootstrap: ExamBro dan jaringan sekolah kerap tanpa internet.

    Versi: jQuery 3.7.1, Select2 4.1.0-rc.0, select2-bootstrap-5-theme 1.3.0.
--}}
<script src="{{ asset('vendor/select2/jquery.min.js') }}"></script>
<script src="{{ asset('vendor/select2/select2.min.js') }}"></script>
<script src="{{ asset('vendor/select2/i18n/id.js') }}"></script>
<script>
(function () {
    'use strict';

    /*
     * Kirim formulir begitu pilihannya berubah — pengganti onchange="this.form.submit()".
     *
     * Atribut onchange ikut terpanggil oleh jQuery saat Select2 mengubah
     * nilainya, lalu terpanggil sekali lagi oleh jembatan di bawah: formulir
     * terkirim dua kali. Pendengar biasa seperti ini hanya menerima kiriman
     * jembatan, jadi tepat sekali — dan tetap bekerja bila Select2 gagal
     * dimuat.
     */
    document.addEventListener('change', function (e) {
        var el = e.target;
        if (el && el.matches && el.matches('select[data-kirim-otomatis]') && el.form) {
            el.form.submit();
        }
    });

    var $ = window.jQuery;
    if (! $ || ! $.fn || ! $.fn.select2) return;   // tanpa Select2, select biasa tetap berfungsi

    function pasang(el) {
        if (el.dataset.select2 === 'tidak' || el.classList.contains('select2-hidden-accessible')) return;

        var $el = $(el);
        var kecil = el.classList.contains('form-select-sm');
        var modal = el.closest('.modal');

        $el.select2({
            theme: 'bootstrap-5',
            language: 'id',
            // Lebar dari atribut style bila ada (mis. kotak kecil penjodohan),
            // selain itu memenuhi wadahnya seperti .form-select. Nilai persen,
            // bukan piksel, supaya tetap benar untuk select yang dipasang saat
            // bagiannya sedang tersembunyi.
            width: el.style.width ? 'style' : '100%',
            // Daftar boleh lebih lebar dari kotaknya, supaya pilihan panjang
            // tidak terlipat di kotak kecil seperti penyaring kepala tabel.
            dropdownAutoWidth: true,
            /*
             * Sengaja tanpa placeholder. Select2 menyembunyikan pilihan
             * placeholder dari daftar, padahal pilihan kosong di aplikasi ini
             * kerap berupa pilihan sungguhan — "Semua kelas", "— tanpa topik —".
             * Dengan placeholder, sekali memilih satu kelas, pengawas tidak
             * bisa kembali ke "Semua kelas". Tanpanya, setiap pilihan tetap
             * bisa dipilih, persis seperti select biasa.
             */
            // Kotak pencarian hanya untuk daftar yang panjang. Di daftar pendek
            // ia tidak membantu, dan di ponsel justru memunculkan papan ketik.
            minimumResultsForSearch: 8,
            selectionCssClass: kecil ? 'select2--small' : '',
            dropdownCssClass: kecil ? 'select2--small' : '',
            // Di dalam modal, daftar harus ditempel ke modalnya; kalau tidak,
            // kotak pencariannya tidak bisa diketik.
            dropdownParent: modal ? $(modal) : $(document.body)
        });

        /*
         * Jembatan kejadian change.
         *
         * Select2 mengabarkan perubahan lewat jQuery .trigger('change'), dan
         * kabar itu TIDAK sampai ke pendengar addEventListener('change') yang
         * dipakai aplikasi ini — termasuk yang menyimpan jawaban soal
         * penjodohan di lembar ujian. Tanpa jembatan ini, jawaban penjodohan
         * diam-diam berhenti tersimpan.
         *
         * Kabar dari Select2 tidak membawa originalEvent; itulah yang
         * diteruskan sebagai kejadian change biasa. Kejadian terusan itu
         * membawa originalEvent, jadi tidak diteruskan lagi.
         */
        $el.on('change', function (e) {
            if (! e.originalEvent) {
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    function pasangSemua(akar) {
        var daftar = (akar || document).querySelectorAll('select');
        for (var i = 0; i < daftar.length; i++) {
            try {
                pasang(daftar[i]);
            } catch (galat) {
                // Satu select yang bermasalah tidak boleh menggagalkan yang lain;
                // ia tetap tampil sebagai select biasa.
            }
        }
    }

    pasangSemua(document);

    // Select yang ditambahkan belakangan (mis. oleh skrip halaman) ikut dipasang.
    if (window.MutationObserver) {
        new MutationObserver(function (perubahan) {
            perubahan.forEach(function (p) {
                p.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return;
                    if (node.tagName === 'SELECT') pasangSemua(node.parentNode);
                    else if (node.querySelector && node.querySelector('select')) pasangSemua(node);
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    }

    window.Select2Aplikasi = { pasang: pasang, pasangSemua: pasangSemua };
})();
</script>
