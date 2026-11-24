{{--
    Editor isi soal yang menampilkan hasilnya langsung.

    Sebelumnya kolom pertanyaan berupa textarea polos, sehingga menyisipkan
    pangkat atau pecahan memunculkan tulisan <span class="pecahan">… di layar
    guru — bentuk yang mustahil dibaca, apalagi disunting. Padahal justru
    pangkat, akar, dan pecahan itulah yang paling sering dipakai guru
    matematika.

    Karena itu tiap kolom teks soal diberi lapisan contenteditable yang
    menampilkan hasil jadinya. Kolom aslinya tidak dibuang, hanya disembunyikan
    dan tetap ikut terkirim: nama, nilai awal dari old(), pesan galat validasi,
    dan penanganan di sisi server semuanya tetap seperti semula. Bila JavaScript
    gagal berjalan, kolom aslinya muncul kembali dan formulir tetap terpakai.
--}}
<style>
    .editor-kaya {
        min-height: 2.6rem;
        height: auto;
        overflow-y: auto;
        cursor: text;
        line-height: 1.7;
    }
    .editor-kaya-luas { min-height: 8.5rem; }
    .editor-kaya:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 .25rem rgba(13,110,253,.25);
        outline: 0;
    }
    /* Penunjuk isi kolom, meniru placeholder bawaan kolom teks. */
    .editor-kaya:empty::before {
        content: attr(data-placeholder);
        color: #adb5bd;
        pointer-events: none;
    }
    .editor-kaya.is-invalid { border-color: #dc3545; }

    /* Susunan bertingkat perlu ruang vertikal lebih agar tidak berdesakan. */
    .editor-kaya .pecahan, .editor-kaya .akar { user-select: text; }
    .editor-kaya .pembilang:empty::after,
    .editor-kaya .penyebut:empty::after,
    .editor-kaya .radikan:empty::after {
        content: '\00a0\00a0';   /* ruang kecil supaya bagian kosong tetap bisa diklik */
    }
</style>

@push('scripts')
<script>
window.EditorSoal = (function () {
    'use strict';

    /** Tag yang dipertahankan saat menempel dari Word atau halaman web. */
    const TAG_BOLEH = ['B','STRONG','I','EM','U','S','SUP','SUB','BR','SPAN','SMALL','MARK',
                       'CODE','P','DIV','UL','OL','LI','TABLE','THEAD','TBODY','TR','TD','TH','IMG'];
    const ATRIBUT_BOLEH = ['dir','lang','class','colspan','rowspan','src','alt'];
    const KELAS_BOLEH = ['teks-arab','pecahan','pembilang','penyebut','akar','radikan','gambar-sebaris'];

    /*
     * Rentang tulisan Arab: huruf Arab dan turunannya, harakat, tatwil,
     * tanda baca Arab, angka Arab, serta bentuk sambungnya.
     */
    const AKSARA_ARAB = '\\u0600-\\u06FF\\u0750-\\u077F\\u08A0-\\u08FF\\uFB50-\\uFDFF\\uFE70-\\uFEFF';

    /** Satu untaian Arab utuh — spasi di tengahnya ikut, di ujungnya tidak. */
    const POLA_ARAB = new RegExp(
        '[' + AKSARA_ARAB + '](?:[' + AKSARA_ARAB + '\\s\\u200c\\u200d]*[' + AKSARA_ARAB + '])?',
        'g'
    );

    /*
     * Huruf Arab sungguhan, bukan angka atau tanda baca semata. Angka Arab
     * "١" yang berdiri sendiri — nomor urut pada daftar kosakata, misalnya —
     * tidak perlu diperlakukan sebagai kutipan Arab.
     */
    const POLA_HURUF_ARAB = new RegExp('[\\u0620-\\u064A\\u066E-\\u06D3\\u06FA-\\u06FF\\u0750-\\u077F\\u08A0-\\u08FF\\uFB50-\\uFDFF\\uFE70-\\uFEFF]');

    let editorAktif = null;
    let rentangTersimpan = null;

    /*
     * Awalan subfolder aplikasi, mis. "/cbt" (kosong di akar domain).
     *
     * Gambar tersimpan sebagai "/storage/...". Di editor ia ditampilkan
     * dengan awalan ini — tanpanya peramban memintanya dari akar domain dan
     * gambarnya tampil rusak — lalu dikembalikan ke bentuk netral saat isi
     * editor disalin ke kolom formulir. Lihat App\Support\AlamatPublik.
     */
    const DASAR = @json(\App\Support\AlamatPublik::dasar());
    const POLA_SIMPAN = new RegExp('(\\ssrc\\s*=\\s*["\'])' + DASAR.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '/storage/', 'gi');

    function alamatTampil(html) {
        return DASAR ? html.replace(/(\ssrc\s*=\s*["'])\/storage\//gi, '$1' + DASAR + '/storage/') : html;
    }

    function alamatSimpan(html) {
        return DASAR ? html.replace(POLA_SIMPAN, '$1/storage/') : html;
    }

    // ------------------------------------------------------------------
    // Pemasangan
    // ------------------------------------------------------------------

    function pasang(kolom) {
        if (kolom.dataset.editorTerpasang) return null;
        kolom.dataset.editorTerpasang = '1';

        const luas = kolom.tagName === 'TEXTAREA';
        const editor = document.createElement('div');

        editor.className = 'editor-kaya form-control soal-body' + (luas ? ' editor-kaya-luas' : '');
        editor.contentEditable = 'true';
        editor.spellcheck = false;
        editor.setAttribute('dir', kolom.getAttribute('dir') || 'auto');
        editor.setAttribute('role', 'textbox');
        editor.setAttribute('aria-multiline', luas ? 'true' : 'false');
        editor.dataset.placeholder = kolom.getAttribute('placeholder') || '';
        editor.dataset.labelSisip = kolom.dataset.labelSisip || '';
        editor.dataset.editorUntuk = '1';

        if (kolom.classList.contains('is-invalid')) editor.classList.add('is-invalid');

        editor.innerHTML = keHtml(kolom.value, ! luas);
        tataGambar(editor);

        kolom.insertAdjacentElement('afterend', editor);
        kolom.classList.add('d-none');
        // Kolom tersembunyi yang bertanda required membuat peramban menolak
        // mengirim formulir tanpa pernah menampilkan kesalahannya. Kewajiban
        // isian tetap diperiksa di sisi server.
        kolom.removeAttribute('required');

        editor.addEventListener('input', () => sinkron(editor, kolom));

        editor.addEventListener('keydown', function (e) {
            // Tab menyusuri susunan bertingkat: pembilang → penyebut → keluar.
            // Langkah "keluar" itu yang penting — tanpanya kursor terperangkap
            // di dalam pecahan dan seluruh kalimat berikutnya ikut tertulis
            // sebagai penyebut.
            if (e.key === 'Tab') {
                const pembilang = terdekat('.pembilang');

                if (pembilang) {
                    const penyebut = pembilang.parentNode.querySelector('.penyebut');

                    if (penyebut) {
                        e.preventDefault();
                        pilihIsi(penyebut);

                        return;
                    }
                }

                const dalam = terdekat('.penyebut, .radikan');

                if (dalam) {
                    e.preventDefault();
                    keluarDari(dalam.closest('.pecahan, .akar'));
                    sinkron(editor, kolom);
                }

                return;
            }

            if (e.key !== 'Enter') return;

            e.preventDefault();
            // Kolom satu baris — mis. opsi jawaban — tidak boleh berganti baris.
            if (luas) document.execCommand('insertLineBreak');
        });

        editor.addEventListener('paste', function (e) {
            // Tempelan gambar ditangani papan sisip. Pemeriksaan ini harus
            // didahulukan: tangkapan layar dari Word datang bersama teks
            // HTML sekaligus berkas gambar, dan bila teksnya diproses di
            // sini gambarnya akan tersisip dua kali.
            const adaGambar = [...(e.clipboardData?.items || [])]
                .some(x => x.kind === 'file' && x.type.startsWith('image/'));

            if (adaGambar) return;

            const html = e.clipboardData?.getData('text/html');
            const teks = e.clipboardData?.getData('text/plain');

            if (!html && !teks) return;

            e.preventDefault();
            // Tempelan teks polos tidak melewati penyaring, jadi pemagaran
            // Arabnya dipanggil di sini — jalur inilah yang paling sering
            // dipakai saat menyalin kosakata Arab dari mana pun.
            document.execCommand('insertHTML', false, html
                ? saring(html, ! luas)
                : pagariArab(konversiLatexDalamTeks(teks)));
            tataGambar(editor);
            sinkron(editor, kolom);
        });

        // Huruf Arab yang diketik langsung — dari papan ketik Arab Windows
        // maupun tombol hijaiyah — baru dipagari setelah kolomnya ditinggalkan.
        // Menatanya sambil diketik akan melemparkan kursor ke tempat lain pada
        // tiap huruf.
        editor.addEventListener('blur', function (e) {
            // Tombol papan sisip merebut fokus sebentar saat ditekan, dan
            // papan rumus merebutnya selama modalnya terbuka. Menata ulang isi
            // pada saat itu membuat posisi kursor yang disimpan menunjuk simpul
            // yang sudah tidak ada, dan sisipan berikutnya mendarat di awal
            // kolom.
            if (e.relatedTarget?.closest('#papanSimbol, #papanRumus')) return;

            if (! bungkusArab(editor)) return;

            sinkron(editor, kolom);

            // Isi kolom baru saja disusun ulang, jadi posisi kursor yang
            // tersimpan menunjuk simpul yang sudah tidak ada. Diarahkan ke
            // ujung isi supaya sisipan berikutnya dari papan simbol mendarat
            // di tempat yang masuk akal, bukan di awal kolom.
            if (editorAktif === editor) {
                const ujung = document.createRange();
                ujung.selectNodeContents(editor);
                ujung.collapse(false);
                rentangTersimpan = ujung;
            }
        });

        return editor;
    }

    function pasangSemua(akar) {
        (akar || document).querySelectorAll('[data-sisip-target]').forEach(pasang);
    }

    function sinkron(editor, kolom) {
        const tujuan = kolom || editor.previousElementSibling;
        if (!tujuan) return;

        // Peramban menyisakan <br> tunggal pada kolom yang baru dikosongkan;
        // itu bukan isi, dan kalau ikut tersimpan soal tampak "berisi".
        const isi = editor.innerHTML
            .replace(/\u200B/g, '')
            .replace(/^\s*<br\s*\/?>\s*$/i, '')
            .trim();

        tujuan.value = alamatSimpan(isi);
        editor.classList.toggle('is-invalid', false);
    }

    /**
     * Tinggi sebuah gambar yang masih masuk akal sebagai bagian dari kalimat.
     *
     * Rumus pada naskah Word kerap bukan huruf melainkan gambar: pecahan,
     * akar, dan pangkat dibuat di penyunting persamaan lalu ikut tersalin
     * sebagai potongan gambar kecil, setinggi satu sampai dua baris. Gambar
     * soal yang sesungguhnya — grafik, denah, foto benda — jauh lebih tinggi
     * dari itu.
     */
    const TINGGI_SEBARIS = 110;

    /**
     * Tentukan gambar mana yang berdiri sendiri dan mana yang bagian kalimat.
     *
     * Badan soal memperlakukan gambar sebagai blok: ia berdiri sendiri di
     * antara dua baris tulisan, sebagaimana mestinya gambar soal. Rumus yang
     * tersalin sebagai gambar ikut terkena aturan itu, dan satu kalimat soal
     * pecah menjadi tiga baris — "Bentuk dari", pecahannya, lalu "dalam
     * pangkat positif adalah". Yang kecil karena itu ditandai supaya tetap
     * mengalir bersama tulisannya.
     *
     * Penandanya ikut tersimpan ke bank soal, bukan dihitung ulang di tiap
     * halaman: lembar ujian siswa dan naskah cetak tidak mengukur gambar,
     * mereka hanya membaca apa yang sudah tertulis.
     */
    function tataGambar(editor) {
        if (!editor) return;

        editor.querySelectorAll('img').forEach(function (img) {
            if (img.complete && img.naturalHeight) {
                tandaiGambar(img);

                return;
            }

            // Gambar dari tempelan baru selesai dimuat beberapa saat
            // kemudian; ukurannya belum diketahui pada saat ini.
            img.addEventListener('load', function () {
                tandaiGambar(img);
                sinkron(editor);
            }, { once: true });
        });
    }

    function tandaiGambar(img) {
        img.classList.toggle('gambar-sebaris', img.naturalHeight <= TINGGI_SEBARIS);

        // Sisa atribut kosong tidak perlu ikut tersimpan.
        if (img.className === '') img.removeAttribute('class');
    }

    /** Nilai tersimpan bisa berupa teks polos maupun HTML; keduanya diterima. */
    function keHtml(nilai, satuBaris) {
        const teks = (nilai || '').toString();

        if (teks === '') return '';
        if (/<[a-z][\s\S]*>/i.test(teks)) return alamatTampil(saring(teks, satuBaris));

        return lolos(teks).replace(/\n/g, '<br>');
    }

    /**
     * Ratakan isi menjadi satu baris.
     *
     * Kolom opsi jawaban dan pasangan penjodohan tingginya satu baris; sebuah
     * tempelan beralinea dari Word akan merenggangkan barisnya dan merusak
     * susunan daftar opsi. Tag bloknya dibuang, isinya tetap.
     *
     * Tabel adalah kekecualiannya. Guru yang menempelkan tabel ke kolom opsi
     * memang memaksudkan tabelnya; diratakan, yang tersisa hanya deretan kata
     * tanpa bentuk — "No Nama 1 Air" — dan tidak ada cara memulihkannya.
     * Kolomnya memanjang sendiri mengikuti isi, jadi opsi bertabel tetap
     * terbaca tanpa merusak deretan opsi lainnya.
     */
    function ratakan(wadah) {
        wadah.querySelectorAll('p, div, li, ul, ol, br').forEach(function (el) {
            if (el.closest('table')) return;

            if (el.tagName === 'BR') {
                el.replaceWith(document.createTextNode(' '));

                return;
            }

            el.replaceWith(...el.childNodes, document.createTextNode(' '));
        });

        rapatkanSpasi(wadah);
    }

    /**
     * Rapatkan sisa spasi bekas tag blok yang dibuka.
     *
     * Tiap blok menyisakan satu spasi, dan tempelan Word yang bersarang bisa
     * menyisakan selusin sekaligus. Peramban mengubah deretan itu menjadi
     * rentetan &nbsp; yang ikut tersimpan ke bank soal — tampak sebagai opsi
     * yang menggantung jauh dari hurufnya. Isi tabel dilewati: jarak di
     * dalamnya sudah diatur selnya sendiri.
     */
    function rapatkanSpasi(wadah) {
        // Tiap blok yang dibuka meninggalkan simpul teks berisi satu spasi,
        // masing-masing berdiri sendiri. Tanpa disatukan lebih dulu, deretan
        // itu tidak pernah terbaca sebagai spasi berturut-turut.
        wadah.normalize();

        const jalan = document.createTreeWalker(wadah, NodeFilter.SHOW_TEXT);
        const teks = [];
        let simpul;

        while ((simpul = jalan.nextNode())) {
            if (! simpul.parentElement?.closest('table')) teks.push(simpul);
        }

        teks.forEach(function (t) {
            t.textContent = t.textContent.replace(/[\s ]+/g, ' ');
        });

        if (teks.length === 0) return;

        teks[0].textContent = teks[0].textContent.replace(/^ /, '');
        teks[teks.length - 1].textContent = teks[teks.length - 1].textContent.replace(/ $/, '');
    }

    /**
     * Rapikan tabel hasil tempelan.
     *
     * Word, Excel, dan Google Dokumen membungkus isi tiap sel dengan <p> atau
     * <div> — kerap beberapa sekaligus, termasuk yang kosong sebagai pengganti
     * baris kosong. Margin bawaan paragraf itu membuat baris tabel jadi tinggi
     * dan renggang, padahal di naskah asalnya rapat. Isinya diturunkan menjadi
     * isi sel biasa; pemisah antarblok diganti <br> hanya bila memang ada dua
     * blok yang berisi.
     */
    function rapikanTabel(wadah) {
        wadah.querySelectorAll('td, th').forEach(function (sel) {
            // Berputar beberapa kali karena blok dari Word kerap bersarang:
            // sebuah <div> yang isinya <p>. Batasnya menjaga agar tempelan
            // aneh tidak membuat putaran ini berjalan tanpa akhir.
            for (let putaran = 0; putaran < 5; putaran++) {
                const blok = [...sel.children].filter(el => el.tagName === 'P' || el.tagName === 'DIV');

                if (blok.length === 0) break;

                let adaIsi = false;

                blok.forEach(function (el) {
                    if (el.textContent.trim() === '' && ! el.querySelector('img')) {
                        el.remove();

                        return;
                    }

                    if (adaIsi) el.before(document.createElement('br'));
                    adaIsi = true;

                    el.replaceWith(...el.childNodes);
                });
            }
        });

        // Word memakai tabel bersel tunggal sebagai kotak tata letak — bingkai
        // di sekeliling sebuah kalimat, bukan tabel yang perlu dibaca sebagai
        // tabel. Isinya dikembalikan menjadi teks biasa.
        wadah.querySelectorAll('table').forEach(function (tabel) {
            if (tabel.querySelectorAll('td, th').length > 1) return;

            tabel.querySelectorAll('thead, tbody, tfoot, tr, td, th')
                .forEach(el => el.replaceWith(...el.childNodes));
            tabel.replaceWith(...tabel.childNodes);
        });
    }

    /**
     * Pagari kutipan Arab dengan blok arahnya sendiri.
     *
     * Kolom teks soal memakai dir="auto": peramban menebak arah tulisan dari
     * huruf berarah pertama yang ditemuinya. Satu kata Arab di awal kalimat
     * sudah cukup membuat seluruh kolom dibaca kanan-ke-kiri, sehingga soal
     * berbahasa Indonesia pun terlempar menempel ke tepi kanan — dan nomor
     * urut serta tanda kurung di sekitarnya berpindah tempat.
     *
     * Menyalin kutipan Arab dari mana pun karena itu selalu berakhir begitu;
     * tombol "Blok teks Arab" pada papan sisip hanya menolong yang mengetik
     * sendiri. Di sini tiap untaian Arab dibungkus blok yang sama seperti
     * yang dipasang tombol itu. Pembungkusnya ber-dir sendiri, sehingga
     * tebakan arah pada kolom tidak lagi melihat ke dalamnya: kalimat
     * Indonesia tetap kiri-ke-kanan, dan kutipan Arabnya tetap utuh
     * kanan-ke-kiri di dalam pagarnya.
     *
     * Soal yang seluruhnya berbahasa Arab tidak dirugikan: pembungkusnya
     * memenuhi lebar kolom dan isinya tetap rata kanan.
     *
     * @return {boolean} benar bila ada yang dibungkus.
     */
    function bungkusArab(wadah) {
        let berubah = false;

        // Alinea yang isinya memang Arab seluruhnya tidak dipagari kata demi
        // kata, melainkan diberi arah pada alineanya sendiri. Dengan begitu
        // titik, tanda tanya, dan tanda kutip di ujung kalimat berpindah ke
        // tempat yang benar — pada tulisan Arab tempatnya di ujung kiri —
        // dan barisnya rata kanan seperti naskah Arab pada umumnya.
        wadah.querySelectorAll('p, div, li, blockquote, td, th').forEach(function (el) {
            if (el.hasAttribute('dir')) return;

            // Yang ditandai hanya blok terdalam; menandai pembungkusnya akan
            // ikut membalik alinea berbahasa Indonesia di dalamnya.
            if (el.querySelector('p, div, li, blockquote, table')) return;

            if (! seluruhnyaArab(el.textContent)) return;

            el.setAttribute('dir', 'rtl');
            el.setAttribute('lang', 'ar');
            berubah = true;
        });

        // Tempelan tanpa alinea sama sekali — teks polos, atau satu kalimat
        // yang langsung mendarat di kolom — diperlakukan sama: satu pagar
        // untuk seluruhnya, bukan satu pagar tiap kata.
        if (wadah.childNodes.length > 0
            && ! wadah.querySelector('p, div, li, blockquote, table, .teks-arab, [dir="rtl"]')
            && seluruhnyaArab(wadah.textContent)) {
            const pagar = document.createElement('span');
            pagar.className = 'teks-arab';
            pagar.setAttribute('lang', 'ar');
            pagar.setAttribute('dir', 'rtl');
            pagar.append(...wadah.childNodes);
            wadah.append(pagar);

            return true;
        }

        const jalan = document.createTreeWalker(wadah, NodeFilter.SHOW_TEXT);
        const sasaran = [];
        let simpul;

        while ((simpul = jalan.nextNode())) {
            if (! POLA_HURUF_ARAB.test(simpul.textContent)) continue;

            // Yang sudah berada di dalam pagar arah — entah dari tombol papan
            // sisip, entah dari penempelan sebelumnya — dibiarkan.
            if (simpul.parentElement?.closest('.teks-arab, [dir="rtl"]')) continue;

            sasaran.push(simpul);
        }

        sasaran.forEach(function (teks) {
            const isi = teks.textContent;
            const ganti = document.createElement('span');

            let keluar = '';
            let akhir = 0;
            let cocok;

            POLA_ARAB.lastIndex = 0;

            while ((cocok = POLA_ARAB.exec(isi)) !== null) {
                keluar += lolos(isi.slice(akhir, cocok.index));

                keluar += POLA_HURUF_ARAB.test(cocok[0])
                    ? '<span class="teks-arab" lang="ar" dir="rtl">' + lolos(cocok[0]) + '</span>'
                    : lolos(cocok[0]);

                akhir = cocok.index + cocok[0].length;
            }

            keluar += lolos(isi.slice(akhir));

            ganti.innerHTML = keluar;
            teks.replaceWith(...ganti.childNodes);
        });

        return berubah || sasaran.length > 0;
    }

    /**
     * Apakah sepotong teks memang berbahasa Arab seluruhnya?
     *
     * Angka dan tanda baca dibiarkan: nomor ayat dan titik di ujung kalimat
     * tetap bagian dari naskah Arabnya. Satu kata beraksara Latin sudah cukup
     * menandakan kalimat itu bukan kutipan Arab utuh, melainkan kalimat
     * Indonesia yang memuat kutipan.
     */
    function seluruhnyaArab(teks) {
        return POLA_HURUF_ARAB.test(teks) && ! /[A-Za-z]/.test(teks || '');
    }

    /** Jalankan pemagaran Arab pada sepotong HTML lepas. */
    function pagariArab(html) {
        const wadah = document.createElement('div');
        wadah.innerHTML = html;
        bungkusArab(wadah);

        return wadah.innerHTML;
    }

    // ------------------------------------------------------------------
    // LaTeX
    // ------------------------------------------------------------------

    /**
     * Lambang LaTeX yang lazim dipakai pada soal sekolah, beserta padanan
     * Unicode-nya.
     */
    const LAMBANG_LATEX = {
        times: '×', div: '÷', pm: '±', mp: '∓', cdot: '·', ast: '∗', star: '⋆',
        neq: '≠', ne: '≠', leq: '≤', le: '≤', geq: '≥', ge: '≥', approx: '≈',
        equiv: '≡', sim: '∼', cong: '≅', ll: '≪', gg: '≫', propto: '∝',
        in: '∈', notin: '∉', subset: '⊂', subseteq: '⊆', supset: '⊃', supseteq: '⊇',
        cup: '∪', cap: '∩', emptyset: '∅', varnothing: '∅', forall: '∀', exists: '∃',
        therefore: '∴', because: '∵', neg: '¬', land: '∧', lor: '∨',
        infty: '∞', partial: '∂', nabla: '∇', sum: '∑', prod: '∏',
        int: '∫', iint: '∬', oint: '∮',
        Delta: 'Δ', Gamma: 'Γ', Theta: 'Θ', Lambda: 'Λ', Xi: 'Ξ', Pi: 'Π',
        Sigma: 'Σ', Phi: 'Φ', Psi: 'Ψ', Omega: 'Ω',
        alpha: 'α', beta: 'β', gamma: 'γ', delta: 'δ', epsilon: 'ε', varepsilon: 'ε',
        zeta: 'ζ', eta: 'η', theta: 'θ', iota: 'ι', kappa: 'κ', lambda: 'λ', mu: 'μ',
        nu: 'ν', xi: 'ξ', pi: 'π', rho: 'ρ', sigma: 'σ', tau: 'τ', upsilon: 'υ',
        phi: 'φ', varphi: 'φ', chi: 'χ', psi: 'ψ', omega: 'ω',
        rightarrow: '→', to: '→', leftarrow: '←', leftrightarrow: '↔',
        Rightarrow: '⇒', Leftarrow: '⇐', Leftrightarrow: '⇔', mapsto: '↦',
        circ: '°', degree: '°', angle: '∠', perp: '⊥', parallel: '∥', triangle: '△',
        ldots: '…', dots: '…', cdots: '⋯',
        quad: '  ', qquad: '    ',
    };

    /**
     * Lambang yang perlu diberi jarak di kiri-kanannya — lihat penjelasan
     * yang sama pada App\Support\RumusLatex di sisi server.
     */
    const BERJARAK_LATEX = [
        'times', 'div', 'pm', 'mp', 'cdot', 'ast', 'star',
        'neq', 'ne', 'leq', 'le', 'geq', 'ge', 'approx', 'equiv', 'sim', 'cong',
        'll', 'gg', 'propto',
        'in', 'notin', 'subset', 'subseteq', 'supset', 'supseteq', 'cup', 'cap',
        'land', 'lor',
        'rightarrow', 'to', 'leftarrow', 'leftrightarrow',
        'Rightarrow', 'Leftarrow', 'Leftrightarrow', 'mapsto',
        'perp', 'parallel',
    ];

    /** Perintah yang isinya ditampilkan apa adanya. */
    const BUNGKUS_LATEX = ['text', 'mathrm', 'mathbf', 'mathit', 'operatorname', 'textrm', 'mbox'];

    /**
     * Ubah potongan LaTeX menjadi penanda yang dipakai aplikasi ini.
     *
     * Cakupannya sengaja dibatasi pada yang benar-benar muncul di soal sekolah
     * — pecahan, akar, pangkat, indeks, dan lambang — bukan seluruh LaTeX.
     * Perintah di luar daftar ditulis apa adanya, bukan dibuang, supaya guru
     * bisa melihat bagian mana yang perlu dirapikan sendiri.
     */
    function dariLatex(sumber) {
        const src = String(sumber || '');
        let i = 0;
        let keluar = '';

        // LaTeX merapatkan spasi berturut-turut menjadi satu; nama perintah
        // juga menyerap spasi di belakangnya, sehingga "\pi r" berarti "πr".
        const rapatkan = t => t.replace(/ {2,}/g, ' ');

        // Cari '}' pasangan dari '{' pada posisi awal, menghitung sarangnya.
        function tutupKurung(mulai) {
            let dalam = 0;

            for (let j = mulai; j < src.length; j++) {
                if (src[j] === '\\') { j++; continue; }
                if (src[j] === '{') dalam++;
                else if (src[j] === '}' && --dalam === 0) return j;
            }

            return src.length;
        }

        // Ambil satu satuan berikutnya: sebuah {kelompok}, satu perintah, atau
        // satu huruf. Dipakai sebagai isi pangkat, akar, dan pecahan.
        function satuan() {
            while (src[i] === ' ') i++;

            if (src[i] === '{') {
                const tutup = tutupKurung(i);
                const isi = src.slice(i + 1, tutup);
                i = tutup + 1;

                return dariLatex(isi);
            }

            if (src[i] === '\\') {
                const cocok = /^\\([a-zA-Z]+)[ ]*/.exec(src.slice(i));

                if (cocok) {
                    i += cocok[0].length;

                    return perintah(cocok[1]);
                }

                i += 2;

                return lolos(src[i - 1] || '');
            }

            const huruf = src[i++];

            return lolos(huruf === undefined ? '' : huruf);
        }

        function perintah(nama) {
            if (nama === 'frac' || nama === 'dfrac' || nama === 'tfrac') {
                const atas = satuan();
                const bawah = satuan();

                return '<span class="pecahan"><span class="pembilang">' + atas
                     + '</span><span class="penyebut">' + bawah + '</span></span>';
            }

            if (nama === 'sqrt') {
                let derajat = '';

                // Akar berderajat ditulis \sqrt[3]{27}.
                if (src[i] === '[') {
                    const tutup = src.indexOf(']', i);
                    derajat = '<sup>' + dariLatex(src.slice(i + 1, tutup)) + '</sup>';
                    i = tutup + 1;
                }

                return derajat + '<span class="akar"><span class="radikan">' + satuan() + '</span></span>';
            }

            if (BUNGKUS_LATEX.includes(nama)) return satuan();

            // \left( dan \right) hanya penanda ukuran; kurungnya sendiri tetap.
            if (nama === 'left' || nama === 'right' || nama === 'big' || nama === 'Big') return '';

            if (LAMBANG_LATEX[nama] !== undefined) {
                const lambang = lolos(LAMBANG_LATEX[nama]);

                return BERJARAK_LATEX.includes(nama) ? ' ' + lambang + ' ' : lambang;
            }

            return lolos('\\' + nama);
        }

        while (i < src.length) {
            const c = src[i];

            if (c === '\\') {
                const cocok = /^\\([a-zA-Z]+)[ ]*/.exec(src.slice(i));

                if (cocok) {
                    i += cocok[0].length;
                    keluar += perintah(cocok[1]);

                    continue;
                }

                // \, \; \! \: mengatur jarak; \{ \} \$ adalah huruf biasa.
                const berikut = src[i + 1] || '';
                i += 2;
                keluar += ',;:! '.includes(berikut) ? ' ' : lolos(berikut);

                continue;
            }

            if (c === '^' || c === '_') {
                i++;
                keluar += c === '^'
                    ? '<sup>' + satuan() + '</sup>'
                    : '<sub>' + satuan() + '</sub>';

                continue;
            }

            if (c === '{') {
                const tutup = tutupKurung(i);
                keluar += dariLatex(src.slice(i + 1, tutup));
                i = tutup + 1;

                continue;
            }

            if (c === '}') { i++; continue; }

            i++;
            keluar += lolos(c);
        }

        return rapatkan(keluar);
    }

    /** Apakah sepotong teks memuat rumus LaTeX? */
    function punyaLatex(teks) {
        return /\\\(|\\\[|\$\$|\\frac|\\dfrac|\\sqrt|\\times|\\div|\\pm|\\le|\\ge/.test(teks || '');
    }

    /**
     * Ubah rumus LaTeX yang tersisip di dalam kalimat biasa.
     *
     * Inilah bentuk tempelan yang paling sering ditemui: banyak situs — dan
     * keluaran asisten AI — menuliskan rumusnya sebagai "\(4\sqrt{3}\)" lalu
     * hanya mengirim teks itu ke papan klip, tanpa MathML sama sekali. Tanpa
     * penguraian ini, kode mentahnya yang muncul di badan soal.
     *
     * Pembatas $...$ baru diperlakukan sebagai rumus bila isinya memang memuat
     * perintah LaTeX; kalau tidak, tulisan harga seperti $5 ikut terbaca
     * sebagai rumus.
     */
    function konversiLatexDalamTeks(teks) {
        const pola = /\\\(([\s\S]+?)\\\)|\\\[([\s\S]+?)\\\]|\$\$([\s\S]+?)\$\$|\$([^$\n]*[\\^_{][^$\n]*)\$/g;
        let keluar = '';
        let akhir = 0;
        let m;

        while ((m = pola.exec(teks)) !== null) {
            keluar += lolos(teks.slice(akhir, m.index)).replace(/\n/g, '<br>');
            keluar += dariLatex(m[1] ?? m[2] ?? m[3] ?? m[4]);
            akhir = m.index + m[0].length;
        }

        keluar += lolos(teks.slice(akhir)).replace(/\n/g, '<br>');

        return keluar;
    }

    function lolos(teks) {


        const d = document.createElement('div');
        d.textContent = teks || '';

        return d.innerHTML;
    }

    // ------------------------------------------------------------------
    // Jalan pulang: penanda tampil kembali menjadi LaTeX
    // ------------------------------------------------------------------

    /*
     * Rumus disimpan sebagai penanda tampil, bukan sebagai LaTeX — itulah yang
     * membuatnya bisa dibaca di lembar ujian, naskah cetak, dan berkas analisis
     * tanpa penampil khusus. Harganya: rumus yang sudah tersisip tidak lagi
     * punya kode asalnya. Guru yang salah mengetik penyebut karena itu harus
     * membongkar sendiri susunannya di dalam kolom, bagian demi bagian.
     *
     * Penguraian balik di bawah mengembalikan bentuk LaTeX-nya, sehingga rumus
     * yang terlanjur tersimpan bisa dibuka lagi di papan rumus seperti saat
     * pertama disusun. Cakupannya sama persis dengan dariLatex di atas —
     * pecahan, akar, pangkat, indeks — sehingga sekali putaran selalu kembali
     * ke bentuk semula.
     */

    /** Huruf yang di LaTeX punya arti sendiri, jadi perlu dilindungi. */
    const POLA_LINDUNG = /[\\{}^_$%&#~]/g;

    function lindungi(teks) {
        return (teks || '').replace(POLA_LINDUNG, '\\$&');
    }

    /** Ubah satu simpul penanda tampil menjadi LaTeX. */
    function keLatex(simpul) {
        if (simpul.nodeType === 3) return lindungi(simpul.textContent);
        if (simpul.nodeType !== 1) return '';

        if (simpul.classList.contains('pecahan')) {
            const atas = simpul.querySelector(':scope > .pembilang');
            const bawah = simpul.querySelector(':scope > .penyebut');

            return '\\frac{' + (atas ? isiKeLatex(atas) : '')
                 + '}{' + (bawah ? isiKeLatex(bawah) : '') + '}';
        }

        if (simpul.classList.contains('akar')) {
            const radikan = simpul.querySelector(':scope > .radikan');

            return '\\sqrt{' + (radikan ? isiKeLatex(radikan) : isiKeLatex(simpul)) + '}';
        }

        if (simpul.tagName === 'SUP') return '^{' + isiKeLatex(simpul) + '}';
        if (simpul.tagName === 'SUB') return '_{' + isiKeLatex(simpul) + '}';
        if (simpul.tagName === 'BR') return ' ';

        return isiKeLatex(simpul);
    }

    /** Ubah isi sebuah elemen menjadi LaTeX, anak demi anak. */
    function isiKeLatex(el) {
        const anak = [...el.childNodes];
        let keluar = '';

        for (let i = 0; i < anak.length; i++) {
            const kini = anak[i];
            const lanjut = anak[i + 1];

            // Akar berderajat tersimpan sebagai <sup>3</sup> tepat sebelum
            // akarnya — bentuk yang sama yang dihasilkan \sqrt[3]{27}. Tanpa
            // dikenali di sini, kodenya kembali sebagai "^{3}\sqrt{27}": tampil
            // sama persis, tetapi tidak lagi terbaca sebagai akar pangkat tiga
            // oleh guru yang membuka kodenya.
            if (kini.nodeType === 1 && kini.tagName === 'SUP'
                && lanjut?.nodeType === 1 && lanjut.classList?.contains('akar')) {
                const radikan = lanjut.querySelector(':scope > .radikan');

                keluar += '\\sqrt[' + isiKeLatex(kini) + ']{'
                        + (radikan ? isiKeLatex(radikan) : '') + '}';
                i++;

                continue;
            }

            keluar += keLatex(kini);
        }

        return keluar;
    }

    /**
     * Rumus terluar yang memuat sebuah titik di dalam editor.
     *
     * Yang dicari yang terluar, bukan yang terdekat: pada pecahan yang
     * pembilangnya pecahan lagi, mengklik angka di dalamnya harus membuka
     * seluruh rumusnya — bukan potongan yang tidak berdiri sendiri.
     */
    function rumusTerdekat(sasaran) {
        const mulai = sasaran?.nodeType === 3 ? sasaran.parentElement : sasaran;
        const editor = mulai?.closest?.('[data-editor-untuk]');

        if (!editor) return null;

        let rumus = null;
        let simpul = mulai;

        while (simpul && simpul !== editor) {
            if (simpul.matches?.('.pecahan, .akar, sup, sub')) rumus = simpul;
            simpul = simpul.parentElement;
        }

        // Derajat akar berdiri sebagai simpul tersendiri di depan akarnya;
        // mengklik angka tiga pada ∛27 berarti menyunting akarnya, bukan
        // sebuah pangkat yang kebetulan ada di sebelahnya.
        if (rumus?.tagName === 'SUP' && derajatAkar(rumus.nextSibling)) {
            rumus = rumus.nextSibling;
        }

        return rumus;
    }

    /** Benarkah simpul ini sebuah akar yang didahului derajatnya? */
    function derajatAkar(simpul) {
        return simpul?.nodeType === 1 && simpul.classList.contains('akar');
    }

    /** Tandai sebuah rumus seutuhnya, sehingga sisipan berikutnya menggantinya. */
    function pilihRumus(el) {
        const editor = el?.closest?.('[data-editor-untuk]');

        if (!editor) return false;

        editorAktif = editor;
        editor.focus();

        const rentang = document.createRange();
        rentang.selectNode(el);

        // Akar berderajat ditandai berikut derajatnya. Tanpa itu, mengganti
        // ∛27 dengan rumus lain akan meninggalkan angka tiga kecilnya
        // menggantung sendirian di tengah kalimat.
        const sebelum = el.previousSibling;

        if (el.classList?.contains('akar') && sebelum?.nodeType === 1 && sebelum.tagName === 'SUP') {
            rentang.setStartBefore(sebelum);
        }

        const sel = document.getSelection();
        sel.removeAllRanges();
        sel.addRange(rentang);
        simpanRentang();

        return true;
    }

    /** LaTeX dari bagian yang sedang ditandai; kosong bila tidak ada. */
    function latexTerpilih() {
        if (!aktif()) return '';

        const sel = document.getSelection();

        if (!sel || !sel.rangeCount) return '';

        const rentang = sel.getRangeAt(0);

        if (rentang.collapsed || !editorAktif.contains(rentang.commonAncestorContainer)) {
            return '';
        }

        const wadah = document.createElement('div');
        wadah.appendChild(rentang.cloneContents());

        return isiKeLatex(wadah).trim();
    }

    // ------------------------------------------------------------------
    // Penyaringan tempelan
    // ------------------------------------------------------------------

    /**
     * Buang tag dan atribut di luar daftar. Cerminan dari penyaring di sisi
     * server — yang tetap menjadi penentu akhir; penyaringan di sini semata
     * agar tempelan dari Word tidak membawa serta gaya yang merusak tata letak.
     */
    function saring(html, satuBaris) {
        const wadah = document.createElement('div');
        wadah.innerHTML = html;

        // Simpul komentar dibuang lebih dulu. Google Dokumen menyelipkan
        // penanda <!--StartFragment-->, <!--EndFragment-->, dan penanda
        // internalnya sendiri pada setiap salinan; semuanya tidak tampil di
        // layar tetapi ikut tersimpan ke bank soal dan menyulitkan siapa pun
        // yang kelak membaca isi soalnya.
        const jalanKomentar = document.createTreeWalker(wadah, NodeFilter.SHOW_COMMENT);
        const komentar = [];
        let simpulKomentar;

        while ((simpulKomentar = jalanKomentar.nextNode())) komentar.push(simpulKomentar);
        komentar.forEach(c => c.remove());

        // Rumus dikenali lebih dulu, sebelum atribut dibuang — bentuk
        // aslinya justru tersimpan di atribut dan tag yang akan dibuang itu.
        normalkanRumus(wadah);

        wadah.querySelectorAll('*').forEach(function (el) {
            if (el.tagName === 'SCRIPT' || el.tagName === 'STYLE') {
                el.remove();

                return;
            }

            if (!TAG_BOLEH.includes(el.tagName)) {
                el.replaceWith(...el.childNodes);

                return;
            }

            [...el.attributes].forEach(function (attr) {
                if (!ATRIBUT_BOLEH.includes(attr.name.toLowerCase())) {
                    el.removeAttribute(attr.name);
                }
            });

            if (el.hasAttribute('class')) {
                const kelas = el.className.split(/\s+/).filter(k => KELAS_BOLEH.includes(k));
                kelas.length ? (el.className = kelas.join(' ')) : el.removeAttribute('class');
            }
        });

        rapikanTabel(wadah);
        bungkusArab(wadah);

        if (satuBaris) ratakan(wadah);

        return wadah.innerHTML;
    }

    /**
     * Kenali rumus dari tempelan Word, Google Dokumen, dan halaman web.
     *
     * Sumber-sumber itu jarang memakai <sup> maupun penanda kita:
     *
     *   - Google Dokumen dan sebagian versi Word menandai pangkat lewat
     *     style "vertical-align: super", bukan tag <sup>.
     *   - Rumus dari Word 365, Wikipedia, dan situs bermatematika datang
     *     sebagai MathML (<msup>, <mfrac>, <msqrt>).
     *   - Halaman yang memakai KaTeX atau MathJax mengirim dua salinan
     *     sekaligus: MathML untuk pembaca layar dan tumpukan <span> untuk
     *     mata. Tanpa penanganan, keduanya ikut tertempel dan rumusnya
     *     tampak tertulis dua kali.
     *
     * Semuanya diubah menjadi penanda yang dipakai aplikasi ini, sehingga
     * hasil tempelan tampil sama persis dengan hasil ketikan sendiri.
     */
    function normalkanRumus(wadah) {
        // KaTeX & MathJax: ambil MathML-nya, buang kembaran visualnya.
        wadah.querySelectorAll('.katex, mjx-container').forEach(function (el) {
            const math = el.querySelector('math');
            math ? el.replaceWith(math) : el.replaceWith(...el.childNodes);
        });

        // Sisa kembaran yang disembunyikan dari pembaca layar.
        wadah.querySelectorAll('.katex-html, [aria-hidden="true"]').forEach(el => el.remove());

        wadah.querySelectorAll('math').forEach(function (math) {
            const ganti = document.createElement('span');
            ganti.innerHTML = dariMathML(math);
            math.replaceWith(...ganti.childNodes);
        });

        // Google Dokumen membungkus seluruh tempelan dengan
        // <b style="font-weight:normal"> — tag tebal yang justru dinetralkan
        // oleh gayanya sendiri. Begitu style dibuang, yang tersisa hanya <b>,
        // dan seluruh kalimat yang ditempel berubah menjadi tebal.
        wadah.querySelectorAll('b[style], strong[style], i[style], em[style]').forEach(function (el) {
            const gaya = el.getAttribute('style') || '';
            const tebal = /^(B|STRONG)$/.test(el.tagName) && /font-weight\s*:\s*(normal|400)/i.test(gaya);
            const miring = /^(I|EM)$/.test(el.tagName) && /font-style\s*:\s*normal/i.test(gaya);

            if (tebal || miring) el.replaceWith(...el.childNodes);
        });

        uraiLatexPadaTeks(wadah);

        // Pangkat & indeks yang ditulis sebagai gaya, bukan sebagai tag.
        // Ditelusuri dari dalam ke luar supaya susunan bersarang tetap utuh.
        [...wadah.querySelectorAll('[style]')].reverse().forEach(function (el) {
            const cocok = (el.getAttribute('style') || '').match(/vertical-align\s*:\s*(super|sub)/i);
            if (!cocok) return;

            const tag = document.createElement(cocok[1].toLowerCase() === 'super' ? 'sup' : 'sub');
            tag.innerHTML = el.innerHTML;
            el.replaceWith(tag);
        });
    }

    /**
     * Telusuri simpul teks dan urai rumus LaTeX yang ada di dalamnya.
     *
     * Halaman web kerap menampilkan rumusnya sebagai tulisan biasa, jadi
     * tempelan berbentuk HTML pun masih bisa memuat \(...\) di badan
     * kalimatnya — bukan hanya tempelan teks polos.
     */
    function uraiLatexPadaTeks(wadah) {
        const jalan = document.createTreeWalker(wadah, NodeFilter.SHOW_TEXT);
        const sasaran = [];

        let simpul;
        while ((simpul = jalan.nextNode())) {
            if (punyaLatex(simpul.textContent)) sasaran.push(simpul);
        }

        sasaran.forEach(function (teks) {
            const ganti = document.createElement('span');
            ganti.innerHTML = konversiLatexDalamTeks(teks.textContent);
            teks.replaceWith(...ganti.childNodes);
        });
    }

    /** Ubah satu simpul MathML menjadi penanda yang dipakai aplikasi ini. */
    function dariMathML(simpul) {
        if (simpul.nodeType === 3) return lolos(simpul.textContent);
        if (simpul.nodeType !== 1) return '';

        const nama = simpul.localName.toLowerCase();
        const anak = [...simpul.childNodes].filter(n => n.nodeType === 1 || (n.nodeType === 3 && n.textContent.trim() !== ''));
        const isi = i => (anak[i] ? dariMathML(anak[i]) : '');
        const semua = () => anak.map(dariMathML).join('');

        switch (nama) {
            case 'math':
            case 'mrow':
            case 'mstyle':
            case 'mpadded':
                return semua();

            // <semantics> membungkus MathML bersama salinan LaTeX-nya;
            // hanya bagian pertama yang berupa rumus sebenarnya.
            case 'semantics':
                return isi(0);

            case 'annotation':
            case 'annotation-xml':
                return '';

            case 'msup':
                return isi(0) + '<sup>' + isi(1) + '</sup>';

            case 'msub':
                return isi(0) + '<sub>' + isi(1) + '</sub>';

            case 'msubsup':
                return isi(0) + '<sub>' + isi(1) + '</sub><sup>' + isi(2) + '</sup>';

            case 'mfrac':
                return '<span class="pecahan"><span class="pembilang">' + isi(0)
                     + '</span><span class="penyebut">' + isi(1) + '</span></span>';

            case 'msqrt':
                return '<span class="akar"><span class="radikan">' + semua() + '</span></span>';

            // Akar berderajat, mis. akar pangkat tiga: derajatnya ditulis
            // sebagai angka kecil di depan tanda akar.
            case 'mroot':
                return '<sup>' + isi(1) + '</sup><span class="akar"><span class="radikan">'
                     + isi(0) + '</span></span>';

            case 'mspace':
                return ' ';

            default:
                return anak.length ? semua() : lolos(simpul.textContent);
        }
    }
    // ------------------------------------------------------------------
    // Penyuntingan
    // ------------------------------------------------------------------

    function simpanRentang() {
        const sel = document.getSelection();
        if (!sel || sel.rangeCount === 0) return;

        const rentang = sel.getRangeAt(0);
        if (editorAktif && editorAktif.contains(rentang.commonAncestorContainer)) {
            rentangTersimpan = rentang.cloneRange();
        }
    }

    /**
     * Kembalikan kursor ke posisi terakhir di dalam editor.
     *
     * Diperlukan karena tombol papan sisip bisa merebut fokus — misalnya
     * tombol tab Bootstrap — dan tanpa pemulihan ini sisipan akan mendarat di
     * awal kolom, bukan di tempat kursor berada.
     */
    function pulihkanRentang() {
        if (!aktif()) return;

        const sel = document.getSelection();

        // Bila kursor sudah berada di dalam editor, biarkan apa adanya.
        // Memaksakan rentang tersimpan di keadaan ini justru melemparkan
        // kursor kembali ke posisi lama, sehingga sisipan berikutnya mendarat
        // di awal kolom dan saling bersarang.
        if (sel && sel.rangeCount && editorAktif.contains(sel.getRangeAt(0).commonAncestorContainer)) {
            editorAktif.focus();

            return;
        }

        editorAktif.focus();

        if (!rentangTersimpan || !editorAktif.contains(rentangTersimpan.commonAncestorContainer)) {
            return;
        }

        sel.removeAllRanges();
        sel.addRange(rentangTersimpan);
    }

    /**
     * Ubah tulisan LaTeX yang sedang ditandai menjadi rumus.
     *
     * Jalan cadangan untuk soal yang terlanjur tersimpan atau tertempel
     * sebagai kode mentah: guru menandai bagiannya, tekan tombolnya, selesai.
     * Tanpa ini satu-satunya cara adalah mengetik ulang seluruh rumus.
     */
    function ubahLatexTerpilih() {
        if (!aktif()) return false;

        pulihkanRentang();

        const sel = document.getSelection();
        if (!sel || !sel.rangeCount || sel.getRangeAt(0).collapsed) return false;

        const teks = sel.toString();
        if (!teks.trim()) return false;

        // Pembatas boleh ada, boleh tidak — guru sering menandai isinya saja.
        const bersih = teks.trim()
            .replace(/^\\\(|\\\)$/g, '')
            .replace(/^\\\[|\\\]$/g, '')
            .replace(/^\$\$|\$\$$/g, '')
            .replace(/^\$|\$$/g, '');

        const hasil = punyaLatex(teks) || /[\\^_]/.test(bersih)
            ? dariLatex(bersih)
            : konversiLatexDalamTeks(teks);

        document.execCommand('insertHTML', false, hasil);
        simpanRentang();
        sinkron(editorAktif);

        return true;
    }
    /** Tandai seluruh isi sebuah elemen agar ketikan berikutnya menggantinya. */
    function pilihIsi(el) {
        const rentang = document.createRange();
        rentang.selectNodeContents(el);

        const sel = document.getSelection();
        sel.removeAllRanges();
        sel.addRange(rentang);
        simpanRentang();
    }

    /**
     * Editor yang sedang disunting.
     *
     * Tidak cukup mengandalkan catatan dari kejadian focus: bila kursor sudah
     * berada di dalam editor sejak halaman dimuat — peramban kerap memulihkan
     * fokus sendiri — kejadian itu tidak pernah terpicu, dan papan sisip akan
     * menolak bekerja padahal guru jelas sedang berada di kolom tersebut.
     * Karena itu keadaan sebenarnya diperiksa ulang di sini.
     */
    function aktif() {
        // Keadaan sebenarnya didahulukan, catatan lama menyusul. Urutan ini
        // menentukan: bila catatan yang dimenangkan, berpindah dari kolom
        // pertanyaan ke kolom opsi tidak akan mengalihkan sasaran papan, dan
        // simbol yang ditekan sesudahnya mendarat di kolom yang salah.
        const dariFokus = document.activeElement?.closest?.('[data-editor-untuk]');
        if (dariFokus) return (editorAktif = dariFokus);

        const sel = document.getSelection();

        if (sel && sel.rangeCount) {
            let simpul = sel.getRangeAt(0).commonAncestorContainer;
            if (simpul.nodeType !== 1) simpul = simpul.parentNode;

            const dariKursor = simpul?.closest?.('[data-editor-untuk]');
            if (dariKursor) return (editorAktif = dariKursor);
        }

        // Catatan terakhir dipakai bila fokus sedang berada di luar editor —
        // misalnya sesaat setelah menekan tombol pada papan sisip.
        return editorAktif && document.contains(editorAktif) ? editorAktif : null;
    }

    /** Jadikan sebuah editor sasaran aktif — dipakai saat tempel & seret-lepas. */
    function fokuskan(editor) {
        if (!editor) return;

        editorAktif = editor;
        editor.focus();
        simpanRentang();
    }

    /**
     * Sisipan yang memuat susunan bertingkat tidak bisa dititipkan ke
     * insertHTML.
     *
     * Perintah bawaan peramban menata ulang sisipan yang memuat elemen
     * berisi bertumpuk. Bila kursor berada di tengah kalimat, kalimatnya
     * harus dibelah lebih dulu, dan pada saat itu Chrome membongkar
     * <span class="pecahan">: pembilangnya tetap terbungkus, penyebutnya
     * terlempar keluar menjadi <span> bergaya sebaris — "21 7" berdampingan,
     * bukan lagi pecahan. Pecahan yang disisipkan di ujung kalimat lolos dari
     * penataan itu; yang di tengah tidak. Itulah sebabnya kerusakannya jarang
     * tertangkap.
     *
     * Akar, pangkat, dan indeks isinya tetap sebaris, jadi tidak terkena.
     */
    const POLA_BERTINGKAT = /class\s*=\s*["'][^"']*\bpecahan\b/i;

    function sisipHtml(html) {
        if (!aktif()) return false;

        pulihkanRentang();

        if (POLA_BERTINGKAT.test(html)) {
            pasangSimpul(html);
        } else {
            document.execCommand('insertHTML', false, html);
        }

        tataGambar(editorAktif);
        simpanRentang();
        sinkron(editorAktif);

        return true;
    }

    /**
     * Pasang sepotong HTML tepat pada posisi kursor, apa adanya.
     *
     * Bagian yang sedang ditandai digantikan, sama seperti yang dilakukan
     * insertHTML — hanya saja tidak ada penataan ulang sesudahnya.
     */
    function pasangSimpul(html) {
        const sel = document.getSelection();

        if (!sel || !sel.rangeCount) return;

        const rentang = sel.getRangeAt(0);
        rentang.deleteContents();

        const potongan = rentang.createContextualFragment(html);
        const ujung = potongan.lastChild;

        rentang.insertNode(potongan);

        if (!ujung) return;

        // Kursor ditaruh sesudah sisipan, supaya ketikan berikutnya
        // melanjutkan kalimatnya dan bukan menimpa yang baru dipasang.
        const sesudah = document.createRange();
        sesudah.setStartAfter(ujung);
        sesudah.collapse(true);

        sel.removeAllRanges();
        sel.addRange(sesudah);
    }

    function sisipTeks(teks) {
        return sisipHtml(lolos(teks));
    }

    /**
     * Isi sebuah kolom beserta editornya sekaligus — dipakai template soal
     * yang menuangkan satu butir utuh ke formulir.
     *
     * Mengisi kolomnya saja tidak cukup: yang dilihat dan disunting guru
     * adalah editor di sebelahnya, dan isi editor itulah yang menimpa kolom
     * pada penyuntingan berikutnya. Tanpa baris kedua di bawah, isian yang
     * baru dituang akan lenyap begitu guru mengetik satu huruf.
     */
    function setel(kolom, nilai) {
        if (!kolom) return false;

        kolom.value = nilai ?? '';

        const editor = kolom.nextElementSibling;

        if (editor?.classList.contains('editor-kaya')) {
            editor.innerHTML = keHtml(kolom.value, kolom.tagName !== 'TEXTAREA');
            tataGambar(editor);
        }

        return true;
    }

    /** Perintah bawaan peramban: bold, italic, underline, superscript, subscript. */
    function perintah(nama) {
        if (!aktif()) return false;

        pulihkanRentang();

        const sel = document.getSelection();
        const kolaps = sel && sel.rangeCount && sel.getRangeAt(0).collapsed;
        const bertingkat = nama === 'superscript' || nama === 'subscript';

        // Menekan tombol pangkat untuk kedua kalinya berarti "sudahi
        // pangkatnya". Tanpa penanganan ini peramban justru menyarangkan
        // <sup> di dalam <sup>, sehingga kalimat yang diketik sesudahnya ikut
        // naik menjadi pangkat.
        //
        // Keadaannya diperiksa dengan menelusuri induk simpul, bukan dengan
        // queryCommandState: perintah itu melaporkan false meski kursor jelas
        // berada di dalam <sup>, sehingga tidak bisa dijadikan pegangan.
        if (kolaps && bertingkat) {
            const dalam = terdekat(nama === 'superscript' ? 'sup' : 'sub');

            if (dalam) {
                keluarDari(dalam);
                simpanRentang();

                return true;
            }
        }

        document.execCommand(nama, false, null);
        simpanRentang();
        sinkron(editorAktif);

        return true;
    }

    /**
     * Pindahkan kursor ke luar elemen terdekat bertag tertentu.
     *
     * Kursor ditambatkan pada satu spasi tanpa lebar yang disisipkan sesudah
     * elemen; tanpa jangkar itu peramban cenderung menarik ketikan berikutnya
     * kembali ke dalam elemen. Karakter itu dibuang lagi saat nilai disalin ke
     * kolom asli, jadi tidak ikut tersimpan.
     */
    function keluarDari(el) {
        if (!el || !el.parentNode) return;

        const jangkar = document.createTextNode('\u200B');
        el.parentNode.insertBefore(jangkar, el.nextSibling);

        const rentang = document.createRange();
        rentang.setStart(jangkar, 1);
        rentang.collapse(true);

        const sel = document.getSelection();
        sel.removeAllRanges();
        sel.addRange(rentang);
    }

    /** Elemen terdekat dari kursor yang cocok dengan pemilih, di dalam editor. */
    function terdekat(pemilih) {
        const sel = document.getSelection();
        if (!sel || !sel.rangeCount) return null;

        let simpul = sel.getRangeAt(0).startContainer;
        if (simpul.nodeType !== 1) simpul = simpul.parentNode;

        const el = simpul?.closest?.(pemilih);

        return el && editorAktif?.contains(el) ? el : null;
    }

    /**
     * Bungkus bagian yang ditandai.
     *
     * Bila tidak ada yang ditandai, dipakai bentuk kosong yang disediakan
     * tombolnya — dengan penanda kursor pada elemen yang mestinya diisi. Cara
     * lama, yakni menyelipkan <span> kosong sebagai penanda, tidak bisa
     * diandalkan: peramban membuang span tanpa isi, sehingga kursor tidak
     * pernah masuk ke dalam akar dan ketikan berikutnya mendarat di luarnya.
     */
    function bungkus(awal, akhir, kosong) {
        if (!aktif()) return false;

        pulihkanRentang();

        const sel = document.getSelection();
        let isi = '';

        if (sel && sel.rangeCount && !sel.getRangeAt(0).collapsed) {
            const wadah = document.createElement('div');
            wadah.appendChild(sel.getRangeAt(0).cloneContents());
            isi = wadah.innerHTML;
        }

        if (isi) return sisipDenganKursor(awal + isi + akhir, false);

        return sisipDenganKursor(kosong || (awal + '\u200B' + akhir), true);
    }

    /**
     * Sisipkan HTML lalu tempatkan kursor pada bagian yang ditandai
     * data-kursor, sehingga guru bisa langsung mengetik isinya.
     */
    function sisipDenganKursor(html, pindahkan) {
        const berhasil = sisipHtml(html);

        if (!berhasil || !pindahkan || !editorAktif) return berhasil;

        const titik = editorAktif.querySelector('[data-kursor]');
        if (!titik) return berhasil;

        titik.removeAttribute('data-kursor');

        const rentang = document.createRange();
        rentang.selectNodeContents(titik);

        const sel = document.getSelection();
        sel.removeAllRanges();
        sel.addRange(rentang);
        simpanRentang();

        return true;
    }

    // ------------------------------------------------------------------

    // Editor yang sedang disunting ditandai lewat focusin — kejadian ini
    // menggelembung, jadi satu pendengar cukup untuk seluruh kolom, termasuk
    // baris opsi yang ditambahkan belakangan.
    document.addEventListener('focusin', function (e) {
        if (e.target?.dataset?.editorUntuk) editorAktif = e.target;
    });

    // Rentang disimpan setiap kali kursor berpindah, termasuk perpindahan
    // akibat penyuntingan, bukan hanya akibat tekanan tuts atau klik.
    document.addEventListener('selectionchange', simpanRentang);

    document.addEventListener('DOMContentLoaded', function () {
        pasangSemua(document);

        // Nilai kolom tersembunyi disegarkan sekali lagi tepat sebelum kirim,
        // menjaring perubahan terakhir yang belum sempat memicu event input.
        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function () {
                form.querySelectorAll('.editor-kaya').forEach(ed => sinkron(ed));
            });
        });
    });

    return { pasang, pasangSemua, pasangUlang: pasangSemua, aktif, fokuskan,
             sisipHtml, sisipTeks, setel, bungkus, perintah, sisipDenganKursor,
             ubahLatexTerpilih, dariLatex, rumusTerdekat, pilihRumus, latexTerpilih };
})();
</script>
@endpush
