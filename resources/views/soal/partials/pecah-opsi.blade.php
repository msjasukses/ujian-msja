{{--
    Pemisah opsi jawaban dari teks pertanyaan.

    Guru lazim menyalin satu butir utuh dari Word, PDF, atau grup WhatsApp —
    pertanyaan, opsi A–D, kadang beserta kuncinya — lalu menempelkannya ke
    kolom Pertanyaan. Sebelumnya semua itu ikut tersimpan sebagai satu blok
    teks: opsi tampil dua kali di lembar siswa (sekali di badan soal, sekali
    di daftar opsi yang kosong) dan kuncinya ikut terbaca peserta.

    Begitu bentuk seperti itu dikenali, formulir menawarkan memindahkannya:
    teks pertanyaan dipangkas sampai sebelum opsi A, tiap opsi masuk ke
    kolomnya sendiri sesuai hurufnya, dan kunci yang tertulis ikut dicentang.
    Tawaran, bukan tindakan diam-diam — guru menekan "Pindahkan" sendiri, dan
    "Urungkan" mengembalikan isian persis seperti semula bila salah terbaca.
--}}
<div id="pecahOpsiKotak" class="alert alert-primary py-2 px-3 mt-2 d-none">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="small" id="pecahOpsiPesan"></div>
        <div class="d-flex gap-2 flex-shrink-0">
            <button type="button" class="btn btn-sm btn-primary" id="pecahOpsiTerapkan">
                <i class="bi bi-box-arrow-in-down me-1"></i>Pindahkan
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="pecahOpsiAbaikan">
                Abaikan
            </button>
        </div>
    </div>
</div>

<div id="pecahOpsiUrung" class="alert alert-success py-2 px-3 mt-2 d-none">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="small" id="pecahOpsiUrungPesan"></div>
        <button type="button" class="btn btn-sm btn-outline-secondary flex-shrink-0" id="pecahOpsiUrungTombol">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Urungkan
        </button>
    </div>
</div>

@push('scripts')
<script>
(function () {
    'use strict';

    const MAKS_OPSI = 8;

    // Pemisah setelah huruf opsi dibuat longgar: "A.", "A)", "(A)", "A:" —
    // keempatnya sama-sama lazim dipakai guru.
    const POLA_OPSI  = /^[\s ]*\(?([A-Ha-h])[\s ]*[.)\]:][\s ]*/;
    const POLA_KUNCI = /^[\s ]*(?:kunci(?:[\s ]*jawaban)?|jawaban)[\s ]*[:.–-][\s ]*(.*)$/i;
    const POLA_BAHAS = /^[\s ]*(?:pembahasan|penjelasan)[\s ]*[:.–-][\s ]*(.*)$/i;
    const POLA_NOMOR = /^[\s ]*\(?\d{1,3}[\s ]*[.)][\s ]*/;

    let hasil = null;      // hasil pembacaan terakhir, null bila tidak ada opsi
    let sebelum = null;    // potret isian sebelum dipindahkan, untuk "Urungkan"
    let abaikanUntuk = null;
    let tunda = null;

    // ------------------------------------------------------------------
    // Pembacaan teks
    // ------------------------------------------------------------------

    function kolomPertanyaan() {
        return document.querySelector('textarea[name=pertanyaan]');
    }

    function kolomPembahasan() {
        return document.querySelector('textarea[name=pembahasan]');
    }

    function teksDari(html) {
        const wadah = document.createElement('div');
        wadah.innerHTML = html;

        return wadah.textContent.replace(/​/g, '');
    }

    function lolos(teks) {
        const wadah = document.createElement('div');
        wadah.textContent = teks;

        return wadah.innerHTML;
    }

    /**
     * Buang awalan yang cocok dengan pola dari sepotong HTML.
     *
     * Polanya diuji pada teks murni, lalu huruf sebanyak itu dihapus dari
     * simpul teks pertama — supaya "A. 3<sup>2</sup>" tetap menjadi
     * "3<sup>2</sup>", bukan teks polos yang kehilangan pangkatnya.
     */
    function buangAwalan(html, pola) {
        const wadah = document.createElement('div');
        wadah.innerHTML = html;

        const cocok = pola.exec(wadah.textContent);
        if (!cocok) return String(html || '').trim();

        let sisa = cocok[0].length;
        const jalan = document.createTreeWalker(wadah, NodeFilter.SHOW_TEXT);
        let simpul;

        while (sisa > 0 && (simpul = jalan.nextNode())) {
            const isi = simpul.nodeValue;

            if (isi.length <= sisa) {
                sisa -= isi.length;
                simpul.nodeValue = '';
            } else {
                simpul.nodeValue = isi.slice(sisa);
                sisa = 0;
            }
        }

        return wadah.innerHTML.trim();
    }

    /** Pecah isi kolom menjadi baris; tag blok dan <br> jadi pembatasnya. */
    function pecahBaris(html) {
        const rata = String(html || '')
            .replace(/<br\s*\/?>/gi, '\n')
            .replace(/<\/(?:p|div|li|tr|h[1-6]|blockquote)\s*>/gi, '\n')
            .replace(/<(?:p|div|li|tr|h[1-6]|blockquote)\b[^>]*>/gi, '\n');

        return rata.split('\n').map(function (potong) {
            return { html: potong.trim(), teks: teksDari(potong).trim() };
        });
    }

    /**
     * Opsi yang berderet dalam satu baris, mis. tempelan dari PDF:
     * "A. Bandung  B. Semarang  C. Surabaya".
     *
     * Hanya dikerjakan pada baris teks polos — memotong HTML di tengah
     * tag justru merusak pangkat, pecahan, dan gambar di dalamnya.
     */
    function pisahInline(teks) {
        const batas = [];
        let dari = 0;

        for (let k = 0; k < MAKS_OPSI; k++) {
            const pola = new RegExp('(^|[\\s\\u00a0])\\(?' + String.fromCharCode(65 + k) + '[.)][\\s\\u00a0]');
            const cocok = pola.exec(teks.slice(dari));
            if (!cocok) break;

            const pos = dari + cocok.index + cocok[1].length;
            batas.push(pos);
            dari = pos + 2;
        }

        if (batas.length < 2) return null;

        const keluar = [];
        if (batas[0] > 0) keluar.push(teks.slice(0, batas[0]));

        batas.forEach(function (awal, k) {
            keluar.push(teks.slice(awal, k + 1 < batas.length ? batas[k + 1] : undefined));
        });

        return keluar.map(t => t.trim()).filter(t => t !== '');
    }

    function mekarkan(baris) {
        const keluar = [];

        baris.forEach(function (b) {
            const bagian = b.html.includes('<') ? null : pisahInline(b.teks);

            if (bagian) {
                bagian.forEach(t => keluar.push({ html: lolos(t), teks: t }));

                return;
            }

            keluar.push(b);
        });

        return keluar;
    }

    /**
     * Kunci yang tertulis: "A", "a", "A dan C", "AC".
     *
     * Kalimat utuh seperti "Jawaban: Bandung" sengaja tidak diterima —
     * mencentang B, A, lalu D karena huruf-huruf itu kebetulan ada di
     * dalamnya jauh lebih merugikan daripada membiarkan kunci kosong.
     */
    function hurufKunci(mentah, jumlah) {
        if (!mentah) return [];

        const bersih = mentah.toUpperCase()
            .replace(/\bDAN\b|\bATAU\b/g, '')
            .replace(/[\s .,;:/&+-]/g, '');

        if (bersih === '' || bersih.length > jumlah || !/^[A-H]+$/.test(bersih)) return [];

        return [...new Set(bersih.split(''))].filter(h => h.charCodeAt(0) - 65 < jumlah);
    }

    /**
     * Kunci yang ditulis ulang sebagai teks opsinya, mis. "Kunci: Bandung".
     * Dicocokkan ke daftar opsi supaya tidak perlu dicentang manual.
     */
    function kunciDariTeks(mentah, opsi) {
        const cari = teksDari(mentah || '').trim().toLowerCase();
        if (cari === '') return [];

        const ke = opsi.findIndex(o => teksDari(o).trim().toLowerCase() === cari);

        return ke < 0 ? [] : [String.fromCharCode(65 + ke)];
    }

    function rakitPertanyaan(baris) {
        const isi = baris.map(b => b.html);

        while (isi.length && isi[0].trim() === '') isi.shift();
        while (isi.length && isi[isi.length - 1].trim() === '') isi.pop();

        // Nomor butir ikut tersalin dari naskah, padahal aplikasi menomori
        // soal sendiri sesuai urutannya di paket.
        if (isi.length) isi[0] = buangAwalan(isi[0], POLA_NOMOR);

        return isi.join('<br>');
    }

    /** Baca isi kolom pertanyaan; null bila di dalamnya tidak ada daftar opsi. */
    function analisis(html) {
        const baris = mekarkan(pecahBaris(html));
        let mulai = -1;

        for (let i = 0; i < baris.length; i++) {
            const cocok = POLA_OPSI.exec(baris[i].teks);

            if (cocok && cocok[1].toUpperCase() === 'A') {
                mulai = i;
                break;
            }
        }

        if (mulai < 0) return null;

        const pertanyaan = baris.slice(0, mulai);
        const opsi = [];
        let harap = 0;
        let kunci = null;
        let pembahasan = null;
        let berhenti = false;   // sudah lewat baris kunci/pembahasan

        for (let i = mulai; i < baris.length; i++) {
            const b = baris[i];
            if (b.teks === '') continue;

            const cocok = POLA_OPSI.exec(b.teks);

            if (!berhenti && cocok && opsi.length < MAKS_OPSI
                && cocok[1].toUpperCase() === String.fromCharCode(65 + harap)) {
                opsi.push(buangAwalan(b.html, POLA_OPSI));
                harap++;
                continue;
            }

            const adaKunci = POLA_KUNCI.exec(b.teks);

            if (adaKunci) {
                kunci = adaKunci[1];
                berhenti = true;
                continue;
            }

            const adaBahas = POLA_BAHAS.exec(b.teks);

            if (adaBahas) {
                pembahasan = adaBahas[1];
                berhenti = true;
                continue;
            }

            // Opsi yang teksnya panjang kerap terpotong ke baris berikutnya.
            if (!berhenti && opsi.length) {
                opsi[opsi.length - 1] = (opsi[opsi.length - 1] + ' ' + b.html).trim();
                continue;
            }

            if (pembahasan !== null) {
                pembahasan = (pembahasan + ' ' + b.teks).trim();
                continue;
            }

            pertanyaan.push(b);
        }

        if (opsi.length < 2) return null;

        const huruf = hurufKunci(kunci, opsi.length);
        const terbaca = huruf.length ? huruf : kunciDariTeks(kunci, opsi);

        return {
            pertanyaan: rakitPertanyaan(pertanyaan),
            opsi: opsi,
            kunci: terbaca,
            // Kuncinya tertulis tetapi tidak terbaca sebagai salah satu opsi —
            // guru perlu diberi tahu, bukan dibiarkan menyimpan soal tanpa kunci.
            kunciRagu: !!kunci && terbaca.length === 0,
            pembahasan: pembahasan,
        };
    }

    // ------------------------------------------------------------------
    // Penerapan ke formulir
    // ------------------------------------------------------------------

    function setel(kolom, nilai) {
        if (!kolom) return;

        if (window.EditorSoal && window.EditorSoal.setel) {
            window.EditorSoal.setel(kolom, nilai);
        } else {
            kolom.value = nilai;
        }
    }

    /** Sediakan tepat sebanyak yang dibutuhkan baris opsi. */
    function siapkanBaris(jumlah) {
        const daftar = document.getElementById('daftarOpsi');
        if (!daftar) return [];

        while (daftar.children.length > jumlah) daftar.lastElementChild.remove();
        while (daftar.children.length < jumlah && daftar.children.length < MAKS_OPSI) tambahOpsi();

        nomoriUlangOpsi();

        return [...daftar.children];
    }

    function potret() {
        return {
            pertanyaan: kolomPertanyaan() ? kolomPertanyaan().value : '',
            pembahasan: kolomPembahasan() ? kolomPembahasan().value : '',
            opsi: [...document.querySelectorAll('#daftarOpsi .baris-opsi')].map(function (baris) {
                const kolom = baris.querySelector('input[name="opsi_text[]"]');
                const centang = baris.querySelector('.kunci-opsi');

                return { teks: kolom ? kolom.value : '', kunci: !!(centang && centang.checked) };
            }),
        };
    }

    function pulihkan(awal) {
        setel(kolomPertanyaan(), awal.pertanyaan);
        setel(kolomPembahasan(), awal.pembahasan);

        siapkanBaris(awal.opsi.length).forEach(function (baris, i) {
            if (!awal.opsi[i]) return;

            setel(baris.querySelector('input[name="opsi_text[]"]'), awal.opsi[i].teks);

            const centang = baris.querySelector('.kunci-opsi');
            if (centang) centang.checked = awal.opsi[i].kunci;
        });
    }

    function terapkan() {
        if (!hasil) return;

        sebelum = potret();

        // Pilihan ganda biasa hanya mengenal satu kunci; bila naskahnya
        // menulis lebih, yang pertama saja yang dipakai.
        const satuKunci = jenisTerpilih() === 'pg';
        const kunci = satuKunci ? hasil.kunci.slice(0, 1) : hasil.kunci;

        setel(kolomPertanyaan(), hasil.pertanyaan);

        siapkanBaris(hasil.opsi.length).forEach(function (baris, i) {
            setel(baris.querySelector('input[name="opsi_text[]"]'), hasil.opsi[i] || '');

            const centang = baris.querySelector('.kunci-opsi');
            if (centang) centang.checked = kunci.includes(String.fromCharCode(65 + i));
        });

        // Pembahasan yang sudah ditulis guru tidak ditimpa.
        if (hasil.pembahasan && kolomPembahasan() && kolomPembahasan().value.trim() === '') {
            setel(kolomPembahasan(), hasil.pembahasan);
        }

        const pesan = 'Opsi A–' + String.fromCharCode(64 + hasil.opsi.length) + ' dipindahkan'
            + (kunci.length ? ', kunci ' + kunci.join(', ') + ' dicentang' : '')
            + '. '
            + (hasil.kunciRagu
                ? 'Kunci yang tertulis tidak dikenali, jadi belum ada yang dicentang — tandai sendiri.'
                : 'Periksa sebentar sebelum disimpan.');

        hasil = null;
        document.getElementById('pecahOpsiKotak').classList.add('d-none');
        document.getElementById('pecahOpsiUrungPesan').textContent = pesan;
        document.getElementById('pecahOpsiUrung').classList.remove('d-none');
    }

    // ------------------------------------------------------------------
    // Pemicu
    // ------------------------------------------------------------------

    function periksa() {
        const kotak = document.getElementById('pecahOpsiKotak');
        const kolom = kolomPertanyaan();
        if (!kotak || !kolom) return;

        const jenis = typeof jenisTerpilih === 'function' ? jenisTerpilih() : 'pg';
        const nilai = kolom.value;

        hasil = (jenis === 'pg' || jenis === 'pg_kompleks') ? analisis(nilai) : null;

        if (!hasil || nilai === abaikanUntuk) {
            kotak.classList.add('d-none');

            return;
        }

        document.getElementById('pecahOpsiUrung').classList.add('d-none');
        document.getElementById('pecahOpsiPesan').innerHTML =
            'Teks pertanyaan memuat <strong>' + hasil.opsi.length + ' opsi jawaban</strong> '
            + '(A–' + String.fromCharCode(64 + hasil.opsi.length) + ')'
            + (hasil.kunci.length ? ' beserta kuncinya' : '')
            + '. Pindahkan ke kolom Opsi Jawaban?';
        kotak.classList.remove('d-none');
    }

    function jadwalkan() {
        clearTimeout(tunda);
        tunda = setTimeout(periksa, 250);
    }

    /**
     * Tawaran "Urungkan" hanya berlaku selama isian belum disentuh lagi.
     * Potret yang sudah basi justru berbahaya: menekannya akan membuang
     * suntingan yang guru ketik sesudah pemindahan.
     */
    function lupakanUrung() {
        if (!sebelum) return;

        sebelum = null;
        document.getElementById('pecahOpsiUrung').classList.add('d-none');
    }

    document.addEventListener('input', function (e) {
        const kolom = kolomPertanyaan();
        if (!kolom) return;

        if (e.target.closest && e.target.closest('#daftarOpsi')) lupakanUrung();

        // Kolom aslinya tersembunyi di balik editor kaya; keduanya diawasi
        // supaya tawaran tetap muncul walau JavaScript editornya tidak jalan.
        if (e.target === kolom || e.target === kolom.nextElementSibling) {
            lupakanUrung();
            jadwalkan();
        }
    });

    document.addEventListener('change', function (e) {
        if (e.target && e.target.name === 'jenis') jadwalkan();
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('pecahOpsiTerapkan').addEventListener('click', terapkan);

        document.getElementById('pecahOpsiAbaikan').addEventListener('click', function () {
            const kolom = kolomPertanyaan();
            abaikanUntuk = kolom ? kolom.value : null;
            document.getElementById('pecahOpsiKotak').classList.add('d-none');
        });

        document.getElementById('pecahOpsiUrungTombol').addEventListener('click', function () {
            if (!sebelum) return;

            pulihkan(sebelum);
            abaikanUntuk = kolomPertanyaan() ? kolomPertanyaan().value : null;
            sebelum = null;
            document.getElementById('pecahOpsiUrung').classList.add('d-none');
        });

        periksa();
    });
})();
</script>
@endpush
