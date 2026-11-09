@extends('layouts.app')
@section('title', $item->exists ? 'Ubah Soal' : 'Input Soal')
@section('subtitle', 'Pilihan ganda, PG kompleks, essay, penjodohan, benar/salah, dan benar/salah kompleks')

@section('content')
@php
    use App\Models\Soal;
    use App\Support\Referensi;

    $jenis = old('jenis', $item->jenis ?? Soal::PG);

    // Nilai awal untuk tiap bagian form, memakai old() agar isian tidak
    // hilang saat validasi gagal.
    $opsiLama  = old('opsi_text', collect($item->jenis === Soal::PENJODOHAN ? [] : ($item->opsi ?? []))->pluck('text')->all());
    $opsiLama  = $opsiLama ?: ['', '', '', ''];
    $kunciPg   = old('kunci_pg', in_array($item->jenis, [Soal::PG, Soal::PG_KOMPLEKS], true) ? ($item->kunci ?? []) : []);
    $jodohKiri = old('jodoh_kiri', collect($item->opsi['kiri'] ?? [])->pluck('text')->all()) ?: ['', ''];
    $jodohKanan = old('jodoh_kanan', collect($item->opsi['kanan'] ?? [])->pluck('text')->all()) ?: ['', ''];
    $kunciBs   = old('kunci_bs', $item->jenis === Soal::BENAR_SALAH ? ($item->kunci[0] ?? 'benar') : 'benar');

    // Benar/salah kompleks: daftar pernyataan beserta kuncinya masing-masing.
    // Keduanya memakai indeks yang sama agar tetap berpasangan, baik saat
    // dimuat dari butir yang sudah ada maupun setelah validasi gagal.
    $bskPernyataan = old('bsk_pernyataan', $item->jenis === Soal::BENAR_SALAH_KOMPLEKS
        ? collect($item->opsi ?? [])->pluck('text')->all() : []);
    $bskPernyataan = $bskPernyataan ?: ['', '', ''];
    $bskKunci  = old('bsk_kunci', $item->jenis === Soal::BENAR_SALAH_KOMPLEKS
        ? collect($item->opsi ?? [])->map(fn ($o) => $item->kunci[$o['key']] ?? 'benar')->all() : []);
    $kunciEssay = old('kunci_essay', $item->jenis === Soal::ESSAY ? ($item->kunci['jawaban'] ?? '') : '');
    $kataKunci = old('kata_kunci', implode(', ', $item->jenis === Soal::ESSAY ? ($item->kunci['kata_kunci'] ?? []) : []));
@endphp

<form method="POST" enctype="multipart/form-data"
      action="{{ $item->exists ? route('soal.update', $item) : route('soal.store') }}">
    @csrf
    @if ($item->exists) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-lg-8">

            <div class="card mb-3">
                <div class="card-header">Jenis Soal</div>
                <div class="card-body">
                    <div class="row g-2">
                        @foreach (Soal::JENIS as $nilai => $label)
                            <div class="col-6 col-md-4">
                                <input type="radio" class="btn-check" name="jenis" id="jenis-{{ $nilai }}"
                                       value="{{ $nilai }}" @checked($jenis === $nilai) onchange="gantiJenis()">
                                <label class="btn btn-outline-primary w-100 text-start py-2" for="jenis-{{ $nilai }}">
                                    <span class="small">{{ $label }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Pertanyaan</span>
                    {{-- Template merakit teks, gambar, opsi, dan kuncinya sekaligus;
                         semuanya masih bisa disunting di formulir ini setelahnya. --}}
                    <button type="button" class="btn btn-sm btn-outline-primary"
                            data-bs-toggle="modal" data-bs-target="#modalTemplate">
                        <i class="bi bi-grid-1x2 me-1"></i>Ambil dari template
                    </button>
                </div>
                <div class="card-body">
                    <textarea name="pertanyaan" rows="5" required dir="auto"
                              data-sisip-target data-label-sisip="Pertanyaan"
                              class="form-control @error('pertanyaan') is-invalid @enderror"
                              placeholder="Tulis teks pertanyaan di sini">{{ old('pertanyaan', $item->pertanyaan) }}</textarea>
                    @error('pertanyaan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">
                        Yang tampil di sini sama persis dengan yang dilihat siswa. Pangkat, akar,
                        pecahan bertingkat, huruf Arab, dan gambar disisipkan lewat papan di bawah.
                    </div>

                    {{-- Menawarkan memindahkan opsi A, B, C, ... yang ikut tertempel
                         di sini ke kolom Opsi Jawaban masing-masing. --}}
                    @include('soal.partials.pecah-opsi')
                </div>
            </div>

            {{-- ---------- Lampiran audio / video ---------- --}}
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-volume-up me-1"></i>Audio / Video (opsional)</span>
                    @if ($item->punya_media)
                        <span class="badge text-bg-success-subtle text-success border border-success-subtle">
                            {{ $item->media_tipe === 'video' ? 'Ada video' : 'Ada audio' }}
                        </span>
                    @endif
                </div>
                <div class="card-body">
                    @if ($item->punya_media)
                        <x-media-soal :soal="$item" judul="Lampiran saat ini" />

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="hapus_media" value="1" id="hapus_media">
                            <label class="form-check-label" for="hapus_media">Hapus lampiran ini dari soal</label>
                        </div>
                    @endif

                    <label class="form-label" for="media">
                        {{ $item->punya_media ? 'Ganti dengan berkas lain' : 'Pilih berkas audio atau video' }}
                    </label>
                    <input type="file" name="media" id="media" accept="audio/*,video/*"
                           class="form-control @error('media') is-invalid @enderror">
                    @error('media')<div class="invalid-feedback">{{ $message }}</div>@enderror

                    <div class="form-text">
                        Untuk soal menyimak: rekaman diputar peserta di lembar ujiannya sendiri,
                        dan boleh diulang sebanyak yang diperlukan.
                        {{ \App\Services\MediaSoalService::keteranganBatas() }}
                        Berkas disimpan di server ujian, jadi tetap dapat diputar tanpa internet.
                    </div>
                </div>
            </div>

            @include('partials.papan-simbol')

            {{-- ---------- Pilihan ganda & PG kompleks ---------- --}}
            <div class="card mb-3 bagian-jenis" data-untuk="pg pg_kompleks">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Opsi Jawaban</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="tambahOpsi()">
                        <i class="bi bi-plus-lg me-1"></i>Tambah opsi
                    </button>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-1" id="petunjukKunci"></p>
                    <p class="small text-muted">
                        Opsi yang dibiarkan kosong akan terisi hurufnya sendiri saat disimpan —
                        opsi A menjadi <strong>A</strong>, opsi B menjadi <strong>B</strong>, dan seterusnya.
                    </p>
                    <div id="daftarOpsi">
                        @foreach ($opsiLama as $i => $teks)
                            <div class="input-group mb-2 baris-opsi">
                                <span class="input-group-text huruf-opsi" style="width:2.75rem">{{ chr(65 + $i) }}</span>
                                <div class="input-group-text">
                                    <input class="form-check-input mt-0 kunci-opsi" type="checkbox"
                                           name="kunci_pg[]" value="{{ chr(65 + $i) }}"
                                           @checked(in_array(chr(65 + $i), (array) $kunciPg, true))
                                           title="Tandai sebagai kunci jawaban">
                                </div>
                                <input name="opsi_text[]" value="{{ $teks }}" class="form-control" placeholder="Teks opsi"
                                       dir="auto" data-sisip-target data-label-sisip="Opsi {{ chr(65 + $i) }}">
                                <button type="button" class="btn btn-outline-danger" onclick="hapusOpsi(this)" title="Hapus opsi">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>
                    @error('kunci_pg')<div class="text-danger small">{{ $message }}</div>@enderror
                    @error('opsi_text')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- ---------- Benar / salah ---------- --}}
            <div class="card mb-3 bagian-jenis" data-untuk="benar_salah">
                <div class="card-header">Kunci Jawaban</div>
                <div class="card-body">
                    <p class="small text-muted">Pernyataan di atas bernilai:</p>
                    <div class="d-flex gap-3">
                        @foreach (['benar' => 'Benar', 'salah' => 'Salah'] as $nilai => $label)
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="kunci_bs" value="{{ $nilai }}"
                                       id="bs-{{ $nilai }}" @checked($kunciBs === $nilai)>
                                <label class="form-check-label" for="bs-{{ $nilai }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- ---------- Benar / salah kompleks ---------- --}}
            <div class="card mb-3 bagian-jenis" data-untuk="benar_salah_kompleks">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Pernyataan &amp; Kuncinya</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="tambahPernyataanBsk()">
                        <i class="bi bi-plus-lg me-1"></i>Tambah pernyataan
                    </button>
                </div>
                <div class="card-body">
                    <p class="small text-muted">
                        Satu butir memuat beberapa pernyataan, dan peserta menilai
                        <strong>tiap</strong> pernyataan benar atau salah. Skornya parsial —
                        pernyataan yang dinilai tepat tetap mendapat bagian bobotnya,
                        jadi butir ini tidak harus tepat seluruhnya untuk berskor.
                    </p>
                    <div id="daftarPernyataanBsk">
                        @foreach ($bskPernyataan as $i => $teks)
                            {{-- Kunci dan tombol hapus turun ke barisnya sendiri di layar
                                 sempit; dijejalkan sebaris, kolom pernyataannya tinggal
                                 selebar satu kata. --}}
                            <div class="baris-bsk row g-2 align-items-center mb-2">
                                <div class="col-12 col-sm">
                                    <div class="input-group">
                                        <span class="input-group-text nomor-bsk" style="width:2.5rem">{{ $i + 1 }}</span>
                                        <input name="bsk_pernyataan[{{ $i }}]" value="{{ $teks }}" class="form-control"
                                               placeholder="Pernyataan yang dinilai benar atau salah"
                                               dir="auto" data-sisip-target data-label-sisip="Pernyataan {{ $i + 1 }}">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-auto d-flex gap-2 align-items-center justify-content-end">
                                    @foreach (Soal::PILIHAN_BS as $nilai => $label)
                                        <label class="pil-kunci-bsk">
                                            <input class="form-check-input mt-0 kunci-bsk" type="radio"
                                                   name="bsk_kunci[{{ $i }}]" value="{{ $nilai }}"
                                                   @checked(($bskKunci[$i] ?? 'benar') === $nilai)>
                                            <span>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                    <button type="button" class="btn btn-outline-danger" onclick="hapusPernyataanBsk(this)"
                                            title="Hapus pernyataan">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('bsk_pernyataan')<div class="text-danger small">{{ $message }}</div>@enderror
                    @error('bsk_kunci')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- ---------- Essay ---------- --}}
            <div class="card mb-3 bagian-jenis" data-untuk="essay">
                <div class="card-header">Kunci &amp; Rubrik Essay</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Jawaban model <span class="text-danger">*</span></label>
                        <textarea name="kunci_essay" rows="4" class="form-control" dir="auto"
                                  data-sisip-target data-label-sisip="Jawaban model"
                                  placeholder="Jawaban ideal sebagai acuan guru saat menilai">{{ $kunciEssay }}</textarea>
                    </div>
                    <div>
                        <label class="form-label">Kata kunci penilaian</label>
                        <input name="kata_kunci" value="{{ $kataKunci }}" class="form-control"
                               placeholder="mis. evaporasi, kondensasi, presipitasi">
                        <div class="form-text">Dipisah koma. Ditampilkan saat guru mengoreksi agar penilaian konsisten.</div>
                    </div>
                    <div class="alert alert-warning small mt-3 mb-0 py-2">
                        <i class="bi bi-info-circle me-1"></i>
                        Soal essay tidak dikoreksi otomatis — skornya diisi guru di menu
                        <strong>Hasil &amp; Laporan &rarr; Daftar Nilai Ujian</strong>.
                    </div>
                </div>
            </div>

            {{-- ---------- Penjodohan ---------- --}}
            <div class="card mb-3 bagian-jenis" data-untuk="penjodohan">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Pasangan Penjodohan</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="tambahJodoh()">
                        <i class="bi bi-plus-lg me-1"></i>Tambah pasangan
                    </button>
                </div>
                <div class="card-body">
                    <p class="small text-muted">
                        Kolom kiri adalah pernyataan yang dijodohkan, kolom kanan pasangan yang benar.
                        Saat ujian, urutan kolom kanan diacak otomatis bila pengacakan opsi diaktifkan pada paket soal.
                    </p>
                    <div id="daftarJodoh">
                        @foreach ($jodohKiri as $i => $kiri)
                            <div class="row g-2 mb-2 baris-jodoh">
                                <div class="col-5">
                                    <div class="input-group">
                                        <span class="input-group-text nomor-jodoh" style="width:2.5rem">{{ $i + 1 }}</span>
                                        <input name="jodoh_kiri[]" value="{{ $kiri }}" class="form-control" placeholder="Pernyataan"
                                               dir="auto" data-sisip-target data-label-sisip="Pernyataan {{ $i + 1 }}">
                                    </div>
                                </div>
                                <div class="col-auto d-flex align-items-center text-muted"><i class="bi bi-arrow-right"></i></div>
                                <div class="col">
                                    <div class="input-group">
                                        <span class="input-group-text huruf-jodoh" style="width:2.5rem">{{ chr(65 + $i) }}</span>
                                        <input name="jodoh_kanan[]" value="{{ $jodohKanan[$i] ?? '' }}" class="form-control" placeholder="Pasangan"
                                               dir="auto" data-sisip-target data-label-sisip="Pasangan {{ chr(65 + $i) }}">
                                        <button type="button" class="btn btn-outline-danger" onclick="hapusJodoh(this)">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('jodoh_kiri')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Pembahasan</div>
                <div class="card-body">
                    <textarea name="pembahasan" rows="3" class="form-control" dir="auto"
                              data-sisip-target data-label-sisip="Pembahasan"
                              placeholder="Penjelasan jawaban, ditampilkan ke siswa bila guru mengizinkan">{{ old('pembahasan', $item->pembahasan) }}</textarea>
                </div>
            </div>
        </div>

        {{-- ---------- Sidebar atribut ---------- --}}
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">Penempatan &amp; Atribut</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Topik</label>
                        <select name="topik_id" class="form-select">
                            <option value="">— tanpa topik —</option>
                            @foreach ($daftarTopik as $t)
                                <option value="{{ $t->id }}" @selected(old('topik_id', $item->topik_id) == $t->id)>
                                    {{ Str::limit($t->nama_topik, 70) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mata pelajaran</label>
                        <x-pilih-mapel :value="$item->mata_pelajaran_id" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tingkat kelas</label>
                        <x-pilih-tingkat :value="$item->tingkat_kelas_id" />
                    </div>
                    @if (Referensi::pakaiJurusan())
                        <div class="mb-3">
                            <label class="form-label">Jurusan</label>
                            <x-pilih-jurusan :value="$item->jurusan_id" />
                            <div class="form-text">Kosongkan untuk soal mapel umum yang berlaku di semua jurusan.</div>
                        </div>
                    @endif

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Bobot <span class="text-danger">*</span></label>
                            <input type="number" step="0.5" min="0.5" name="bobot"
                                   value="{{ old('bobot', $item->bobot ?? 1) }}" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Kesukaran</label>
                            <select name="tingkat_kesukaran" class="form-select">
                                <option value="">—</option>
                                @foreach (Soal::KESUKARAN as $nilai => $label)
                                    <option value="{{ $nilai }}" @selected(old('tingkat_kesukaran', $item->tingkat_kesukaran) === $nilai)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Level kognitif</label>
                        <select name="level_kognitif" class="form-select">
                            <option value="">—</option>
                            @foreach (Referensi::levelKognitif() as $nilai => $label)
                                <option value="{{ $nilai }}" @selected(old('level_kognitif', $item->level_kognitif) === $nilai)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Kode soal</label>
                            <input name="kode_soal" value="{{ old('kode_soal', $item->kode_soal) }}" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Tahun ajaran</label>
                            <x-pilih-tahun-ajaran :value="$item->tahun_ajaran" />
                        </div>
                    </div>

                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="is_aktif" value="1" id="is_aktif"
                               @checked(old('is_aktif', $item->is_aktif ?? true))>
                        <label class="form-check-label" for="is_aktif">Soal aktif (bisa dipilih ke paket)</label>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
                @unless ($item->exists)
                    <button class="btn btn-outline-primary" name="lanjut" value="1">
                        <i class="bi bi-save2 me-1"></i>Simpan &amp; input soal berikutnya
                    </button>
                @endunless
                <a href="{{ route('soal.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </div>
    </div>
</form>

@include('soal.partials.template-soal')

@push('head')
<style>
    /* Kunci tiap pernyataan benar/salah kompleks: tombol pil, bukan radio
       telanjang, supaya sasaran ketuknya cukup besar dan pilihan yang sedang
       berlaku terbaca sekilas saat guru memeriksa ulang butirnya. */
    .pil-kunci-bsk {
        display:inline-flex; align-items:center; gap:.4rem;
        min-height:2.4rem; margin:0; padding:.35rem .7rem;
        border:1px solid var(--bs-border-color); border-radius:2rem;
        font-size:.85rem; font-weight:500; color:var(--bs-secondary-color);
        cursor:pointer; user-select:none; white-space:nowrap;
        transition:border-color .12s, background-color .12s, color .12s;
    }
    .pil-kunci-bsk:hover { border-color:var(--bs-primary-border-subtle); }
    .pil-kunci-bsk:has(input:checked) {
        border-color:var(--bs-primary); background:var(--bs-primary-bg-subtle); color:var(--bs-primary);
    }
    .pil-kunci-bsk .form-check-input { flex:none; }
</style>
@endpush

@push('scripts')
<script>
    // Menampilkan hanya bagian form yang relevan dengan jenis soal terpilih,
    // sekaligus mengatur perilaku kunci: PG hanya boleh satu, PG kompleks bebas.
    function jenisTerpilih() {
        return document.querySelector('input[name=jenis]:checked')?.value;
    }

    function gantiJenis() {
        const jenis = jenisTerpilih();

        document.querySelectorAll('.bagian-jenis').forEach(function (blok) {
            const cocok = blok.dataset.untuk.split(' ').includes(jenis);
            blok.style.display = cocok ? '' : 'none';
            // Kolom yang tersembunyi tidak boleh ikut tervalidasi browser.
            blok.querySelectorAll('input, textarea, select').forEach(el => el.disabled = !cocok);
        });

        const petunjuk = document.getElementById('petunjukKunci');
        if (petunjuk) {
            petunjuk.textContent = jenis === 'pg_kompleks'
                ? 'Centang semua opsi yang benar — pilihan ganda kompleks boleh punya lebih dari satu kunci.'
                : 'Centang tepat satu opsi sebagai kunci jawaban.';
        }

        aturModeKunci(jenis);
    }

    function aturModeKunci(jenis) {
        const kotak = document.querySelectorAll('.kunci-opsi');
        kotak.forEach(function (cb) {
            cb.onclick = function () {
                if (jenis === 'pg' && cb.checked) {
                    kotak.forEach(lain => { if (lain !== cb) lain.checked = false; });
                }
            };
        });
    }

    function hurufKe(i) { return String.fromCharCode(65 + i); }

    function nomoriUlangOpsi() {
        document.querySelectorAll('#daftarOpsi .baris-opsi').forEach(function (baris, i) {
            baris.querySelector('.huruf-opsi').textContent = hurufKe(i);
            baris.querySelector('.kunci-opsi').value = hurufKe(i);
        });
        aturModeKunci(jenisTerpilih());
    }

    function tambahOpsi() {
        const daftar = document.getElementById('daftarOpsi');
        if (daftar.children.length >= 8) return;

        const i = daftar.children.length;
        const baris = document.createElement('div');
        baris.className = 'input-group mb-2 baris-opsi';
        baris.innerHTML = `
            <span class="input-group-text huruf-opsi" style="width:2.75rem">${hurufKe(i)}</span>
            <div class="input-group-text">
                <input class="form-check-input mt-0 kunci-opsi" type="checkbox" name="kunci_pg[]" value="${hurufKe(i)}">
            </div>
            <input name="opsi_text[]" class="form-control" placeholder="Teks opsi" dir="auto" data-sisip-target data-label-sisip="Opsi ${hurufKe(i)}">
            <button type="button" class="btn btn-outline-danger" onclick="hapusOpsi(this)"><i class="bi bi-x-lg"></i></button>`;
        daftar.appendChild(baris);
        window.EditorSoal?.pasangSemua(baris);
        nomoriUlangOpsi();
    }

    function hapusOpsi(tombol) {
        const daftar = document.getElementById('daftarOpsi');
        if (daftar.children.length <= 2) {
            alert('Pilihan ganda minimal punya 2 opsi.');
            return;
        }
        tombol.closest('.baris-opsi').remove();
        nomoriUlangOpsi();
    }

    /*
     * Pernyataan benar/salah kompleks dinomori ulang tiap kali barisnya
     * berubah. Nomornya bukan hiasan: ia juga indeks yang memasangkan kolom
     * pernyataan dengan kolom kuncinya saat formulir dikirim, sehingga
     * menghapus baris di tengah tidak menggeser kunci baris di bawahnya.
     *
     * Radionya dibungkus <label> sendiri, jadi tidak perlu pasangan id/for
     * yang ikut dinomori ulang — satu sumber kekeliruan lebih sedikit.
     */
    function nomoriUlangBsk() {
        document.querySelectorAll('#daftarPernyataanBsk .baris-bsk').forEach(function (baris, i) {
            baris.querySelector('.nomor-bsk').textContent = i + 1;
            baris.querySelector('input[name^="bsk_pernyataan"]').name = `bsk_pernyataan[${i}]`;
            baris.querySelectorAll('.kunci-bsk').forEach(function (radio) {
                radio.name = `bsk_kunci[${i}]`;
            });
        });
    }

    function tambahPernyataanBsk() {
        const daftar = document.getElementById('daftarPernyataanBsk');
        if (daftar.children.length >= 10) return;

        const i = daftar.children.length;
        const baris = document.createElement('div');
        baris.className = 'baris-bsk row g-2 align-items-center mb-2';
        baris.innerHTML = `
            <div class="col-12 col-sm">
                <div class="input-group">
                    <span class="input-group-text nomor-bsk" style="width:2.5rem">${i + 1}</span>
                    <input name="bsk_pernyataan[${i}]" class="form-control"
                           placeholder="Pernyataan yang dinilai benar atau salah"
                           dir="auto" data-sisip-target data-label-sisip="Pernyataan ${i + 1}">
                </div>
            </div>
            <div class="col-12 col-sm-auto d-flex gap-2 align-items-center justify-content-end">
                <label class="pil-kunci-bsk">
                    <input class="form-check-input mt-0 kunci-bsk" type="radio" name="bsk_kunci[${i}]" value="benar" checked>
                    <span>Benar</span>
                </label>
                <label class="pil-kunci-bsk">
                    <input class="form-check-input mt-0 kunci-bsk" type="radio" name="bsk_kunci[${i}]" value="salah">
                    <span>Salah</span>
                </label>
                <button type="button" class="btn btn-outline-danger" onclick="hapusPernyataanBsk(this)"
                        title="Hapus pernyataan">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>`;
        daftar.appendChild(baris);
        window.EditorSoal?.pasangSemua(baris);
        nomoriUlangBsk();
    }

    function hapusPernyataanBsk(tombol) {
        const daftar = document.getElementById('daftarPernyataanBsk');
        if (daftar.children.length <= 2) {
            alert('Soal benar/salah kompleks minimal punya 2 pernyataan.');
            return;
        }
        tombol.closest('.baris-bsk').remove();
        nomoriUlangBsk();
    }

    function nomoriUlangJodoh() {
        document.querySelectorAll('#daftarJodoh .baris-jodoh').forEach(function (baris, i) {
            baris.querySelector('.nomor-jodoh').textContent = i + 1;
            baris.querySelector('.huruf-jodoh').textContent = hurufKe(i);
        });
    }

    function tambahJodoh() {
        const daftar = document.getElementById('daftarJodoh');
        if (daftar.children.length >= 10) return;

        const i = daftar.children.length;
        const baris = document.createElement('div');
        baris.className = 'row g-2 mb-2 baris-jodoh';
        baris.innerHTML = `
            <div class="col-5">
                <div class="input-group">
                    <span class="input-group-text nomor-jodoh" style="width:2.5rem">${i + 1}</span>
                    <input name="jodoh_kiri[]" class="form-control" placeholder="Pernyataan" dir="auto" data-sisip-target data-label-sisip="Pernyataan ${i + 1}">
                </div>
            </div>
            <div class="col-auto d-flex align-items-center text-muted"><i class="bi bi-arrow-right"></i></div>
            <div class="col">
                <div class="input-group">
                    <span class="input-group-text huruf-jodoh" style="width:2.5rem">${hurufKe(i)}</span>
                    <input name="jodoh_kanan[]" class="form-control" placeholder="Pasangan" dir="auto" data-sisip-target data-label-sisip="Pasangan ${hurufKe(i)}">
                    <button type="button" class="btn btn-outline-danger" onclick="hapusJodoh(this)"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>`;
        daftar.appendChild(baris);
        window.EditorSoal?.pasangSemua(baris);
        nomoriUlangJodoh();
    }

    function hapusJodoh(tombol) {
        const daftar = document.getElementById('daftarJodoh');
        if (daftar.children.length <= 2) {
            alert('Soal penjodohan minimal punya 2 pasangan.');
            return;
        }
        tombol.closest('.baris-jodoh').remove();
        nomoriUlangJodoh();
    }

    gantiJenis();

</script>
@endpush
@endsection
