{{--
    Papan rumus.

    Papan sisip di sebelah hanya menyediakan karakter satuan: × ÷ √ π. Rumus
    yang sebenarnya ditulis guru matematika — pecahan bertingkat di dalam akar,
    pangkat berindeks, notasi ilmiah — tidak bisa dirakit dari karakter lepas
    seperti itu. Sejauh ini jalan satu-satunya adalah menekan tombol pecahan,
    mengetik pembilangnya, menekan Tab, mengetik penyebutnya, lalu menekan Tab
    sekali lagi untuk keluar — sambil berharap kursornya tidak terperangkap.

    Di sini rumusnya dirakit terpisah dari badan soal: ada ruang tersendiri
    untuk mencoba, menghapus, dan menata ulang. Ruang itu sendirilah yang
    diketik — bukan kolom kode yang hasilnya ditampilkan di tempat lain.
    Angka diketik langsung dari papan ketik, susunannya dipasang dari tombol,
    dan yang terlihat sepanjang penyusunan persis yang akan dibaca siswa.

    Bentuk simpanannya penanda yang sama seperti yang ditulis tangan lewat
    papan sisip — span.pecahan, span.akar, sup — bukan LaTeX, bukan gambar.
    Dengan begitu rumus dari papan ini tampil di lembar ujian siswa, naskah
    cetak, dan berkas analisis tanpa perlu apa pun yang baru di sisi penampil,
    dan masih bisa disunting tangan sesudahnya.

    LaTeX tetap dipakai, tetapi hanya di balik layar: tombol menyimpan
    bentuknya sebagai LaTeX lalu menguraikannya lewat EditorSoal.dariLatex
    sebelum dipasang, dan rumus yang terlanjur tertulis sebagai kode mentah
    dikenali lewat jalan yang sama saat ditempel atau ditandai.
--}}
@php
    use App\Support\RumusLatex;

    /*
     * Penampung bagian rumus yang belum diisi. Dipilih berupa huruf tampak —
     * bukan penanda tersembunyi — supaya guru melihat sendiri bagian mana yang
     * masih kosong, dan bisa langsung mengetik menimpanya.
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

    $pecahanKosong = '\frac{'.$kotak.'}{'.$kotak.'}';

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
        '@kotak'  => 'Lompat ke kotak berikutnya (juga lewat tuts Tab)',
    ];

    /*
     * Fungsi dan perakit yang lebih panjang. Tombolnya memuat penampung
     * sekaligus tanda kurungnya, sehingga yang tersisa bagi guru hanyalah
     * mengisi bagian yang kosong — bukan mengingat di mana kurungnya ditutup.
     */
    $fungsi = [
        'Fungsi' => [
            'sin('.$kotak.')', 'cos('.$kotak.')', 'tan('.$kotak.')',
            'log('.$kotak.')', 'log_{'.$kotak.'}('.$kotak.')', 'ln('.$kotak.')',
            'f('.$kotak.')', 'lim_{'.$kotak.'}', '%',
        ],
        'Pangkat, indeks & akar' => [
            $kotak.'^{'.$kotak.'}', $kotak.'^2', $kotak.'^3',
            $kotak.'_{'.$kotak.'}', $kotak.'_{'.$kotak.'}^{'.$kotak.'}',
            '\sqrt{'.$kotak.'}', '\sqrt[3]{'.$kotak.'}',
            '\sqrt['.$kotak.']{'.$kotak.'}',
        ],
        'Kalkulus & deret' => [
            '\sum_{'.$kotak.'}^{'.$kotak.'}', '\prod_{'.$kotak.'}^{'.$kotak.'}',
            '\int_{'.$kotak.'}^{'.$kotak.'}', '\int', '\partial', '\infty', '\Delta', '\nabla',
        ],
        'Kurung & nilai mutlak' => [
            '('.$kotak.')', '['.$kotak.']', '\{'.$kotak.'\}', '|'.$kotak.'|',
            '\left('.$kotak.'\right)^{'.$kotak.'}',
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
     * Perakit siap pakai, dikelompokkan menurut bab yang memakainya. Guru
     * matematika mencari rumusnya lewat babnya — trigonometri, logaritma,
     * kalkulus — bukan lewat bentuk susunannya.
     */
    $templat = [
        'Pecahan & akar' => [
            $pecahanKosong,
            $kotak.$pecahanKosong,
            $pecahanKosong.'\%',
            '\frac{\sqrt{'.$kotak.'}}{'.$kotak.'}',
            '\frac{'.$kotak.'^{'.$kotak.'}}{'.$kotak.'^{'.$kotak.'}}',
            '\sqrt{'.$kotak.'}',
            '\sqrt[3]{'.$kotak.'}',
            '\sqrt['.$kotak.']{'.$kotak.'}',
            '\sqrt{\frac{'.$kotak.'}{'.$kotak.'}}',
        ],
        'Pangkat & indeks' => [
            $kotak.'^{'.$kotak.'}',
            $kotak.'_{'.$kotak.'}',
            $kotak.'_{'.$kotak.'}^{'.$kotak.'}',
            $kotak.'^2',
            $kotak.'^3',
            $kotak.'^{-1}',
            '('.$kotak.')^{'.$kotak.'}',
            '\frac{'.$kotak.'^{'.$kotak.'}}{'.$kotak.'^{'.$kotak.'}} = '.$kotak.'^{'.$kotak.'}',
        ],

        /*
         * Trigonometri. Nama fungsinya ditulis sebagai huruf biasa, bukan
         * perintah \sin: keluaran papan ini huruf tampak, dan "sin" memang
         * sudah berupa tiga huruf yang benar.
         */
        'Trigonometri' => [
            'sin '.$kotak,
            'cos '.$kotak,
            'tan '.$kotak,
            'sin^{2} '.$kotak.' + cos^{2} '.$kotak.' = 1',
            'tan '.$kotak.' = \frac{sin '.$kotak.'}{cos '.$kotak.'}',
            'sin '.$kotak.'° = \frac{'.$kotak.'}{'.$kotak.'}',
            'sin('.$kotak.' ± '.$kotak.')',
            '\frac{'.$kotak.'}{sin '.$kotak.'} = \frac{'.$kotak.'}{sin '.$kotak.'}',
            $kotak.'^{2} = '.$kotak.'^{2} + '.$kotak.'^{2} − 2·'.$kotak.'·'.$kotak.'·cos '.$kotak,
        ],

        'Kurung' => [
            '('.$kotak.')',
            '['.$kotak.']',
            '\{'.$kotak.'\}',
            '|'.$kotak.'|',
            '\left(\frac{'.$kotak.'}{'.$kotak.'}\right)',
            '\left(\frac{'.$kotak.'}{'.$kotak.'}\right)^{'.$kotak.'}',
            '('.$kotak.' + '.$kotak.')('.$kotak.' − '.$kotak.')',
            '\left['.$kotak.' − ('.$kotak.' + '.$kotak.')\right]',
        ],

        /*
         * Logaritma. Buku sekolah di Indonesia menulis basisnya sebagai
         * pangkat di depan: ᵃlog b, bukan log_a b. Keduanya disediakan supaya
         * guru tidak perlu menyusun sendiri bentuk yang tidak dipakainya.
         */
        'Logaritma' => [
            '^{'.$kotak.'}log '.$kotak,
            '^{'.$kotak.'}log '.$kotak.' = '.$kotak,
            'log '.$kotak,
            'ln '.$kotak,
            'log_{'.$kotak.'} '.$kotak,
            'log ('.$kotak.' × '.$kotak.') = log '.$kotak.' + log '.$kotak,
            'log \frac{'.$kotak.'}{'.$kotak.'}',
            'log '.$kotak.'^{'.$kotak.'} = '.$kotak.' log '.$kotak,
        ],

        'Kalkulus' => [
            'lim_{'.$kotak.' \to '.$kotak.'} '.$kotak,
            '\frac{d'.$kotak.'}{d'.$kotak.'}',
            '\frac{d}{d'.$kotak.'}\left('.$kotak.'\right)',
            'f\'('.$kotak.')',
            '\int '.$kotak.' d'.$kotak,
            '\int_{'.$kotak.'}^{'.$kotak.'} '.$kotak.' d'.$kotak,
            '\sum_{'.$kotak.'}^{'.$kotak.'} '.$kotak,
            '\frac{\partial '.$kotak.'}{\partial '.$kotak.'}',
        ],

        'Notasi ilmiah & satuan' => [
            $kotak.'\times 10^{'.$kotak.'}',
            $kotak.'\%',
            $kotak.'°',
            $kotak.'°C',
            $kotak.'\text{ cm}^3',
            '\frac{'.$kotak.'\text{ km}}{\text{jam}}',
        ],
        'Perbandingan' => [
            $kotak.' : '.$kotak,
            $pecahanKosong.' = '.$pecahanKosong,
            $kotak.' \leq '.$kotak.' \leq '.$kotak,
            $kotak.' \neq '.$kotak,
            $kotak.' \approx '.$kotak,
        ],
    ];
@endphp

<style>
    /*
        Kolom rumusnya sendiri. Ia yang diketik, jadi diberi ruang paling
        lapang dan huruf paling besar di seluruh papan: rumus bertingkat dua
        seperti akar di dalam pecahan butuh tinggi lebih dari satu baris, dan
        guru harus bisa membaca pangkat kecilnya tanpa memicingkan mata.
    */
    #papanRumus .rumus-medan {
        min-height: 5.2rem;
        font-size: 1.6rem;
        line-height: 2.1;
        border: 1px solid #dee2e6;
        border-radius: .6rem;
        padding: .85rem 1rem;
        background: #fff;
        overflow-x: auto;
        cursor: text;
    }
    #papanRumus .rumus-medan:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 .25rem rgba(13, 110, 253, .25);
        outline: 0;
    }
    #papanRumus .rumus-medan:empty::before {
        content: attr(data-kosong);
        color: #adb5bd;
        font-size: .95rem;
        pointer-events: none;
    }
    /* Bagian yang kotaknya sudah terhapus tetap harus bisa diklik. */
    #papanRumus .rumus-medan .pembilang:empty::after,
    #papanRumus .rumus-medan .penyebut:empty::after,
    #papanRumus .rumus-medan .radikan:empty::after { content: '\00a0\00a0'; }

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

    /* ---------- Penyaring template ---------- */
    /*
        Dulu sebuah dropdown. Dengan delapan kelompok, memilih lewat dropdown
        berarti dua kali klik dan satu daftar yang menutupi rumusnya sendiri.
        Sebagai deretan pil, seluruh kelompok terbaca sekaligus dan berpindah
        bab cukup sekali tekan.
    */
    #papanRumus .rumus-saring { display: flex; flex-wrap: wrap; gap: .3rem; }
    #papanRumus .rumus-saring .btn {
        border-radius: 999px;
        padding: .2rem .7rem;
        font-size: .76rem;
        border-color: #e4e9f0;
        color: #5b6b83;
        background: #f7f9fc;
    }
    #papanRumus .rumus-saring .btn:hover { border-color: #b6c6dd; color: #223; }
    #papanRumus .rumus-saring .btn.aktif {
        background: #0d6efd;
        border-color: #0d6efd;
        color: #fff;
    }

    /* ---------- Tombol template ---------- */
    #papanRumus .rumus-templat {
        display: flex;
        flex-wrap: wrap;
        gap: .4rem;
        max-height: 11rem;
        overflow-y: auto;
        padding: .15rem;
    }
    #papanRumus .rumus-templat .btn {
        min-width: 3.8rem;
        min-height: 3.4rem;
        padding: .4rem .6rem;
        font-size: 1.05rem;
        color: #223;
        background: #fff;
        border-color: #e4e9f0;
        transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
    }
    #papanRumus .rumus-templat .btn:hover {
        border-color: #86b7fe;
        box-shadow: 0 2px 8px rgba(16, 24, 40, .1);
        transform: translateY(-1px);
    }

    #papanRumus .tab-pane { max-height: 15rem; overflow-y: auto; }
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
                {{-- Kolom rumusnya: diketik langsung, dan yang tampil di sini
                     persis yang akan dibaca siswa. --}}
                <div class="rumus-medan soal-body mb-3" id="rumusMedan"
                     contenteditable="true" spellcheck="false" role="textbox" aria-multiline="false"
                     aria-label="Rumus"
                     data-kosong="Ketik angkanya di sini, susunannya dipasang dari tombol di bawah."></div>

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
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="text-muted" style="font-size:.7rem">Template</span>
                    <div class="rumus-saring flex-grow-1">
                        <button type="button" class="btn btn-sm aktif" data-kategori="">Semua</button>
                        @foreach (array_keys($templat) as $nama)
                            <button type="button" class="btn btn-sm" data-kategori="{{ $nama }}">{{ $nama }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="rumus-templat">
                    @foreach ($templat as $nama => $daftar)
                        @foreach ($daftar as $isi)
                            <button type="button" class="btn btn-sm tuts-rumus"
                                    data-kategori="{{ $nama }}" data-rumus="{{ $isi }}"
                                    title="{{ $nama }}">{!! RumusLatex::urai($isi) !!}</button>
                        @endforeach
                    @endforeach
                </div>

                <div class="form-text mt-2">
                    Kotak <strong>{{ $kotak }}</strong> adalah bagian yang masih harus diisi — ketik
                    menimpanya, lalu tekan <kbd>Tab</kbd> untuk lompat ke kotak berikutnya.
                    Rumus disisipkan sebagai bagian dari teks soal — bukan gambar — jadi masih bisa
                    disunting langsung di kolomnya setelah disisipkan.
                    <strong>Klik ganda</strong> sebuah rumus di kolom soal untuk membukanya lagi di
                    papan ini; menandai sepotong tulisan lalu membuka papan ini juga bisa, dan yang
                    ditandai itulah yang diganti.
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

        const medan = document.getElementById('rumusMedan');
        const sasaran = document.getElementById('rumusSasaran');
        const pesan = document.getElementById('rumusPesan');
        const barisSendiri = document.getElementById('rumusBarisSendiri');
        const tombolSisip = document.getElementById('rumusSisipkan');

        // Sisipan ditunda sampai modal benar-benar tertutup. Bootstrap menahan
        // fokus selama modal terbuka: memanggil editor.focus() di saat itu
        // membuat fokusnya ditarik kembali ke modal pada detik yang sama, dan
        // rumusnya mendarat entah di mana.
        let tertunda = null;

        // Benar bila papan dibuka di atas sesuatu yang sudah ditandai di kolom
        // soal; sisipannya akan menggantikan tanda itu, bukan menambah.
        let menyunting = false;

        // Posisi kursor terakhir di dalam kolom rumus. Tombol papan menahan
        // fokus, tetapi tab Bootstrap dan pil penyaring sempat merebutnya.
        let rentangTersimpan = null;

        function keluhkan(teks) {
            pesan.textContent = teks;
            pesan.classList.remove('d-none');
            setTimeout(() => pesan.classList.add('d-none'), 3000);
        }

        // ------------------------------------------------------------------
        // Kursor
        // ------------------------------------------------------------------

        function simpanRentang() {
            const sel = document.getSelection();

            if (!sel || !sel.rangeCount) return;

            const rentang = sel.getRangeAt(0);

            if (medan.contains(rentang.commonAncestorContainer)) {
                rentangTersimpan = rentang.cloneRange();
            }
        }

        function pilih(rentang) {
            const sel = document.getSelection();

            medan.focus();
            sel.removeAllRanges();
            sel.addRange(rentang);
            simpanRentang();
        }

        function keUjung() {
            const rentang = document.createRange();
            rentang.selectNodeContents(medan);
            rentang.collapse(false);
            pilih(rentang);
        }

        /** Titik tempat sisipan berikutnya mendarat. */
        function titikSisip() {
            const sel = document.getSelection();

            if (sel && sel.rangeCount) {
                const rentang = sel.getRangeAt(0);

                if (medan.contains(rentang.commonAncestorContainer)) return rentang;
            }

            if (rentangTersimpan && medan.contains(rentangTersimpan.commonAncestorContainer)) {
                return rentangTersimpan;
            }

            const ujung = document.createRange();
            ujung.selectNodeContents(medan);
            ujung.collapse(false);

            return ujung;
        }

        // ------------------------------------------------------------------
        // Kotak kosong
        // ------------------------------------------------------------------

        /** Seluruh kotak kosong pada sepotong rumus, berurutan dari kiri. */
        function semuaKotak(akar) {
            const sumber = akar || medan;
            const daftar = [];

            function telusuri(simpul) {
                let dari = simpul.textContent.indexOf(KOTAK);

                while (dari !== -1) {
                    const rentang = document.createRange();
                    rentang.setStart(simpul, dari);
                    rentang.setEnd(simpul, dari + 1);
                    daftar.push(rentang);

                    dari = simpul.textContent.indexOf(KOTAK, dari + 1);
                }
            }

            // Simpul teks tidak dikunjungi oleh penelusurnya sendiri; yang
            // disisipkan kerap justru berupa teks lepas seperti "sin □".
            if (sumber.nodeType === 3) {
                telusuri(sumber);

                return daftar;
            }

            const jalan = document.createTreeWalker(sumber, NodeFilter.SHOW_TEXT);
            let simpul;

            while ((simpul = jalan.nextNode())) telusuri(simpul);

            return daftar;
        }

        /**
         * Tandai kotak kosong berikutnya.
         *
         * Bila semuanya sudah terisi, kursor dibawa ke ujung rumus. Itu yang
         * dibutuhkan tepat sesudah mengisi kotak terakhir: kursor masih berada
         * di dalam penyebut, dan tanda hitung yang ditekan sesudahnya akan ikut
         * masuk ke dalam penyebut itu.
         */
        function kotakBerikutnya() {
            const daftar = semuaKotak();

            if (daftar.length === 0) {
                keUjung();

                return;
            }

            const sel = document.getSelection();
            const acuan = sel && sel.rangeCount && medan.contains(sel.getRangeAt(0).commonAncestorContainer)
                ? sel.getRangeAt(0)
                : null;

            const berikut = acuan
                ? daftar.find(r => r.compareBoundaryPoints(Range.START_TO_START, acuan) > 0)
                : null;

            pilih(berikut || daftar[0]);
        }

        // ------------------------------------------------------------------
        // Penyuntingan
        // ------------------------------------------------------------------

        /**
         * Pasang sepotong penanda pada posisi kursor.
         *
         * Simpulnya dipasang sendiri lewat Range, bukan lewat insertHTML:
         * perintah bawaan peramban membongkar pecahan bila kursor berada di
         * tengah kalimat. Lihat penjelasan yang sama di editor-kaya.
         */
        function sisip(html) {
            const rentang = titikSisip();

            medan.focus();
            rentang.deleteContents();

            const potongan = rentang.createContextualFragment(html);
            const simpul = [...potongan.childNodes];

            rentang.insertNode(potongan);

            if (simpul.length === 0) return;

            // Kursor langsung ditaruh pada kotak kosong pertama milik sisipan
            // itu: menekan "pecahan" lalu mengetik angka harus mengisi
            // pembilangnya, bukan menambah di belakangnya.
            const kotak = simpul.flatMap(s => semuaKotak(s));

            if (kotak.length > 0) {
                pilih(kotak[0]);

                return;
            }

            const sesudah = document.createRange();
            sesudah.setStartAfter(simpul[simpul.length - 1]);
            sesudah.collapse(true);
            pilih(sesudah);
        }

        /** Pasang sebuah bentuk yang disimpan tombolnya sebagai LaTeX. */
        function sisipRumus(latex) {
            sisip(window.EditorSoal?.dariLatex(latex) ?? '');
        }

        function geser(arah) {
            medan.focus();
            document.getSelection()?.modify('move', arah, 'character');
            simpanRentang();
        }

        const aksi = {
            '@hapus': function () {
                medan.focus();
                document.execCommand('delete');
                simpanRentang();
            },
            '@bersih': function () {
                medan.innerHTML = '';
                rentangTersimpan = null;
                medan.focus();
            },
            '@kiri': () => geser('backward'),
            '@kanan': () => geser('forward'),
            '@kotak': kotakBerikutnya,
        };

        // Tombol papan tidak boleh merebut fokus dari kolom rumus: posisi
        // kursor di sanalah yang menentukan di mana potongan berikutnya
        // mendarat.
        papan.querySelectorAll('.tuts-rumus').forEach(function (tombol) {
            tombol.addEventListener('mousedown', e => e.preventDefault());
            tombol.addEventListener('click', function () {
                const nilai = tombol.dataset.rumus;

                (aksi[nilai] || (() => sisipRumus(nilai)))();
            });
        });

        papan.querySelectorAll('[data-bs-toggle="pill"]')
            .forEach(t => t.addEventListener('mousedown', e => e.preventDefault()));

        document.addEventListener('selectionchange', function () {
            if (papan.classList.contains('show')) simpanRentang();
        });

        medan.addEventListener('keydown', function (e) {
            if (e.key === 'Tab') {
                e.preventDefault();
                kotakBerikutnya();

                return;
            }

            // Papan ini hampir selalu dipakai di dalam formulir soal. Tanpa
            // penahanan ini, Enter akan mengirim formulirnya dan menyimpan soal
            // yang belum selesai ditulis.
            if (e.key === 'Enter') {
                e.preventDefault();
                sisipkan();
            }
        });

        // Tempelan diratakan menjadi tulisan biasa, lalu diurai: rumus yang
        // disalin dari laman web atau keluaran asisten AI kerap datang sebagai
        // kode LaTeX mentah, dan di sinilah ia dirapikan.
        medan.addEventListener('paste', function (e) {
            const teks = e.clipboardData?.getData('text/plain');

            if (!teks) return;

            e.preventDefault();
            sisip(window.EditorSoal?.dariLatex(teks) ?? '');
        });

        // ------------------------------------------------------------------
        // Penyaring template
        // ------------------------------------------------------------------

        papan.querySelectorAll('.rumus-saring .btn').forEach(function (pil) {
            pil.addEventListener('mousedown', e => e.preventDefault());
            pil.addEventListener('click', function () {
                const pilihan = pil.dataset.kategori;

                papan.querySelectorAll('.rumus-saring .btn')
                    .forEach(lain => lain.classList.toggle('aktif', lain === pil));

                papan.querySelectorAll('.rumus-templat .tuts-rumus').forEach(function (tombol) {
                    tombol.classList.toggle('d-none', pilihan !== '' && tombol.dataset.kategori !== pilihan);
                });

                papan.querySelector('.rumus-templat').scrollTop = 0;
            });
        });

        // ------------------------------------------------------------------
        // Sisipan ke kolom soal
        // ------------------------------------------------------------------

        /** Rumus siap pakai: kotak yang tidak sempat diisi dibuang. */
        function hasilAkhir() {
            const salinan = medan.cloneNode(true);
            const jalan = document.createTreeWalker(salinan, NodeFilter.SHOW_TEXT);
            const teks = [];
            let simpul;

            while ((simpul = jalan.nextNode())) teks.push(simpul);

            teks.forEach(function (t) {
                t.textContent = t.textContent.split(KOTAK).join('');
            });

            return { html: salinan.innerHTML.trim(), isi: salinan.textContent.trim() };
        }

        function sisipkan() {
            const hasil = hasilAkhir();

            if (hasil.isi === '' && !medan.querySelector('img')) {
                keluhkan('Rumusnya masih kosong.');

                return;
            }

            if (! window.EditorSoal?.aktif()) {
                keluhkan('Klik kolom teksnya lebih dulu.');

                return;
            }

            // Spasi penutup hanya dipasang pada rumus yang baru disisipkan:
            // tanpa jangkar itu kursor sulit keluar dari pecahan untuk
            // melanjutkan kalimatnya. Pada penggantian ia tidak diperlukan —
            // tulisan di sekitarnya sudah ada — dan menyuntingnya berulang
            // kali akan menumpuk spasi di belakang rumus.
            //
            // Barisnya dipisah dengan <br>, bukan dengan <div> atau <p>:
            // peramban menata ulang sisipan bertag blok ke dalam kolom
            // contenteditable dan kelasnya ikut terbuang.
            tertunda = barisSendiri.checked
                ? '<br>' + hasil.html + '<br>'
                : hasil.html + (menyunting ? '' : '&nbsp;');

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
             * Yang sedang ditandai di kolom soal dituangkan ke sini. Dua
             * keadaan bermuara ke jalan yang sama:
             *
             *   - rumus yang sudah tersisip, ditandai oleh klik ganda di atas,
             *     diuraikan balik lalu disusun kembali — bentuknya tetap sama;
             *   - rumus yang terlanjur tertulis sebagai kode mentah —
             *     tertempel dari laman web atau keluaran asisten AI — ditandai
             *     sendiri oleh guru, lalu dirapikan di sini.
             *
             * Keduanya berakhir dengan penggantian: sisipan menimpa bagian
             * yang ditandai, bukan menambah di sebelahnya.
             */
            const terpilih = window.EditorSoal?.latexTerpilih() ?? '';

            menyunting = terpilih !== '';

            if (menyunting) medan.innerHTML = window.EditorSoal.dariLatex(terpilih);

            tombolSisip.innerHTML = menyunting
                ? '<i class="bi bi-arrow-repeat me-1"></i>Ganti'
                : '<i class="bi bi-plus-lg me-1"></i>Sisipkan';
        });

        papan.addEventListener('shown.bs.modal', function () {
            rentangTersimpan = null;
            keUjung();
        });

        papan.addEventListener('hidden.bs.modal', function () {
            if (tertunda) {
                window.EditorSoal.sisipHtml(tertunda);
                tertunda = null;
                medan.innerHTML = '';
            } else if (menyunting) {
                // Batal menyunting. Rumus yang tertinggal milik bagian yang tadi
                // ditandai, bukan rancangan guru sendiri; dibiarkan, ia akan
                // tersisip untuk kedua kalinya saat papan dibuka lagi.
                medan.innerHTML = '';
            }

            menyunting = false;
            rentangTersimpan = null;
        });
    })();
</script>
@endpush
