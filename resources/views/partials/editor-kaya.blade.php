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
    const KELAS_BOLEH = ['teks-arab','pecahan','pembilang','penyebut','akar','radikan'];

    let editorAktif = null;
    let rentangTersimpan = null;

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
            document.execCommand('insertHTML', false, html ? saring(html, ! luas) : konversiLatexDalamTeks(teks));
            sinkron(editor, kolom);
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

        tujuan.value = isi;
        editor.classList.toggle('is-invalid', false);
    }

    /** Nilai tersimpan bisa berupa teks polos maupun HTML; keduanya diterima. */
    function keHtml(nilai, satuBaris) {
        const teks = (nilai || '').toString();

        if (teks === '') return '';
        if (/<[a-z][\s\S]*>/i.test(teks)) return saring(teks, satuBaris);

        return lolos(teks).replace(/\n/g, '<br>');
    }

    /**
     * Ratakan isi menjadi satu baris.
     *
     * Kolom opsi jawaban dan pasangan penjodohan tingginya satu baris; sebuah
     * tempelan beralinea dari Word akan merenggangkan barisnya dan merusak
     * susunan daftar opsi. Tag bloknya dibuang, isinya tetap.
     */
    function ratakan(wadah) {
        wadah.querySelectorAll('p, div, li, ul, ol, table, thead, tbody, tr, td, th, br')
            .forEach(function (el) {
                if (el.tagName === 'BR') {
                    el.replaceWith(document.createTextNode(' '));

                    return;
                }

                el.replaceWith(...el.childNodes, document.createTextNode(' '));
            });
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

    function sisipHtml(html) {
        if (!aktif()) return false;

        pulihkanRentang();
        document.execCommand('insertHTML', false, html);
        simpanRentang();
        sinkron(editorAktif);

        return true;
    }

    function sisipTeks(teks) {
        return sisipHtml(lolos(teks));
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
             sisipHtml, sisipTeks, bungkus, perintah, sisipDenganKursor,
             ubahLatexTerpilih };
})();
</script>
@endpush
