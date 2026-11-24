{{--
    Papan rumus.

    Papan sisip di sebelah hanya menyediakan karakter satuan: × ÷ √ π. Rumus
    yang sebenarnya ditulis guru matematika — pecahan bertingkat di dalam akar,
    pangkat berindeks, notasi ilmiah — tidak bisa dirakit dari karakter lepas
    seperti itu. Sejauh ini jalan satu-satunya adalah menekan tombol pecahan,
    mengetik pembilangnya, menekan Tab, mengetik penyebutnya, lalu menekan Tab
    sekali lagi untuk keluar — sambil berharap kursornya tidak terperangkap.

    Di sini rumusnya dirakit terpisah dari badan soal: ada ruang tersendiri
    untuk mencoba, menghapus, dan menata ulang, dengan hasil jadinya terlihat
    sambil disusun. Baru setelah benar ia disisipkan ke kolom yang sedang
    disunting.

    Bentuk simpanannya tetap penanda yang sama seperti yang ditulis tangan
    lewat papan sisip — span.pecahan, span.akar, sup — bukan LaTeX, bukan
    gambar. Dengan begitu rumus dari papan ini tampil di lembar ujian siswa,
    naskah cetak, dan berkas analisis tanpa perlu apa pun yang baru di sisi
    penampil, dan masih bisa disunting tangan sesudahnya.

    LaTeX dipakai semata sebagai bahasa perantara saat merakit, karena itulah
    bentuk yang sudah dikenali App\Support\RumusLatex di sisi server dan
    EditorSoal.dariLatex di sisi peramban. Guru tidak harus menulisnya: kolom
    kodenya boleh diabaikan sama sekali dan seluruh rumus dirakit lewat tombol.
--}}
@php
    use App\Support\RumusLatex;

    /*
     * Penampung bagian rumus yang belum diisi. Dipilih berupa huruf tampak —
     * bukan penanda tersembunyi — supaya guru melihat sendiri bagian mana yang
     * masih kosong, baik pada kolom kode maupun pada pratinjaunya.
     */
    $kotak = '□';

    /*
     * Satu tuts papan: nilai yang disisipkan, label yang tampak, dan jenisnya
     * (menentukan warna). Label yang berupa rumus dirakit lewat RumusLatex
     * supaya tombolnya memperlihatkan bentuk jadinya, bukan kodenya.
     */
    $tuts = fn (string $nilai, ?string $label = null, string $jenis = 'hitung') => [
        $nilai,
        $label === null ? e($nilai) : RumusLatex::urai($label),
        $jenis,
    ];

    $angka = fn (string $n) => $tuts($n, null, 'angka');

    $pecahanKosong = '\frac{' . $kotak . '}{' . $kotak . '}';

    // Susunan tutsnya meniru kalkulator: angka di kiri, hitungan di tengah,
    // pengatur di kanan — tempat yang sudah dihafal tangan.
    $papanAngka = [
        [$angka('7'), $angka('8'), $angka('9'),
         $tuts('÷'), $tuts('×'), $tuts('('), $tuts(')'),
         $tuts('@hapus', null, 'atur'), $tuts('@bersih', null, 'atur')],

        [$angka('4'), $angka('5'), $angka('6'),
         $tuts('−'), $tuts('+'), $tuts('±'),
         $tuts('x', null, 'angka'), $tuts('y', null, 'angka'), $tuts('z', null, 'angka')],

        [$angka('1'), $angka('2'), $angka('3'),
         $tuts('<'), $tuts('>'), $tuts('≠'),
         $tuts($pecahanKosong, $pecahanKosong),
         $tuts('π'), $tuts('°')],

        [$angka('0'), $angka('.'), $angka(','),
         $tuts('='), $tuts('≤'), $tuts('≥'),
         $tuts('@kiri', null, 'atur'), $tuts('@kanan', null, 'atur'), $tuts('@kotak', null, 'atur')],
    ];

    // Label tombol pengatur ditulis terpisah: ikonnya bukan rumus.
    $labelAtur = [
        '@hapus'  => '<i class="bi bi-backspace"></i>',
        '@bersih' => 'C',
        '@kiri'   => '<i class="bi bi-arrow-left"></i>',
        '@kanan'  => '<i class="bi bi-arrow-right"></i>',
        '@kotak'  => '<i class="bi bi-box-arrow-in-right"></i>',
    ];

    $judulAtur = [
        '@hapus'  => 'Hapus satu huruf',
        '@bersih' => 'Kosongkan seluruhnya',
        '@kiri'   => 'Geser kursor ke kiri',
        '@kanan'  => 'Geser kursor ke kanan',
        '@kotak'  => 'Lompat ke kotak berikutnya, atau ke ujung rumus bila sudah terisi semua',
    ];

    /*
     * Fungsi dan perakit yang lebih panjang. Tombolnya memuat penampung
     * sekaligus tanda kurungnya, sehingga yang tersisa bagi guru hanyalah
     * mengisi bagian yang kosong — bukan mengingat di mana kurungnya ditutup.
     */
    $fungsi = [
        'Fungsi' => [
            'sin(' . $kotak . ')', 'cos(' . $kotak . ')', 'tan(' . $kotak . ')',
            'log(' . $kotak . ')', 'log_{' . $kotak . '}(' . $kotak . ')', 'ln(' . $kotak . ')',
            'f(' . $kotak . ')', 'lim_{' . $kotak . '}', '%',
        ],
        'Pangkat, indeks & akar' => [
            $kotak . '^{' . $kotak . '}', $kotak . '^2', $kotak . '^3',
            $kotak . '_{' . $kotak . '}', $kotak . '_{' . $kotak . '}^{' . $kotak . '}',
            '\sqrt{' . $kotak . '}', '\sqrt[3]{' . $kotak . '}',
            '\sqrt[' . $kotak . ']{' . $kotak . '}',
        ],
        'Kalkulus & deret' => [
            '\sum_{' . $kotak . '}^{' . $kotak . '}', '\prod_{' . $kotak . '}^{' . $kotak . '}',
            '\int_{' . $kotak . '}^{' . $kotak . '}', '\int', '\partial', '\infty', '\Delta', '\nabla',
        ],
        'Kurung & nilai mutlak' => [
            '(' . $kotak . ')', '[' . $kotak . ']', '\{' . $kotak . '\}', '|' . $kotak . '|',
            '\left(' . $kotak . '\right)^{' . $kotak . '}',
        ],
    ];

    /*
     * Lambang yang tidak punya tuts pada papan ketik mana pun. Daftarnya
     * sengaja sama dengan papan sisip di sebelah: guru yang sudah hafal letak
     * sebuah lambang di sana tidak perlu menghafalnya dua kali.
     */
    $lambang = [
        'Perbandingan & operasi' => ['≈', '≡', '∼', '≅', '≪', '≫', '∝', '·', '∗', '∓', '⇌', '≐'],
        'Himpunan & logika' => ['∈', '∉', '⊂', '⊄', '⊆', '⊇', '∪', '∩', '∅', '∀', '∃', '∴', '∵', '¬', '∧', '∨'],
        'Geometri' => ['∠', '⊾', '⊥', '∥', '△', '◯', '⌒', '′', '″'],
        'Panah' => ['→', '←', '↔', '⇒', '⇐', '⇔', '↑', '↓', '↦', '⟶'],
        'Yunani kecil' => ['α', 'β', 'γ', 'δ', 'ε', 'ζ', 'η', 'θ', 'λ', 'μ', 'ν', 'ξ', 'ρ', 'σ', 'τ', 'φ', 'χ', 'ψ', 'ω'],
        'Yunani besar' => ['Γ', 'Δ', 'Θ', 'Λ', 'Ξ', 'Π', 'Σ', 'Φ', 'Ψ', 'Ω'],
        'Pecahan siap pakai' => ['½', '⅓', '⅔', '¼', '¾', '⅕', '⅙', '⅛', '⅜', '⅝', '⅞'],
    ];

    /*
     * Perakit siap pakai, dikelompokkan supaya deretnya tidak menjadi satu
     * baris panjang yang harus ditelusuri dari ujung ke ujung.
     */
    $templat = [
        'Pecahan & akar' => [
            $pecahanKosong,
            $kotak . $pecahanKosong,
            $pecahanKosong . '\%',
            '\frac{\sqrt{' . $kotak . '}}{' . $kotak . '}',
            '\frac{' . $kotak . '^{' . $kotak . '}}{' . $kotak . '^{' . $kotak . '}}',
            '\sqrt{' . $kotak . '}',
            '\sqrt[3]{' . $kotak . '}',
            '\sqrt[' . $kotak . ']{' . $kotak . '}',
            '\sqrt{\frac{' . $kotak . '}{' . $kotak . '}}',
        ],
        'Pangkat & indeks' => [
            $kotak . '^{' . $kotak . '}',
            $kotak . '_{' . $kotak . '}',
            $kotak . '_{' . $kotak . '}^{' . $kotak . '}',
            $kotak . '^2',
            $kotak . '^3',
            $kotak . '^{-1}',
            '(' . $kotak . ')^{' . $kotak . '}',
        ],
        'Notasi ilmiah & satuan' => [
            $kotak . '\times 10^{' . $kotak . '}',
            $kotak . '\%',
            $kotak . '°',
            $kotak . '°C',
            $kotak . '\text{ cm}^3',
            '\frac{' . $kotak . '\text{ km}}{\text{jam}}',
        ],
        'Perbandingan' => [
            $kotak . ' : ' . $kotak,
            $pecahanKosong . ' = ' . $pecahanKosong,
            $kotak . ' \leq ' . $kotak . ' \leq ' . $kotak,
            $kotak . ' \neq ' . $kotak,
            $kotak . ' \approx ' . $kotak,
        ],
    ];
@endphp

<style>
    /* Hasil jadinya diberi ruang sendiri yang lapang — rumus bertingkat dua
       seperti akar di dalam pecahan butuh tinggi lebih dari satu baris. */
    #papanRumus .rumus-pratinjau {
        min-height: 4.5rem;
        font-size: 1.45rem;
        line-height: 2;
        border: 1px solid #dee2e6;
        border-radius: .5rem;
        padding: .75rem 1rem;
        background: #fff;
        overflow-x: auto;
    }
    #papanRumus .rumus-pratinjau:empty::before {
        content: attr(data-kosong);
        color: #adb5bd;
        font-size: .95rem;
    }

    /* Papan angka memakai sembilan lajur tetap supaya letak tiap tuts tidak
       berpindah saat tab diganti — tangan yang sudah hafal tidak perlu
       mencari ulang. Di layar sempit lajurnya dipersempit menjadi lima. */
    #papanRumus .rumus-papan {
        display: grid;
        grid-template-columns: repeat(9, minmax(0, 1fr));
        gap: .25rem;
    }
    @media (max-width: 575.98px) {
        #papanRumus .rumus-papan { grid-template-columns: repeat(5, minmax(0, 1fr)); }
    }
    #papanRumus .rumus-papan .btn { padding: .35rem .2rem; font-size: 1rem; line-height: 1.5; }

    #papanRumus .rumus-lepas { display: flex; flex-wrap: wrap; gap: .25rem; }
    #papanRumus .rumus-lepas .btn { min-width: 2.4rem; }

    #papanRumus .rumus-templat .btn { min-width: 3.4rem; min-height: 3rem; font-size: 1.05rem; }

    /* Kolom kode berlatar teduh: ia bagian teknis yang boleh diabaikan, bukan
       kolom utama yang harus diisi. */
    #papanRumus .rumus-kode {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: .85rem;
        background: #f8f9fa;
    }

    #papanRumus .tab-pane { max-height: 17rem; overflow-y: auto; }
</style>

<div class="modal fade" id="papanRumus" tabindex="-1" aria-labelledby="papanRumusJudul" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title d-flex align-items-center gap-2 mb-0" id="papanRumusJudul">
                    <i class="bi bi-calculator"></i>
                    <span>Rumus</span>
                    <span class="text-muted fw-normal small">ke</span>
                    <span class="badge text-bg-primary-subtle text-primary border border-primary-subtle"
                          id="rumusSasaran">kolom teks</span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body pt-2">
                {{-- Hasil jadinya, persis seperti yang akan dibaca siswa. --}}
                <div class="rumus-pratinjau soal-body mb-2" id="rumusPratinjau"
                     data-kosong="Susun rumusnya lewat tombol di bawah — hasilnya tampil di sini."></div>

                <div class="input-group input-group-sm mb-3">
                    <span class="input-group-text" title="Bentuk LaTeX dari rumus di atas">
                        <i class="bi bi-code-slash"></i>
                    </span>
                    <input type="text" class="form-control rumus-kode" id="rumusKode" spellcheck="false"
                           placeholder="boleh juga ditulis langsung, mis. \frac{1}{2} + \sqrt{9}"
                           aria-label="Kode rumus LaTeX">
                </div>

                <ul class="nav nav-pills nav-sm gap-1 mb-2" role="tablist">
                    @foreach ([
                        'angka' => '123',
                        'fungsi' => 'f(x)',
                        'lambang' => 'Simbol',
                    ] as $kunci => $label)
                        <li class="nav-item">
                            <button class="nav-link py-1 px-3 small {{ $loop->first ? 'active' : '' }}" type="button"
                                    data-bs-toggle="pill" data-bs-target="#rumus-tab-{{ $kunci }}">{{ $label }}</button>
                        </li>
                    @endforeach
                </ul>

                <div class="tab-content mb-3">
                    {{-- ---------------- Papan angka ---------------- --}}
                    <div class="tab-pane fade show active" id="rumus-tab-angka">
                        <div class="rumus-papan">
                            @foreach ($papanAngka as $baris)
                                @foreach ($baris as [$nilai, $label, $jenis])
                                    @php
                                        $warna = match ($jenis) {
                                            'angka' => 'btn-outline-dark',
                                            'atur' => 'btn-outline-secondary',
                                            default => 'btn-outline-primary',
                                        };
                                    @endphp
                                    <button type="button" class="btn btn-sm tuts-rumus {{ $warna }}"
                                            data-rumus="{{ $nilai }}"
                                            @isset($judulAtur[$nilai]) title="{{ $judulAtur[$nilai] }}" @endisset
                                    >{!! $labelAtur[$nilai] ?? $label !!}</button>
                                @endforeach
                            @endforeach
                        </div>
                    </div>

                    {{-- ---------------- Fungsi ---------------- --}}
                    <div class="tab-pane fade" id="rumus-tab-fungsi">
                        @foreach ($fungsi as $judul => $daftar)
                            <div class="mb-2">
                                <div class="text-muted mb-1" style="font-size:.7rem">{{ $judul }}</div>
                                <div class="rumus-lepas">
                                    @foreach ($daftar as $isi)
                                        <button type="button" class="btn btn-sm btn-outline-primary tuts-rumus"
                                                data-rumus="{{ $isi }}">{!! RumusLatex::urai($isi) !!}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- ---------------- Lambang ---------------- --}}
                    <div class="tab-pane fade" id="rumus-tab-lambang">
                        @foreach ($lambang as $judul => $daftar)
                            <div class="mb-2">
                                <div class="text-muted mb-1" style="font-size:.7rem">{{ $judul }}</div>
                                <div class="rumus-lepas">
                                    @foreach ($daftar as $isi)
                                        <button type="button" class="btn btn-sm btn-outline-secondary tuts-rumus"
                                                data-rumus="{{ $isi }}">{{ $isi }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- ---------------- Perakit siap pakai ---------------- --}}
                <label class="form-label small text-muted mb-1" for="rumusKategori">Template</label>
                <select class="form-select form-select-sm mb-2" id="rumusKategori">
                    <option value="">Semua template</option>
                    @foreach (array_keys($templat) as $nama)
                        <option value="{{ $nama }}">{{ $nama }}</option>
                    @endforeach
                </select>

                <div class="rumus-lepas rumus-templat">
                    @foreach ($templat as $nama => $daftar)
                        @foreach ($daftar as $isi)
                            <button type="button" class="btn btn-sm btn-outline-primary tuts-rumus"
                                    data-kategori="{{ $nama }}" data-rumus="{{ $isi }}"
                                    title="{{ $isi }}">{!! RumusLatex::urai($isi) !!}</button>
                        @endforeach
                    @endforeach
                </div>

                <div class="form-text mt-2">
                    Kotak <strong>{{ $kotak }}</strong> adalah bagian yang masih harus diisi; tekan
                    <i class="bi bi-box-arrow-in-right"></i> untuk melompat ke kotak berikutnya.
                    Rumus disisipkan sebagai bagian dari teks soal — bukan gambar — jadi masih
                    bisa disunting langsung di kolomnya setelah disisipkan.
                    <strong>Klik ganda</strong> sebuah rumus di kolom soal untuk membukanya lagi
                    di papan ini; menandai sepotong tulisan lalu membuka papan ini juga bisa,
                    dan yang ditandai itulah yang diganti.
                </div>
            </div>

            <div class="modal-footer py-2 d-flex justify-content-between">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="rumusBarisSendiri">
                    <label class="form-check-label small" for="rumusBarisSendiri">
                        Tampilkan di baris sendiri
                    </label>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <span class="small text-danger d-none" id="rumusPesan"></span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    {{-- Tulisannya berganti menjadi "Ganti" bila papan ini
                         dibuka di atas rumus yang sudah tersisip. --}}
                    <button type="button" class="btn btn-sm btn-primary" id="rumusSisipkan">
                        <i class="bi bi-plus-lg me-1"></i>Sisipkan
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        'use strict';

        const papan = document.getElementById('papanRumus');
        if (!papan) return;

        const KOTAK = @json($kotak);

        const kode = document.getElementById('rumusKode');
        const pratinjau = document.getElementById('rumusPratinjau');
        const sasaran = document.getElementById('rumusSasaran');
        const pesan = document.getElementById('rumusPesan');
        const barisSendiri = document.getElementById('rumusBarisSendiri');
        const kategori = document.getElementById('rumusKategori');
        const tombolSisip = document.getElementById('rumusSisipkan');

        // Sisipan ditunda sampai modal benar-benar tertutup. Bootstrap menahan
        // fokus selama modal terbuka: memanggil editor.focus() di saat itu
        // membuat fokusnya ditarik kembali ke modal pada detik yang sama, dan
        // rumusnya mendarat entah di mana.
        let tertunda = null;

        // Benar bila papan dibuka di atas sesuatu yang sudah ditandai di kolom
        // soal; sisipannya akan menggantikan tanda itu, bukan menambah.
        let menyunting = false;

        function keluhkan(teks) {
            pesan.textContent = teks;
            pesan.classList.remove('d-none');
            setTimeout(() => pesan.classList.add('d-none'), 3000);
        }

        /** Perbarui hasil jadinya setiap kali kodenya berubah. */
        function segarkan() {
            const isi = kode.value.trim();

            pratinjau.innerHTML = isi === '' ? '' : (window.EditorSoal?.dariLatex(isi) ?? '');
        }

        /**
         * Sisipkan potongan pada posisi kursor kolom kode.
         *
         * Setelah disisipkan kursornya langsung ditaruh pada kotak kosong
         * pertama milik potongan itu — menekan "pecahan" lalu mengetik angka
         * harus langsung mengisi pembilangnya, bukan menambah di belakangnya.
         */
        function sisip(potongan) {
            const awal = kode.selectionStart ?? kode.value.length;
            const akhir = kode.selectionEnd ?? awal;

            kode.value = kode.value.slice(0, awal) + potongan + kode.value.slice(akhir);

            const sesudah = awal + potongan.length;
            const kotak = kode.value.indexOf(KOTAK, awal);

            kode.focus();

            if (kotak !== -1 && kotak < sesudah) {
                kode.setSelectionRange(kotak, kotak + 1);
            } else {
                kode.setSelectionRange(sesudah, sesudah);
            }

            segarkan();
        }

        /**
         * Tandai kotak kosong berikutnya.
         *
         * Bila semuanya sudah terisi, kursor dibawa ke ujung rumus. Itu yang
         * dibutuhkan tepat sesudah mengisi kotak terakhir: kursor masih berada
         * di dalam kurung kurawal milik penyebut, dan tanda hitung yang
         * ditekan sesudahnya akan ikut masuk ke dalam penyebut — "3/4+" alih-
         * alih "3/4 +".
         */
        function kotakBerikutnya() {
            const dari = kode.selectionEnd ?? 0;
            const titik = kode.value.indexOf(KOTAK, dari);
            const tujuan = titik === -1 ? kode.value.indexOf(KOTAK) : titik;

            kode.focus();

            if (tujuan === -1) {
                kode.setSelectionRange(kode.value.length, kode.value.length);

                return;
            }

            kode.setSelectionRange(tujuan, tujuan + 1);
        }

        function geser(arah) {
            const posisi = (kode.selectionStart ?? 0) + arah;
            const aman = Math.max(0, Math.min(kode.value.length, posisi));

            kode.focus();
            kode.setSelectionRange(aman, aman);
        }

        /** Hapus satu huruf di kiri kursor, atau bagian yang sedang ditandai. */
        function hapus() {
            const awal = kode.selectionStart ?? 0;
            const akhir = kode.selectionEnd ?? awal;

            kode.focus();

            if (awal !== akhir) {
                kode.value = kode.value.slice(0, awal) + kode.value.slice(akhir);
                kode.setSelectionRange(awal, awal);
            } else if (awal > 0) {
                kode.value = kode.value.slice(0, awal - 1) + kode.value.slice(awal);
                kode.setSelectionRange(awal - 1, awal - 1);
            }

            segarkan();
        }

        const aksi = {
            '@hapus': hapus,
            '@bersih': function () {
                kode.value = '';
                kode.focus();
                segarkan();
            },
            '@kiri': () => geser(-1),
            '@kanan': () => geser(1),
            '@kotak': kotakBerikutnya,
        };

        // Tombol papan tidak boleh merebut fokus dari kolom kode: posisi
        // kursor di sanalah yang menentukan di mana potongan berikutnya
        // mendarat.
        papan.querySelectorAll('.tuts-rumus').forEach(function (tombol) {
            tombol.addEventListener('mousedown', e => e.preventDefault());
            tombol.addEventListener('click', function () {
                const nilai = tombol.dataset.rumus;

                (aksi[nilai] || (() => sisip(nilai)))();
            });
        });

        papan.querySelectorAll('[data-bs-toggle="pill"]')
            .forEach(t => t.addEventListener('mousedown', e => e.preventDefault()));

        kode.addEventListener('input', segarkan);

        // Papan ini hampir selalu dipakai di dalam formulir soal. Tanpa
        // penahanan ini, Enter di kolom kode akan mengirim formulirnya dan
        // menyimpan soal yang belum selesai ditulis.
        kode.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;

            e.preventDefault();
            sisipkan();
        });

        kategori.addEventListener('change', function () {
            const pilihan = this.value;

            papan.querySelectorAll('.rumus-templat .tuts-rumus').forEach(function (tombol) {
                tombol.classList.toggle('d-none', pilihan !== '' && tombol.dataset.kategori !== pilihan);
            });
        });

        // ------------------------------------------------------------------
        // Sisipan ke kolom soal
        // ------------------------------------------------------------------

        function sisipkan() {
            const isi = kode.value.trim();

            if (isi === '') {
                keluhkan('Rumusnya masih kosong.');

                return;
            }

            if (! window.EditorSoal?.aktif()) {
                keluhkan('Klik kolom teksnya lebih dulu.');

                return;
            }

            // Kotak yang tidak sempat diisi dibuang, bukan ikut tersimpan:
            // kotak kosong di badan soal tidak berarti apa pun bagi siswa.
            const html = window.EditorSoal.dariLatex(isi.split(KOTAK).join(''));

            // Barisnya dipisah dengan <br>, bukan dengan <div> atau <p>.
            // Peramban menata ulang sisipan bertag blok ke dalam kolom
            // contenteditable: kelasnya dibuang dan diganti gaya sebaris hasil
            // perhitungannya sendiri, sehingga <span class="akar"> berubah
            // menjadi <span style="white-space:nowrap"> — akarnya lenyap,
            // tinggal angkanya. Yang tersisip sebaris tidak mengalami itu.
            // Spasi penutup hanya dipasang pada rumus yang baru disisipkan:
            // tanpa jangkar itu kursor sulit keluar dari pecahan untuk
            // melanjutkan kalimatnya. Pada penggantian ia tidak diperlukan —
            // tulisan di sekitarnya sudah ada — dan menyuntingnya berulang
            // kali akan menumpuk spasi di belakang rumus.
            tertunda = barisSendiri.checked
                ? '<br>' + html + '<br>'
                : html + (menyunting ? '' : '&nbsp;');

            bootstrap.Modal.getOrCreateInstance(papan).hide();
        }

        tombolSisip.addEventListener('click', sisipkan);

        /*
         * Klik ganda di atas rumus yang sudah tersisip membukanya lagi di sini.
         *
         * Tanpa jalan ini, memperbaiki satu angka pada penyebut berarti
         * membongkar susunannya di dalam kolom — menaruh kursor di tempat yang
         * tepat di antara pembilang dan penyebut, tanpa terpeleset keluar dari
         * pecahannya. Dipasang di tingkat dokumen supaya kolom opsi yang
         * ditambahkan belakangan ikut terlayani.
         */
        document.addEventListener('dblclick', function (e) {
            const rumus = window.EditorSoal?.rumusTerdekat(e.target);

            if (!rumus) return;

            e.preventDefault();
            window.EditorSoal.pilihRumus(rumus);
            bootstrap.Modal.getOrCreateInstance(papan).show();
        });

        papan.addEventListener('show.bs.modal', function () {
            const editor = window.EditorSoal?.aktif();

            sasaran.textContent = editor
                ? (editor.dataset.labelSisip || 'kolom teks')
                : 'belum ada kolom dipilih';

            // Kolom opsi jawaban dan pasangan penjodohan tingginya satu baris;
            // memaksakan rumus ke baris sendiri di sana hanya akan merenggangkan
            // deretan opsinya.
            const satuBaris = editor?.getAttribute('aria-multiline') === 'false';

            barisSendiri.disabled = satuBaris;
            barisSendiri.title = satuBaris ? 'Kolom ini hanya setinggi satu baris.' : '';

            if (satuBaris) barisSendiri.checked = false;

            /*
             * Yang sedang ditandai di kolom soal dituangkan ke sini sebagai
             * kode. Dua keadaan bermuara ke jalan yang sama:
             *
             *   - rumus yang sudah tersisip, ditandai oleh klik ganda di atas,
             *     diuraikan balik menjadi LaTeX-nya;
             *   - rumus yang terlanjur tertulis sebagai kode mentah —
             *     tertempel dari laman web atau keluaran asisten AI — ditandai
             *     sendiri oleh guru, lalu dirapikan di sini.
             *
             * Keduanya berakhir dengan penggantian: sisipan menimpa bagian
             * yang ditandai, bukan menambah di sebelahnya.
             */
            const terpilih = window.EditorSoal?.latexTerpilih() ?? '';

            menyunting = terpilih !== '';

            if (menyunting) kode.value = terpilih;

            tombolSisip.innerHTML = menyunting
                ? '<i class="bi bi-arrow-repeat me-1"></i>Ganti'
                : '<i class="bi bi-plus-lg me-1"></i>Sisipkan';

            segarkan();
        });

        papan.addEventListener('shown.bs.modal', function () {
            kode.focus();
            kode.setSelectionRange(kode.value.length, kode.value.length);
        });

        papan.addEventListener('hidden.bs.modal', function () {
            if (tertunda) {
                window.EditorSoal.sisipHtml(tertunda);
                tertunda = null;
                kode.value = '';
            } else if (menyunting) {
                // Batal menyunting. Kode yang tertinggal milik rumus yang tadi
                // ditandai, bukan rancangan guru sendiri; dibiarkan, ia akan
                // tersisip untuk kedua kalinya saat papan dibuka lagi.
                kode.value = '';
            }

            menyunting = false;
            segarkan();
        });
    })();
</script>
@endpush
