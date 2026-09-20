{{--
    Papan sisip karakter untuk penulisan soal.

    Guru tidak punya tuts × ÷ ≤ √ π apalagi huruf hijaiyah pada papan ketik,
    jadi karakternya disediakan sebagai tombol. Satu papan dipakai bersama oleh
    seluruh kolom teks pada formulir — kolom pertanyaan, tiap opsi jawaban,
    pasangan penjodohan, kunci essay, dan pembahasan — dengan menyisipkan ke
    kolom yang terakhir disentuh. Menempelkan papan terpisah di tiap kolom akan
    membuat formulir penuh sesak.
--}}
<div class="card mb-3" id="papanSimbol">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-keyboard me-1"></i>Sisipkan Simbol &amp; Huruf Arab</span>
        <span class="small text-muted" id="sasaranSisip">klik kolom teks lebih dulu</span>
    </div>

    <div class="card-body py-2">
        <ul class="nav nav-pills nav-sm gap-1 mb-2" role="tablist">
            @foreach ([
                'matematika' => ['Simbol', 'bi-calculator'],
                'format' => ['Rumus & Format', 'bi-superscript'],
                'yunani' => ['Yunani', 'bi-alphabet'],
                'arab' => ['Hijaiyah', 'bi-translate'],
            ] as $kunci => [$label, $ikon])
                <li class="nav-item">
                    <button class="nav-link py-1 px-2 small {{ $loop->first ? 'active' : '' }}" type="button"
                            data-bs-toggle="pill" data-bs-target="#tab-{{ $kunci }}">
                        <i class="bi {{ $ikon }} me-1"></i>{{ $label }}
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="tab-content">
            {{-- ---------------- Matematika ---------------- --}}
            <div class="tab-pane fade show active" id="tab-matematika">
                @foreach ([
                    'Operasi' => ['+', '−', '×', '÷', '±', '∓', '·', '∗', '≠', '=', '≈', '≡'],
                    'Perbandingan' => ['<', '>', '≤', '≥', '≪', '≫', '∝', '≅', '∼'],
                    'Himpunan & logika' => ['∈', '∉', '⊂', '⊄', '⊆', '⊇', '∪', '∩', '∅', '∀', '∃', '∴', '∵', '¬', '∧', '∨'],
                    'Pangkat & indeks' => ['⁰', '¹', '²', '³', '⁴', '⁵', '⁶', '⁷', '⁸', '⁹', '₀', '₁', '₂', '₃', '₄', '₅', '₆', '₇', '₈', '₉'],
                    'Akar & kalkulus' => ['√', '∛', '∜', '∑', '∏', '∫', '∬', '∮', '∂', '∆', '∇', '∞', '≐', '⋅'],
                    'Geometri' => ['°', '′', '″', '∠', '⊾', '⊥', '∥', '△', '□', '◯', '⌒', 'π', '⇌'],
                    'Pecahan siap pakai' => ['½', '⅓', '⅔', '¼', '¾', '⅕', '⅖', '⅗', '⅘', '⅙', '⅛', '⅜', '⅝', '⅞'],
                    'Panah' => ['→', '←', '↔', '⇒', '⇐', '⇔', '↑', '↓', '↦', '⟶'],
                    'Mata uang & lain-lain' => ['Rp', '‰', '%', '№', '✓', '✗', '…', '·'],
                ] as $judul => $daftar)
                    <div class="mb-2">
                        <div class="text-muted mb-1" style="font-size:.7rem">{{ $judul }}</div>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach ($daftar as $simbol)
                                <button type="button" class="btn btn-sm btn-outline-secondary tombol-sisip"
                                        data-sisip="{{ $simbol }}">{{ $simbol }}</button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- ---------------- Huruf Yunani ---------------- --}}
            <div class="tab-pane fade" id="tab-yunani">
                @foreach ([
                    'Huruf kecil' => ['α', 'β', 'γ', 'δ', 'ε', 'ζ', 'η', 'θ', 'ι', 'κ', 'λ', 'μ', 'ν', 'ξ', 'ο', 'π', 'ρ', 'σ', 'τ', 'υ', 'φ', 'χ', 'ψ', 'ω'],
                    'Huruf besar' => ['Γ', 'Δ', 'Θ', 'Λ', 'Ξ', 'Π', 'Σ', 'Φ', 'Ψ', 'Ω'],
                ] as $judul => $daftar)
                    <div class="mb-2">
                        <div class="text-muted mb-1" style="font-size:.7rem">{{ $judul }}</div>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach ($daftar as $simbol)
                                <button type="button" class="btn btn-sm btn-outline-secondary tombol-sisip"
                                        data-sisip="{{ $simbol }}">{{ $simbol }}</button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- ---------------- Huruf hijaiyah ---------------- --}}
            <div class="tab-pane fade" id="tab-arab">
                <div class="alert alert-info py-2 small d-flex gap-2 mb-2">
                    <i class="bi bi-info-circle mt-1"></i>
                    <div>
                        Tekan <strong>Blok teks Arab</strong> lebih dulu bila soal berbahasa Indonesia namun
                        memuat kutipan Arab — tanda baca dan angka di sekitarnya akan tetap pada tempat
                        yang benar. Ketikan Arab juga bisa langsung diketik dari papan ketik Arab
                        bawaan Windows bila sudah dipasang.
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-1 mb-2">
                    <button type="button" class="btn btn-sm btn-primary tombol-bungkus"
                            data-awal='<span class="teks-arab" lang="ar" dir="rtl">' data-akhir='</span>&nbsp;'
                            data-kosong='<span class="teks-arab" lang="ar" dir="rtl" data-kursor="1">&#8203;</span>&nbsp;'>
                        <i class="bi bi-box-arrow-in-right me-1"></i>Blok teks Arab
                    </button>
                </div>

                @foreach ([
                    'Hijaiyah' => ['ا', 'ب', 'ت', 'ث', 'ج', 'ح', 'خ', 'د', 'ذ', 'ر', 'ز', 'س', 'ش', 'ص', 'ض', 'ط', 'ظ', 'ع', 'غ', 'ف', 'ق', 'ك', 'ل', 'م', 'ن', 'ه', 'و', 'ي'],
                    'Bentuk lain' => ['أ', 'إ', 'آ', 'ء', 'ؤ', 'ئ', 'ة', 'ى', 'لا', 'ﻻ'],
                    'Harakat' => ['َ', 'ِ', 'ُ', 'ً', 'ٍ', 'ٌ', 'ْ', 'ّ', 'ٰ', 'ٓ'],
                    'Angka Arab' => ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
                    'Tanda baca' => ['،', '؛', '؟', '۔', '﴾', '﴿', 'ـ'],
                    'Lafaz' => ['ﷲ', 'ﷺ', 'ﷻ', '﷽', 'ﷴ'],
                ] as $judul => $daftar)
                    <div class="mb-2">
                        <div class="text-muted mb-1" style="font-size:.7rem">{{ $judul }}</div>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach ($daftar as $huruf)
                                <button type="button" class="btn btn-sm btn-outline-secondary tombol-sisip teks-arab py-0"
                                        style="min-width:2.3rem" data-sisip="{{ $huruf }}">{{ $huruf }}</button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- ---------------- Format ---------------- --}}
            <div class="tab-pane fade" id="tab-format">
                <div class="alert alert-light border py-2 small d-flex gap-2 mb-2">
                    <i class="bi bi-lightbulb mt-1"></i>
                    <div>
                        Hasilnya langsung terlihat di kolom pertanyaan, sama seperti yang dilihat siswa.
                        Menandai teks lebih dulu akan membungkus teks itu; tanpa menandai, kursor
                        diletakkan di tempat yang perlu diisi. Pada <strong>pangkat</strong>, tekan
                        tombolnya sekali lagi untuk kembali menulis normal. Pada
                        <strong>pecahan</strong> dan <strong>akar</strong>, tekan
                        <kbd>Tab</kbd> untuk berpindah ke bagian berikutnya lalu keluar.
                        Rumus LaTeX seperti <code>\frac{1}{2}</code> yang terlanjur tertempel
                        sebagai tulisan bisa ditandai lalu diubah lewat tombol
                        <strong>Ubah LaTeX terpilih</strong>.
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-1 mb-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary tombol-perintah"
                            data-perintah="bold"><i class="bi bi-type-bold"></i> Tebal</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary tombol-perintah"
                            data-perintah="italic"><i class="bi bi-type-italic"></i> Miring</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary tombol-perintah"
                            data-perintah="underline"><i class="bi bi-type-underline"></i> Garis bawah</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary tombol-perintah"
                            data-perintah="superscript">x<sup>2</sup> Pangkat</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary tombol-perintah"
                            data-perintah="subscript">x<sub>2</sub> Indeks</button>
                </div>

                <div class="text-muted mb-1" style="font-size:.7rem">Gambar</div>
                <div class="d-flex flex-wrap gap-1 align-items-center mb-3">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="tombolGambar">
                        <i class="bi bi-image me-1"></i>Sisipkan gambar
                    </button>
                    <input type="file" id="berkasGambar" accept="image/png,image/jpeg,image/gif,image/webp" hidden>
                    <span class="text-muted" style="font-size:.7rem">
                        atau tempel langsung (Ctrl+V) / seret berkas ke kolom pertanyaan
                    </span>
                </div>

                <div class="text-muted mb-1" style="font-size:.7rem">Susunan bertingkat</div>
                <div class="d-flex flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-outline-primary tombol-sisip-html tombol-kursor"
                            data-sisip-html='<span class="pecahan"><span class="pembilang" data-kursor="1">1</span><span class="penyebut">2</span></span>&nbsp;'>
                        <i class="bi bi-slash-lg me-1"></i>Pecahan bertingkat
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary tombol-bungkus"
                            data-awal='<span class="akar"><span class="radikan">' data-akhir='</span></span>&nbsp;'
                            data-kosong='<span class="akar"><span class="radikan" data-kursor="1">&#8203;</span></span>&nbsp;'>
                        √<span style="text-decoration:overline">x</span> Akar
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success" id="tombolLatex"
                            title="Tandai tulisan LaTeX seperti \frac{1}{2} lalu tekan tombol ini">
                        <i class="bi bi-magic me-1"></i>Ubah LaTeX terpilih
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary tombol-sisip-html"
                            data-sisip-html="<br>">
                        <i class="bi bi-arrow-return-left me-1"></i>Baris baru
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Papan sisip menulis ke editor yang terakhir disentuh pengguna. Tombolnya
    // memakai mousedown + preventDefault supaya fokus tidak berpindah dari
    // editor — dengan begitu posisi kursor dan teks yang ditandai tetap utuh
    // saat tombol ditekan.
    (function () {
        const label = document.getElementById('sasaranSisip');

        // Nama kolom yang sedang aktif, untuk penunjuk di kepala papan.
        document.addEventListener('focusin', function (e) {
            const el = e.target;
            if (!el.dataset?.editorUntuk) return;

            label.textContent = 'menyisipkan ke: ' + (el.dataset.labelSisip || 'kolom teks');
            label.classList.remove('text-muted', 'text-danger');
            label.classList.add('text-primary');
        });

        function ingatkan() {
            label.textContent = 'klik kolom teks lebih dulu';
            label.classList.add('text-danger');
            setTimeout(() => label.classList.remove('text-danger'), 1500);
        }

        /** Jalankan aksi papan; peringatkan bila belum ada kolom yang dipilih. */
        function jalankan(aksi) {
            if (!window.EditorSoal?.aktif()) {
                ingatkan();

                return false;
            }

            return aksi();
        }

        function sisipkan(html) {
            return jalankan(() => window.EditorSoal.sisipHtml(html));
        }

        // Tombol simbol menyisipkan karakter biasa — termasuk < dan > yang
        // harus dilewatkan sebagai teks, bukan sebagai awal tag.
        document.querySelectorAll('#papanSimbol .tombol-sisip').forEach(function (tombol) {
            tombol.addEventListener('mousedown', e => e.preventDefault());
            tombol.addEventListener('click', function () {
                jalankan(() => window.EditorSoal.sisipTeks(tombol.dataset.sisip));
            });
        });

        // Tombol yang menyisipkan penanda siap pakai (pecahan, baris baru).
        document.querySelectorAll('#papanSimbol .tombol-sisip-html').forEach(function (tombol) {
            tombol.addEventListener('mousedown', e => e.preventDefault());
            tombol.addEventListener('click', function () {
                jalankan(() => window.EditorSoal.sisipDenganKursor(
                    tombol.dataset.sisipHtml,
                    tombol.classList.contains('tombol-kursor')
                ));
            });
        });

        // Tombol pembungkus: akar dan blok teks Arab.
        document.querySelectorAll('#papanSimbol .tombol-bungkus').forEach(function (tombol) {
            tombol.addEventListener('mousedown', e => e.preventDefault());
            tombol.addEventListener('click', function () {
                jalankan(() => window.EditorSoal.bungkus(
                    tombol.dataset.awal, tombol.dataset.akhir, tombol.dataset.kosong
                ));
            });
        });

        // Perintah bawaan peramban: tebal, miring, garis bawah, pangkat, indeks.
        document.querySelectorAll('#papanSimbol .tombol-perintah').forEach(function (tombol) {
            tombol.addEventListener('mousedown', e => e.preventDefault());
            tombol.addEventListener('click', function () {
                jalankan(() => window.EditorSoal.perintah(tombol.dataset.perintah));
            });
        });

        document.getElementById('tombolLatex')?.addEventListener('mousedown', e => e.preventDefault());
        document.getElementById('tombolLatex')?.addEventListener('click', function () {
            jalankan(function () {
                if (window.EditorSoal.ubahLatexTerpilih()) return true;

                label.textContent = 'tandai tulisan rumusnya lebih dulu';
                label.classList.add('text-danger');
                setTimeout(() => label.classList.remove('text-danger'), 2500);

                return true;
            });
        });

        // Tombol tab ikut ditahan fokusnya. Tanpa ini, berpindah tab akan
        // memindahkan kursor keluar dari editor, dan tombol yang ditekan
        // sesudahnya menyisipkan di tempat yang salah.
        document.querySelectorAll('#papanSimbol .nav-link')
            .forEach(t => t.addEventListener('mousedown', e => e.preventDefault()));

        // ------------------------------------------------------------------
        // Gambar
        // ------------------------------------------------------------------
        // Gambar diunggah lebih dulu, lalu yang disisipkan ke kolom hanyalah
        // <img src="...">. Guru tidak perlu tahu bedanya — tempel, seret, atau
        // pilih berkas, ketiganya bermuara ke sini.

        const alamatUnggah = @json(route('soal.gambar'));
        const csrf = document.querySelector('meta[name=csrf-token]')?.content;

        async function unggah(berkas) {
            if (!berkas || !berkas.type.startsWith('image/')) return;

            const semula = label.textContent;
            label.textContent = 'mengunggah gambar…';
            label.classList.add('text-primary');

            const data = new FormData();
            data.append('gambar', berkas);

            try {
                const jawab = await fetch(alamatUnggah, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: data,
                });
                const isi = await jawab.json();

                if (!jawab.ok) {
                    // Pesan galat validasi Laravel bersarang di errors.gambar.
                    throw new Error(isi.pesan || isi.errors?.gambar?.[0] || 'Gambar gagal diunggah.');
                }

                sisipkan('<img src="' + isi.url + '" alt="Gambar soal">');
                label.textContent = semula;
            } catch (e) {
                label.textContent = e.message;
                label.classList.add('text-danger');
                setTimeout(() => {
                    label.classList.remove('text-danger');
                    label.textContent = semula;
                }, 3500);
            }
        }

        const pilihBerkas = document.getElementById('berkasGambar');

        document.getElementById('tombolGambar')?.addEventListener('mousedown', e => e.preventDefault());
        document.getElementById('tombolGambar')?.addEventListener('click', () => pilihBerkas.click());

        pilihBerkas?.addEventListener('change', function () {
            unggah(this.files[0]);
            this.value = '';   // supaya berkas yang sama bisa dipilih lagi
        });

        /** Editor tempat sebuah kejadian terjadi, bila memang di dalam editor. */
        function editorDari(e) {
            return e.target?.closest?.('[data-editor-untuk]');
        }

        // Tempelan papan klip: tangkapan layar dari Word, Excel, atau tombol
        // PrintScreen masuk sebagai berkas di dalam clipboardData.
        document.addEventListener('paste', function (e) {
            const editor = editorDari(e);
            if (!editor) return;

            const gambar = [...(e.clipboardData?.items || [])]
                .find(x => x.kind === 'file' && x.type.startsWith('image/'));

            if (!gambar) return;   // tempelan teks biasa ditangani editor

            e.preventDefault();
            window.EditorSoal?.fokuskan(editor);
            unggah(gambar.getAsFile());
        });

        // Seret-lepas berkas gambar ke kolom mana pun. Dipasang di tingkat
        // dokumen supaya kolom opsi yang ditambahkan belakangan ikut terlayani
        // tanpa perlu didaftarkan ulang.
        document.addEventListener('dragover', function (e) {
            const editor = editorDari(e);
            if (!editor || !e.dataTransfer?.types.includes('Files')) return;

            e.preventDefault();
            editor.classList.add('border-primary');
        });

        document.addEventListener('dragleave', function (e) {
            editorDari(e)?.classList.remove('border-primary');
        });

        document.addEventListener('drop', function (e) {
            const editor = editorDari(e);
            if (!editor || !e.dataTransfer?.files.length) return;

            e.preventDefault();
            editor.classList.remove('border-primary');
            window.EditorSoal?.fokuskan(editor);
            unggah(e.dataTransfer.files[0]);
        });
    })();
</script>
@endpush
