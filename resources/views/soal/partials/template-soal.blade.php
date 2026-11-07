{{--
    Galeri template soal.

    Dua layar dalam satu jendela: daftar template yang dikelompokkan per mata
    pelajaran, lalu penyunting isian template yang dipilih. Server yang merakit
    teks soal, gambar, opsi, kunci, dan pembahasannya sekaligus (lihat
    App\Support\Template); yang dikerjakan di sini hanya menampilkan
    pratinjaunya lalu menuangkan hasilnya ke formulir input soal.

    Gambar SVG diubah menjadi PNG dan diunggah lewat jalan yang sama dengan
    gambar unggahan biasa, sehingga butir hasil template tidak berbeda apa pun
    dari butir yang diketik sendiri — ikut terbawa ke export, import, cadangan,
    dan naskah ujian tanpa perlakuan khusus.
--}}
@php
    use App\Services\TemplateSoalService;
    use App\Support\Template\Daftar;

    $katalog = Daftar::katalog();
@endphp

<div class="modal fade" id="modalTemplate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-grid-1x2 me-2"></i><span id="tplJudul">Ambil dari Template Soal</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            {{--
                tabindex agar badan jendela ini dapat menerima fokus papan
                ketik sendiri. Saat kembali dari penyunting, fokus harus
                ditarik keluar dari kolom isian yang baru saja disembunyikan;
                kalau tidak, tombol panah dan Page Down tetap mengetik ke
                kolom itu alih-alih menggulir daftarnya.
            --}}
            <div class="modal-body" tabindex="-1" style="outline:none">

                {{-- ---------- Layar 1: daftar template ---------- --}}
                <div id="tplDaftar">
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input id="tplCari" class="form-control" autocomplete="off"
                               placeholder="Cari template, misal: pecahan, jam, katrol, suhu">
                    </div>

                    @foreach ($katalog as $kelompok)
                        <div class="tpl-kelompok mb-4">
                            <h6 class="text-uppercase text-muted small fw-semibold mb-2">{{ $kelompok['mapel'] }}</h6>
                            <div class="row g-2">
                                @foreach ($kelompok['template'] as $t)
                                    <div class="col-md-6 tpl-kartu" data-cari="{{ $t['cari'] }}">
                                        <button type="button" class="btn btn-outline-secondary w-100 h-100 text-start p-3 tpl-pilih"
                                                data-kode="{{ $t['kode'] }}">
                                            <span class="d-flex gap-3 align-items-start">
                                                <i class="bi {{ $t['ikon'] }} fs-4 text-primary lh-1 mt-1"></i>
                                                <span class="flex-grow-1">
                                                    <span class="d-block fw-semibold">{{ $t['nama'] }}</span>
                                                    <span class="d-block small text-muted lh-sm">{{ $t['ringkasan'] }}</span>
                                                </span>
                                            </span>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <p id="tplKosong" class="text-muted small d-none mb-0">
                        Tidak ada template yang cocok dengan pencarian itu.
                    </p>
                </div>

                {{-- ---------- Layar 2: penyunting template ---------- --}}
                <div id="tplSunting" class="d-none">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="row g-2" id="tplKolom"></div>

                            <button type="button" class="btn btn-sm btn-outline-secondary mt-3 d-none" id="tplAcak">
                                <i class="bi bi-shuffle me-1"></i>Acak angka
                            </button>
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label fw-semibold">Pratinjau soal</label>

                            <div class="d-flex flex-wrap gap-2 mb-3" id="tplJenis"></div>

                            <div class="d-none mb-3" id="tplKotakBenar">
                                <label class="form-label">Pernyataannya dibuat</label>
                                <select class="form-select" id="tplBenar">
                                    <option value="1">Benar — sesuai hitungan</option>
                                    <option value="0">Salah — sengaja dibuat keliru</option>
                                </select>
                            </div>

                            <div class="border rounded p-3 bg-body-tertiary" id="tplHasil">
                                <p class="text-muted small mb-0">Menyiapkan pratinjau…</p>
                            </div>

                            <div class="alert alert-danger small mt-3 mb-0 d-none" id="tplGalat"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer justify-content-between gap-2">
                <button type="button" class="btn btn-outline-secondary d-none" id="tplKembali">
                    <i class="bi bi-arrow-left me-1"></i>Daftar template
                </button>
                <span class="small text-muted flex-grow-1" id="tplCatatan">
                    Pilih template, isi angkanya, lalu tuangkan ke formulir.
                </span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary d-none" id="tplTerapkan">
                        Terapkan ke formulir<i class="bi bi-arrow-right ms-2"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('head')
<style>
    #modalTemplate .tpl-pilih.aktif,
    #modalTemplate .tpl-ikon.aktif {
        border-color: var(--bs-primary);
        background: var(--bs-primary-bg-subtle);
        color: var(--bs-primary);
    }
    #modalTemplate .tpl-jenis.aktif { background: var(--bs-primary); color: #fff; }
    #modalTemplate .tpl-warna {
        width: 2.1rem; height: 2.1rem; border-radius: 50%;
        border: 2px solid; padding: 0; cursor: pointer;
    }
    #modalTemplate .tpl-warna.aktif { outline: 3px solid var(--bs-primary); outline-offset: 2px; }
    #modalTemplate .tpl-gambar svg { max-width: 100%; height: auto; }
    /* Opsi yang menjadi kunci ditandai sejak di pratinjau: kekeliruan paling
       mahal pada soal hasil template adalah kunci yang salah, dan itu harus
       terlihat sebelum butirnya masuk ke formulir. */
    #modalTemplate .tpl-opsi-benar {
        background: var(--bs-success-bg-subtle);
        border-radius: .4rem; font-weight: 600;
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    const modal = document.getElementById('modalTemplate');
    if (!modal) return;

    // Seluruh keterangan template — kolom isian, nilai awal, jenis butir yang
    // didukung — datang dari PHP, jadi formulir di layar tidak mungkin
    // berbeda dari kolom yang divalidasi dan dipakai menggambar.
    const TEMPLATE = {};
    @json($katalog).forEach(k => k.template.forEach(t => TEMPLATE[t.kode] = t));

    const JENIS = @json(TemplateSoalService::JENIS);
    const WARNA = @json(\App\Support\Template\Template::WARNA);
    const LEBAR = @json(\App\Support\Kanvas::LEBAR);
    const TINGGI = @json(\App\Support\Kanvas::TINGGI);
    const DASAR = @json(\App\Support\AlamatPublik::dasar());
    const ALAMAT_RAKIT = @json(route('soal.template.rakit'));
    const ALAMAT_GAMBAR = @json(route('soal.gambar'));
    const csrf = document.querySelector('meta[name=csrf-token]')?.content;

    // Gambar dirasterkan dua kali ukuran kanvasnya supaya tetap tajam saat
    // lembar ujian ditampilkan pada layar beresolusi tinggi.
    const KERAPATAN = 2;

    const keadaan = { kode: null, isi: {}, jenis: 'pg', benar: true };

    let terakhir = null;      // hasil rakitan terakhir dari server
    let penunda = null;

    const layarDaftar = document.getElementById('tplDaftar');
    const layarSunting = document.getElementById('tplSunting');
    const kotakKolom = document.getElementById('tplKolom');
    const kotakJenis = document.getElementById('tplJenis');
    const kotakHasil = document.getElementById('tplHasil');
    const kotakGalat = document.getElementById('tplGalat');
    const kotakBenar = document.getElementById('tplKotakBenar');
    const tombolKembali = document.getElementById('tplKembali');
    const tombolTerapkan = document.getElementById('tplTerapkan');
    const judul = document.getElementById('tplJudul');
    const catatan = document.getElementById('tplCatatan');
    const badan = document.querySelector('#modalTemplate .modal-body');

    // Kedudukan gulir daftar saat guru meninggalkannya, supaya ia kembali ke
    // tempat yang sama — bukan ke tempat sisa guliran layar penyunting.
    let gulirDaftar = 0;

    // ------------------------------------------------------------------
    // Berpindah layar
    // ------------------------------------------------------------------

    function keDaftar() {
        keadaan.kode = null;
        terakhir = null;
        layarDaftar.classList.remove('d-none');
        layarSunting.classList.add('d-none');
        tombolKembali.classList.add('d-none');
        tombolTerapkan.classList.add('d-none');
        judul.textContent = 'Ambil dari Template Soal';
        catatan.textContent = 'Pilih template, isi angkanya, lalu tuangkan ke formulir.';

        // Fokus ditarik ke badan jendela lebih dahulu: tanpa ini ia tertinggal
        // di kolom isian penyunting yang sudah disembunyikan, dan daftar ini
        // tidak dapat digulir dengan papan ketik. preventScroll dipakai agar
        // pemberian fokus tidak membatalkan pemulihan kedudukan gulirnya.
        badan.focus({ preventScroll: true });
        badan.scrollTop = gulirDaftar;
    }

    function keTemplate(kode) {
        const t = TEMPLATE[kode];
        if (!t) return;

        gulirDaftar = badan.scrollTop;
        badan.scrollTop = 0;
        keadaan.kode = kode;
        keadaan.isi = { ...t.awal };
        keadaan.jenis = t.jenis.includes(keadaan.jenis) ? keadaan.jenis : t.jenis[0];

        layarDaftar.classList.add('d-none');
        layarSunting.classList.remove('d-none');
        tombolKembali.classList.remove('d-none');
        tombolTerapkan.classList.remove('d-none');
        judul.textContent = 'Template: ' + t.nama;
        catatan.textContent = 'Teks, opsi, dan kuncinya masih bisa disunting setelah diterapkan. Gambar disimpan sebagai PNG.';

        gambarJenis(t);
        gambarKolom();
        document.getElementById('tplAcak').classList.toggle('d-none', ! t.kolom.some(bisaDiacak));
        muat();
    }

    document.querySelectorAll('.tpl-pilih').forEach(function (tombol) {
        tombol.addEventListener('click', () => keTemplate(tombol.dataset.kode));
    });

    tombolKembali.addEventListener('click', keDaftar);

    /**
     * Menggulir isi jendela dengan papan ketik — berlaku di daftar template
     * maupun di penyuntingnya, yang pratinjaunya kerap lebih tinggi daripada
     * layar.
     *
     * Badan jendela sudah dapat menerima fokus, tetapi peramban tidak selalu
     * mengarahkan tombol gulir ke wadah ber-tabindex -1 — roda tetikus jalan
     * sedangkan Page Down diam. Jadi tombolnya ditangani sendiri, dan hanya
     * saat fokus tidak sedang berada di kolom isian, supaya tidak mengganggu
     * pengetikan di kotak pencarian maupun kotak angka.
     */
    document.getElementById('modalTemplate').addEventListener('keydown', function (e) {
        if (e.target.closest('input, textarea, select, .select2-selection')) {
            return;
        }

        const selayar = badan.clientHeight * 0.9;
        const geser = {
            PageDown: selayar, PageUp: -selayar,
            ArrowDown: 60, ArrowUp: -60,
            Home: -badan.scrollHeight, End: badan.scrollHeight,
        }[e.key];

        if (geser === undefined) {
            return;
        }

        badan.scrollTop += geser;
        e.preventDefault();
    });

    // ------------------------------------------------------------------
    // Pencarian di daftar
    // ------------------------------------------------------------------

    document.getElementById('tplCari').addEventListener('input', function () {
        const cari = this.value.trim().toLowerCase();
        let ketemu = 0;

        document.querySelectorAll('#tplDaftar .tpl-kartu').forEach(function (kartu) {
            const cocok = cari === '' || kartu.dataset.cari.includes(cari);
            kartu.classList.toggle('d-none', !cocok);
            if (cocok) ketemu++;
        });

        // Judul mata pelajaran ikut disembunyikan bila seluruh isinya tersaring,
        // supaya tidak ada kepala kelompok yang menggantung tanpa kartu.
        document.querySelectorAll('#tplDaftar .tpl-kelompok').forEach(function (kelompok) {
            const ada = kelompok.querySelectorAll('.tpl-kartu:not(.d-none)').length > 0;
            kelompok.classList.toggle('d-none', !ada);
        });

        document.getElementById('tplKosong').classList.toggle('d-none', ketemu > 0);
    });

    // ------------------------------------------------------------------
    // Formulir isian
    // ------------------------------------------------------------------

    /** Kolom bisa bergantung pada pilihan kolom lain; yang tak berlaku disembunyikan. */
    function berlaku(kolom) {
        return Object.entries(kolom.tampil || {})
            .every(([lain, nilai]) => nilai.map(String).includes(String(keadaan.isi[lain])));
    }

    function gambarJenis(t) {
        kotakJenis.innerHTML = '';

        t.jenis.forEach(function (nilai) {
            const tombol = document.createElement('button');
            tombol.type = 'button';
            tombol.className = 'btn btn-sm btn-outline-primary tpl-jenis' + (nilai === keadaan.jenis ? ' aktif' : '');
            tombol.textContent = JENIS[nilai];
            tombol.addEventListener('click', function () {
                keadaan.jenis = nilai;
                kotakJenis.querySelectorAll('.tpl-jenis').forEach(b => b.classList.toggle('aktif', b === tombol));
                kotakBenar.classList.toggle('d-none', nilai !== 'benar_salah');
                muat();
            });
            kotakJenis.appendChild(tombol);
        });

        kotakBenar.classList.toggle('d-none', keadaan.jenis !== 'benar_salah');
    }

    /**
     * Menggambar ulang seluruh kolom isian.
     *
     * Kolom yang sedang dipakai ikut dibuang dan dibuat ulang, jadi fokus
     * papan ketik harus dikembalikan sendiri. Tanpa itu fokus jatuh ke
     * document.body — di luar jendela ini — sehingga tombol gulir tidak lagi
     * bekerja dan urutan Tab kembali ke halaman di belakangnya. Kedudukan
     * gulir ikut disimpan karena memberi fokus dapat menggeser pandangan.
     */
    function gambarKolom() {
        const namaAktif = document.activeElement?.closest?.('#tplKolom [data-nama]')?.dataset.nama;
        const gulir = badan.scrollTop;

        kotakKolom.innerHTML = '';

        TEMPLATE[keadaan.kode].kolom.filter(berlaku).forEach(function (kolom) {
            const kotak = document.createElement('div');
            kotak.className = 'col-' + (kolom.lebar || 12);
            kotak.dataset.nama = kolom.nama;
            kotak.innerHTML = '<label class="form-label">' + lolos(kolom.label) + '</label>';
            kotak.appendChild(kendali(kolom));

            if (kolom.bantuan) {
                const bantuan = document.createElement('div');
                bantuan.className = 'form-text';
                bantuan.textContent = kolom.bantuan;
                kotak.appendChild(bantuan);
            }

            kotakKolom.appendChild(kotak);
        });

        kembalikanFokus(namaAktif, gulir);
    }

    /**
     * Mengembalikan fokus ke kolom yang sama setelah digambar ulang, atau ke
     * badan jendela bila kolom itu sudah tidak berlaku lagi — yang penting
     * fokus tidak lepas ke luar jendela.
     *
     * Select2 menyembunyikan select aslinya dan menggantinya dengan kotak
     * buatannya sendiri, jadi yang dapat menerima fokus adalah kotak itu.
     * preventScroll dipakai supaya pemberian fokus tidak menyeret pandangan
     * ke atas; kedudukan gulirnya dipulihkan tepat sesudahnya.
     */
    function kembalikanFokus(nama, gulir) {
        const kotak = nama ? kotakKolom.querySelector('[data-nama="' + nama + '"]') : null;
        const kendaliBaru = kotak?.querySelector('.select2-selection, input, select, button, textarea');

        (kendaliBaru || badan).focus({ preventScroll: true });
        badan.scrollTop = gulir;
    }

    /**
     * Mengubah pilihan bisa memunculkan atau menyembunyikan kolom lain, jadi
     * formulirnya digambar ulang. Mengetik angka tidak pernah begitu, dan
     * menggambar ulang di tiap ketikan hanya akan merebut kursor guru.
     */
    function ubah(nama, nilai, gambarUlang) {
        keadaan.isi[nama] = nilai;

        if (gambarUlang) {
            gambarKolom();
            muat();
        } else {
            tunda();
        }
    }

    function kendali(kolom) {
        const nilai = keadaan.isi[kolom.nama];

        if (kolom.jenis === 'pilihan-ikon') {
            const baris = document.createElement('div');
            baris.className = 'row g-2';

            Object.entries(kolom.pilihan).forEach(function ([kode, label]) {
                const kolomKecil = document.createElement('div');
                kolomKecil.className = 'col-4';
                kolomKecil.innerHTML =
                    '<button type="button" class="btn btn-outline-secondary w-100 h-100 py-2 px-1 tpl-ikon'
                    + (String(nilai) === kode ? ' aktif' : '') + '">'
                    + '<svg viewBox="0 0 32 32" width="30" height="30" class="d-block mx-auto mb-1"'
                    + ' fill="currentColor" fill-opacity=".2" stroke="currentColor" stroke-width="2"'
                    + ' stroke-linejoin="round" fill-rule="evenodd">' + (kolom.ikon?.[kode] || '') + '</svg>'
                    + '<span class="small lh-sm d-block">' + lolos(label) + '</span></button>';

                kolomKecil.querySelector('button').addEventListener('click', () => ubah(kolom.nama, kode, true));
                baris.appendChild(kolomKecil);
            });

            return baris;
        }

        if (kolom.jenis === 'warna') {
            const baris = document.createElement('div');
            baris.className = 'd-flex flex-wrap gap-2';

            Object.entries(WARNA).forEach(function ([nama, [isi, garis]]) {
                const tombol = document.createElement('button');
                tombol.type = 'button';
                tombol.className = 'tpl-warna' + (nilai === nama ? ' aktif' : '');
                tombol.style.background = isi;
                tombol.style.borderColor = garis;
                tombol.title = nama;
                tombol.addEventListener('click', () => ubah(kolom.nama, nama, true));
                baris.appendChild(tombol);
            });

            return baris;
        }

        if (kolom.jenis === 'pilihan') {
            const pilih = document.createElement('select');
            pilih.className = 'form-select';

            Object.entries(kolom.pilihan).forEach(function ([kode, label]) {
                const opsi = document.createElement('option');
                opsi.value = kode;
                opsi.textContent = label;
                opsi.selected = String(nilai) === kode;
                pilih.appendChild(opsi);
            });

            pilih.addEventListener('change', () => ubah(kolom.nama, pilih.value, true));

            return pilih;
        }

        if (kolom.jenis === 'centang') {
            const bungkus = document.createElement('div');
            bungkus.className = 'form-check';
            bungkus.innerHTML = '<input class="form-check-input" type="checkbox"' + (nilai ? ' checked' : '') + '>';
            bungkus.querySelector('input').addEventListener('change', function () {
                ubah(kolom.nama, this.checked, true);
            });

            return bungkus;
        }

        const isian = document.createElement('input');
        isian.className = 'form-control';
        isian.value = nilai ?? '';

        if (kolom.jenis === 'angka') {
            isian.type = 'number';
            isian.step = kolom.langkah ?? 'any';
            if (kolom.min !== undefined) isian.min = kolom.min;
            if (kolom.max !== undefined) isian.max = kolom.max;
        }

        isian.addEventListener('input', () => ubah(kolom.nama, isian.value, false));

        return isian;
    }

    document.getElementById('tplBenar').addEventListener('change', function () {
        keadaan.benar = this.value === '1';
        muat();
    });

    /**
     * Angka yang pantas diacak: yang batas atasnya masih kecil. Skala peta
     * dan berat beban dibiarkan apa adanya — "skala 1 : 7" atau "beban 9 N"
     * bukan angka yang pernah muncul di soal sungguhan.
     */
    function bisaDiacak(kolom) {
        return kolom.jenis === 'angka' && (kolom.max ?? 0) <= 1000;
    }

    /**
     * Membuat satu variasi baru dari template yang sama — cara tercepat
     * menyiapkan beberapa butir sejenis untuk paket soal yang berbeda.
     *
     * Sebagian template punya syarat antarangka (sisi miring harus lebih
     * panjang daripada tinggi, bagian yang diarsir tidak boleh melebihi
     * banyak bagian). Angka acak bisa melanggarnya, jadi hasilnya dicoba
     * ulang beberapa kali sebelum menyerah dan menampilkan pesannya.
     */
    document.getElementById('tplAcak').addEventListener('click', async function () {
        for (let percobaan = 0; percobaan < 6; percobaan++) {
            TEMPLATE[keadaan.kode].kolom.filter(berlaku).filter(bisaDiacak).forEach(function (kolom) {
                const bawah = Math.max(kolom.min ?? 2, 2);
                const atas = Math.min(kolom.max ?? 20, Math.max(bawah + 2, 20));

                keadaan.isi[kolom.nama] = Math.floor(Math.random() * (atas - bawah + 1)) + bawah;
            });

            gambarKolom();
            await muat();

            if (terakhir) return;
        }
    });

    // ------------------------------------------------------------------
    // Pratinjau
    // ------------------------------------------------------------------

    function tunda() {
        clearTimeout(penunda);
        penunda = setTimeout(muat, 350);
    }

    async function muat() {
        clearTimeout(penunda);

        if (!keadaan.kode) return;

        try {
            const jawab = await fetch(ALAMAT_RAKIT, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    kode: keadaan.kode,
                    isi: keadaan.isi,
                    jenis: keadaan.jenis,
                    benar: keadaan.benar ? 1 : 0,
                }),
            });

            const isi = await jawab.json();

            if (!jawab.ok) {
                // Pesan validasi Laravel bersarang per kolom; yang pertama
                // sudah cukup menjelaskan isian mana yang bermasalah.
                throw new Error(Object.values(isi.errors || {})[0]?.[0] || isi.message || 'Soal gagal disusun.');
            }

            terakhir = isi;
            tampilkan(isi);
        } catch (e) {
            terakhir = null;
            kotakGalat.textContent = e.message;
            kotakGalat.classList.remove('d-none');
        }
    }

    function tampilkan(data) {
        kotakGalat.classList.add('d-none');

        // Teks butir, opsi, dan pembahasan sengaja tidak diloloskan: template
        // memang menghasilkan HTML — rumus kimia memakai <sub>, pembahasan
        // memakai <br>. Yang diketik guru sudah diloloskan di sisi template
        // (lihat Template::bebas), jadi yang sampai di sini aman.
        let html = '<p class="mb-2">' + data.teks + '</p>';

        if (data.svg) {
            html += '<div class="tpl-gambar text-center my-2">' + data.svg + '</div>';
        }

        if (data.jenis === 'pg') {
            html += '<div class="mt-3">' + data.opsi.map(o => `
                <div class="d-flex gap-2 px-2 py-1 ${o.benar ? 'tpl-opsi-benar' : ''}">
                    <span>${o.key}.</span><span>${o.teks}</span>
                    ${o.benar ? '<i class="bi bi-check-circle-fill text-success ms-auto"></i>' : ''}
                </div>`).join('') + '</div>';
        } else if (data.jenis === 'essay') {
            html += '<p class="mb-0 mt-3 small">Kunci jawaban: <strong>' + data.jawaban + '</strong></p>';
        } else {
            html += '<p class="mb-0 mt-3 small">Kunci jawaban: <strong>'
                + (data.kunci_bs === 'benar' ? 'Benar' : 'Salah') + '</strong></p>';
        }

        html += '<p class="small text-muted mt-3 mb-0"><i class="bi bi-lightbulb me-1"></i>' + data.pembahasan + '</p>';

        if (data.keterangan) {
            html += '<p class="small text-muted mt-2 mb-0 fst-italic">' + lolos(data.keterangan) + '</p>';
        }

        kotakHasil.innerHTML = html;
    }

    function lolos(teks) {
        const kotak = document.createElement('div');
        kotak.textContent = teks ?? '';

        return kotak.innerHTML;
    }

    // ------------------------------------------------------------------
    // Menuangkan hasil ke formulir
    // ------------------------------------------------------------------

    /** SVG dari server diubah menjadi berkas PNG, siap diunggah. */
    async function keBerkasPng(svg) {
        const dokumen = new DOMParser().parseFromString(svg, 'image/svg+xml');
        const akar = dokumen.documentElement;

        akar.setAttribute('width', LEBAR * KERAPATAN);
        akar.setAttribute('height', TINGGI * KERAPATAN);

        const sumber = 'data:image/svg+xml;charset=utf-8,'
            + encodeURIComponent(new XMLSerializer().serializeToString(akar));

        const gambar = await new Promise(function (selesai, gagal) {
            const img = new Image();
            img.onload = () => selesai(img);
            img.onerror = () => gagal(new Error('Gambar gagal disiapkan peramban ini.'));
            img.src = sumber;
        });

        const kanvas = document.createElement('canvas');
        kanvas.width = LEBAR * KERAPATAN;
        kanvas.height = TINGGI * KERAPATAN;

        const konteks = kanvas.getContext('2d');
        // Latar putih ditulis sendiri: bagian SVG yang tembus pandang akan
        // menjadi hitam pekat begitu disimpan sebagai PNG tanpa ini.
        konteks.fillStyle = '#ffffff';
        konteks.fillRect(0, 0, kanvas.width, kanvas.height);
        konteks.drawImage(gambar, 0, 0, kanvas.width, kanvas.height);

        return await new Promise(function (selesai, gagal) {
            kanvas.toBlob(b => b ? selesai(b) : gagal(new Error('Gambar gagal disimpan.')), 'image/png');
        });
    }

    async function unggahGambar(svg) {
        const data = new FormData();
        data.append('gambar', await keBerkasPng(svg), 'template-soal.png');

        const jawab = await fetch(ALAMAT_GAMBAR, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: data,
        });
        const isi = await jawab.json();

        if (!jawab.ok) {
            throw new Error(isi.pesan || isi.errors?.gambar?.[0] || 'Gambar gagal diunggah.');
        }

        // Alamat dikembalikan siap tampil, berawalan subfolder aplikasi;
        // formulir menyimpannya dalam bentuk netral "/storage/...".
        return DASAR && isi.url.startsWith(DASAR + '/storage/') ? isi.url.slice(DASAR.length) : isi.url;
    }

    /** Isi kolom sekaligus editor kayanya, bila kolom itu memakai editor. */
    function isi(pemilih, nilai) {
        const kolom = document.querySelector(pemilih);
        if (!kolom) return;

        if (window.EditorSoal?.setel) {
            window.EditorSoal.setel(kolom, nilai);
        } else {
            kolom.value = nilai;
        }
    }

    /** Sediakan tepat sebanyak yang dibutuhkan baris opsi pilihan ganda. */
    function siapkanOpsi(jumlah) {
        const daftar = document.getElementById('daftarOpsi');

        while (daftar.children.length > jumlah) {
            daftar.lastElementChild.remove();
        }
        while (daftar.children.length < jumlah) {
            tambahOpsi();
        }

        nomoriUlangOpsi();

        return [...daftar.children];
    }

    function isiBaris(baris, opsi) {
        const kolom = baris.querySelector('input[name="opsi_text[]"]');
        const centang = baris.querySelector('.kunci-opsi');

        if (kolom && window.EditorSoal?.setel) {
            window.EditorSoal.setel(kolom, opsi.teks);
        } else if (kolom) {
            kolom.value = opsi.teks;
        }

        if (centang) centang.checked = opsi.benar;
    }

    tombolTerapkan.addEventListener('click', async function () {
        if (!terakhir) return;

        const semula = tombolTerapkan.innerHTML;
        tombolTerapkan.disabled = true;
        tombolTerapkan.textContent = 'Menyiapkan gambar…';

        try {
            const alamat = terakhir.svg ? await unggahGambar(terakhir.svg) : null;

            // Jenis butir ditetapkan lebih dulu: gantiJenis() yang menentukan
            // bagian formulir mana yang aktif, dan kolom bagian yang tidak
            // aktif sengaja dimatikan sehingga tidak ikut terkirim.
            const radio = document.getElementById('jenis-' + terakhir.jenis);
            if (radio) {
                radio.checked = true;
                gantiJenis();
            }

            isi('textarea[name=pertanyaan]', terakhir.teks
                + (alamat ? '<br><img src="' + alamat + '" alt="Gambar ' + lolos(terakhir.judul) + '">' : ''));
            isi('textarea[name=pembahasan]', terakhir.pembahasan);

            if (terakhir.jenis === 'pg') {
                siapkanOpsi(terakhir.opsi.length).forEach(function (baris, i) {
                    isiBaris(baris, terakhir.opsi[i]);
                });
            } else if (terakhir.jenis === 'essay') {
                isi('textarea[name=kunci_essay]', terakhir.kunci_essay);
                const kata = document.querySelector('input[name=kata_kunci]');
                if (kata) kata.value = terakhir.kata_kunci;
            } else {
                const pilih = document.querySelector(`input[name=kunci_bs][value="${terakhir.kunci_bs}"]`);
                if (pilih) pilih.checked = true;
            }

            bootstrap.Modal.getInstance(modal)?.hide();
        } catch (e) {
            kotakGalat.textContent = e.message;
            kotakGalat.classList.remove('d-none');
        } finally {
            tombolTerapkan.disabled = false;
            tombolTerapkan.innerHTML = semula;
        }
    });

    // ------------------------------------------------------------------

    @if ($awal = request('template'))
        // Dibuka langsung saat guru datang lewat tombol di daftar soal; bila
        // alamatnya menyebut satu template tertentu, templatenya pun langsung
        // terbuka tanpa perlu dipilih lagi.
        document.addEventListener('DOMContentLoaded', function () {
            bootstrap.Modal.getOrCreateInstance(modal).show();

            if (TEMPLATE[@json($awal)]) keTemplate(@json($awal));
        });
    @endif
})();
</script>
@endpush
