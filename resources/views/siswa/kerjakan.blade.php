@extends('layouts.siswa')
@section('title', 'Mengerjakan Ujian')
@section('sembunyikan-keluar', '1')

@section('navbar')
    @php
        $kelasPeserta = $peserta->siswa?->rombelPada();
    @endphp

    {{-- Identitas peserta di kiri, sisa waktu menempel di pojok kanan.
         Keduanya sejajar dalam satu baris supaya tetap muat di layar ponsel,
         tempat sebagian besar siswa mengerjakan lewat ExamBro. --}}
    <div class="d-flex align-items-center gap-2 flex-grow-1 justify-content-between" style="min-width:0">
        <div class="peserta-bilah d-flex flex-column text-truncate">
            <span class="nama text-truncate">{{ $peserta->siswa?->nama_siswa ?? \App\Support\Pengguna::nama() }}</span>
            {{-- Kelas selalu tampil; nama ujian menyusul hanya bila layarnya
                 cukup lebar. Di ponsel, nama ujian yang panjang akan menyita
                 ruang kelas sampai keduanya sama-sama terpotong. --}}
            <span class="rincian text-truncate">
                @if ($kelasPeserta)Kelas {{ $kelasPeserta->nama_rombel }}@endif
                <span class="d-none d-sm-inline">@if ($kelasPeserta) &middot; @endif{{ $peserta->ujian->nama_ujian }}</span>
            </span>
        </div>

        <div class="d-flex align-items-center gap-2 flex-shrink-0">
            <span class="jam-ujian" id="penghitungWaktu">--:--:--</span>

            {{-- Di ExamBro tidak ada bilah alamat. Tanpa tombol ini, satu-satunya
                 cara memuat ulang lembar yang tampilannya tersendat adalah keluar
                 dari peramban ujian — yang justru terhitung pelanggaran. --}}
            <button type="button" class="tombol-muat-ulang" id="tombolMuatUlang"
                    title="Muat ulang lembar ujian">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>
@endsection

@section('content')
@php use App\Models\Soal; use App\Support\TeksSoal; @endphp

<div class="row g-3">

    {{-- ---------- Lembar soal ---------- --}}
    <div class="col-lg-8">
        @foreach ($jawaban as $i => $j)
            @php
                $soal = $j->soal;
                // Urutan opsi dikunci saat peserta memulai; bila ada, opsi
                // ditampilkan menurut urutan itu agar konsisten tiap refresh.
                $opsi = collect($soal->opsiPilihan());
                if ($j->urutan_opsi && in_array($soal->jenis, [Soal::PG, Soal::PG_KOMPLEKS], true)) {
                    $peta = $opsi->keyBy('key');
                    $opsi = collect($j->urutan_opsi)->map(fn ($k) => $peta[$k] ?? null)->filter()->values();
                }
                $kanan = collect($soal->opsiKanan());
                if ($j->urutan_opsi && $soal->jenis === Soal::PENJODOHAN) {
                    $petaKanan = $kanan->keyBy('key');
                    $kanan = collect($j->urutan_opsi)->map(fn ($k) => $petaKanan[$k] ?? null)->filter()->values();
                }
                $terpilih = array_map('strval', (array) ($j->jawaban ?? []));
            @endphp

            <div class="card mb-3 kartu-soal" data-nomor="{{ $j->nomor_urut }}" data-soal="{{ $soal->id }}"
                 style="{{ $i === 0 ? '' : 'display:none' }}">

                <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <span class="d-flex align-items-center gap-2">
                        <span class="lencana-nomor">{{ $j->nomor_urut }}</span>
                        <span class="small text-muted">dari {{ $jawaban->count() }} soal</span>
                    </span>

                    <span class="d-flex align-items-center gap-3">
                        <x-jenis-soal :jenis="$soal->jenis" />
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input tandai-ragu" type="checkbox" id="ragu-{{ $j->id }}"
                                   data-soal="{{ $soal->id }}" @checked($j->ragu)>
                            <label class="form-check-label small" for="ragu-{{ $j->id }}">
                                <i class="bi bi-flag me-1"></i>Ragu-ragu
                            </label>
                        </div>
                    </span>
                </div>

                <div class="card-body">
                    <div class="soal-body mb-4" dir="auto">{!! TeksSoal::html($soal->pertanyaan) !!}</div>

                    @switch ($soal->jenis)

                        {{-- ----- Pilihan ganda ----- --}}
                        @case (Soal::PG)
                            @foreach ($opsi as $o)
                                <label class="d-flex gap-3 align-items-center mb-2 opsi-label">
                                    <input class="form-check-input mt-0 flex-shrink-0" type="radio"
                                           name="soal-{{ $soal->id }}" value="{{ $o['key'] }}"
                                           data-soal="{{ $soal->id }}" data-mode="tunggal"
                                           @checked(in_array((string) $o['key'], $terpilih, true))>
                                    <span class="opsi-huruf">{{ strtoupper($o['key']) }}</span>
                                    <span class="opsi-teks flex-grow-1" dir="auto">{!! TeksSoal::html($o['text']) !!}</span>
                                </label>
                            @endforeach
                            @break

                        {{-- ----- Benar / salah ----- --}}
                        @case (Soal::BENAR_SALAH)
                            <div class="row g-2" style="max-width:30rem">
                                @foreach ([['benar', 'Benar', 'bi-check-lg'], ['salah', 'Salah', 'bi-x-lg']] as [$nilai, $label, $ikon])
                                    <div class="col-6">
                                        <label class="opsi-label d-flex flex-column align-items-center gap-2 py-4 text-center">
                                            <input class="form-check-input" type="radio"
                                                   name="soal-{{ $soal->id }}" value="{{ $nilai }}"
                                                   data-soal="{{ $soal->id }}" data-mode="tunggal"
                                                   @checked(in_array($nilai, $terpilih, true))>
                                            <i class="bi {{ $ikon }} fs-4"></i>
                                            <span class="fw-semibold">{{ $label }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            @break

                        {{-- ----- Pilihan ganda kompleks ----- --}}
                        @case (Soal::PG_KOMPLEKS)
                            <div class="petunjuk-soal mb-3">
                                <i class="bi bi-check2-square me-1"></i>Jawaban benar boleh lebih dari satu — centang semua yang tepat.
                            </div>
                            @foreach ($opsi as $o)
                                <label class="d-flex gap-3 align-items-center mb-2 opsi-label">
                                    <input class="form-check-input mt-0 flex-shrink-0" type="checkbox"
                                           name="soal-{{ $soal->id }}[]" value="{{ $o['key'] }}"
                                           data-soal="{{ $soal->id }}" data-mode="ganda"
                                           @checked(in_array((string) $o['key'], $terpilih, true))>
                                    <span class="opsi-huruf">{{ strtoupper($o['key']) }}</span>
                                    <span class="opsi-teks flex-grow-1" dir="auto">{!! TeksSoal::html($o['text']) !!}</span>
                                </label>
                            @endforeach
                            @break

                        {{-- ----- Penjodohan ----- --}}
                        @case (Soal::PENJODOHAN)
                            <div class="petunjuk-soal mb-3">
                                <i class="bi bi-arrow-left-right me-1"></i>Pilih huruf pasangan yang tepat untuk tiap pernyataan.
                            </div>

                            <div class="row g-3">
                                <div class="col-md-7">
                                    @foreach ($soal->opsiPilihan() as $kiri)
                                        @php $nilaiTerpilih = (array) ($j->jawaban ?? []); @endphp
                                        <div class="baris-jodoh d-flex gap-2 align-items-center mb-2">
                                            <span class="opsi-huruf">{{ $kiri['key'] }}</span>
                                            <span class="flex-grow-1 opsi-teks" dir="auto">{!! TeksSoal::html($kiri['text']) !!}</span>
                                            <select class="form-select form-select-sm jodoh-pilih" style="width:4.75rem"
                                                    data-soal="{{ $soal->id }}" data-kiri="{{ $kiri['key'] }}">
                                                <option value="">—</option>
                                                @foreach ($kanan as $kn)
                                                    <option value="{{ $kn['key'] }}"
                                                            @selected(($nilaiTerpilih[$kiri['key']] ?? null) === $kn['key'])>
                                                        {{ $kn['key'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="col-md-5">
                                    <div class="daftar-pasangan">
                                        <div class="judul">Pilihan pasangan</div>
                                        @foreach ($kanan as $kn)
                                            <div class="d-flex gap-2 align-items-start mb-2">
                                                <span class="opsi-huruf">{{ $kn['key'] }}</span>
                                                <span class="opsi-teks" dir="auto">{!! TeksSoal::html($kn['text']) !!}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            @break

                        {{-- ----- Essay ----- --}}
                        @case (Soal::ESSAY)
                            <textarea class="form-control essay-jawab" rows="9" data-soal="{{ $soal->id }}"
                                      placeholder="Tulis jawaban Anda di sini">{{ $j->teks_essay }}</textarea>
                            <div class="form-text">
                                <i class="bi bi-cloud-check me-1"></i>Jawaban tersimpan otomatis beberapa saat setelah Anda berhenti mengetik.
                            </div>
                            @break
                    @endswitch
                </div>

                <div class="card-body border-top d-flex justify-content-between gap-2">
                    <button class="btn btn-outline-secondary" onclick="keSoal({{ $j->nomor_urut - 1 }})"
                            @disabled($j->nomor_urut <= 1)>
                        <i class="bi bi-chevron-left me-1"></i>Sebelumnya
                    </button>
                    @if ($j->nomor_urut < $jawaban->count())
                        <button class="btn btn-primary" onclick="keSoal({{ $j->nomor_urut + 1 }})">
                            Berikutnya<i class="bi bi-chevron-right ms-1"></i>
                        </button>
                    @else
                        <button class="btn btn-ujian" data-bs-toggle="modal" data-bs-target="#modalSelesai">
                            <i class="bi bi-check2-circle me-1"></i>Selesai &amp; Kumpulkan
                        </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- ---------- Panel navigasi ----------
         Diletakkan sesudah lembar soal pada layar sempit: di ponsel, panel di
         atas memaksa siswa menggulir melewati puluhan tombol nomor sebelum
         sampai ke soalnya sendiri. --}}
    <div class="col-lg-4">
        <div class="card sticky-lg-top" style="top:4.75rem">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-grid-3x3-gap me-1 text-muted"></i>Navigasi Soal</span>
                <span class="jam-panel" id="penghitungWaktuPanel">--:--:--</span>
            </div>

            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3" id="navSoal">
                    @foreach ($jawaban as $j)
                        <button class="btn btn-sm btn-outline-secondary nav-nomor position-relative"
                                data-nomor="{{ $j->nomor_urut }}"
                                data-soal="{{ $j->soal_id }}" onclick="keSoal({{ $j->nomor_urut }})">
                            {{ $j->nomor_urut }}
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning tanda-ragu"
                                  style="{{ $j->ragu ? '' : 'display:none' }};font-size:.5rem">&nbsp;</span>
                        </button>
                    @endforeach
                </div>

                <div class="keterangan-warna mb-3">
                    <span><i class="kotak kotak-biru"></i>sudah dijawab</span>
                    <span><i class="kotak kotak-putih"></i>belum</span>
                    <span><i class="kotak kotak-kuning"></i>ragu-ragu</span>
                </div>

                <div class="d-flex justify-content-between align-items-baseline mb-1">
                    <span class="small text-muted">Kemajuan</span>
                    <span class="small fw-semibold">
                        <span id="jumlahTerjawab">0</span><span class="text-muted">/{{ $jawaban->count() }}</span>
                    </span>
                </div>
                <div class="progress mb-3" style="height:.5rem">
                    <div class="progress-bar bg-success" id="barKemajuan" style="width:0%"></div>
                </div>

                <div class="status-simpan small text-muted" id="statusSimpan">
                    <i class="bi bi-cloud-check me-1"></i>Jawaban tersimpan otomatis
                </div>

                <button class="btn btn-ujian w-100 py-2 mt-3" data-bs-toggle="modal" data-bs-target="#modalSelesai">
                    <i class="bi bi-check2-circle me-1"></i>Selesai &amp; Kumpulkan
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ---------- Modal konfirmasi kumpul ---------- --}}
<div class="modal fade" id="modalSelesai" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius:.9rem">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Kumpulkan lembar jawaban?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Setelah dikumpulkan, Anda <strong>tidak bisa mengubah jawaban lagi</strong>.</p>
                <div class="kotak-kosong" id="peringatanKosong" style="display:none">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>Masih ada <strong><span id="jumlahKosong">0</span> soal</strong> yang belum Anda jawab.</div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Periksa lagi</button>
                <form method="POST" action="{{ route('siswa.ujian.selesai', $peserta) }}">
                    @csrf
                    <button class="btn btn-ujian"><i class="bi bi-check2-circle me-1"></i>Ya, Kumpulkan</button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Tirai pengawasan: tab baru, layar terbelah, dan jendela mengambang. --}}
@if ($pengawasan['aktif'])
    @include('partials.pengawasan-ujian')
@endif
@endsection

@push('head')
<style>
    /* Sasaran sentuhnya dijaga 2,4rem: yang menekan adalah jari di layar
       ponsel, dan tombol ini bertetangga dengan sisa waktu yang tidak boleh
       ikut tertekan. */
    .tombol-muat-ulang {
        width:2.4rem; height:2.4rem; flex:none;
        display:grid; place-items:center;
        border-radius:.6rem; font-size:1.05rem;
        color:#fff; background:rgba(255,255,255,.14);
        border:1px solid rgba(255,255,255,.3);
        cursor:pointer; transition:background-color .12s, border-color .12s;
    }
    .tombol-muat-ulang:hover  { background:rgba(255,255,255,.26); border-color:rgba(255,255,255,.55); }
    .tombol-muat-ulang:active { background:rgba(255,255,255,.34); }
    .tombol-muat-ulang:disabled { opacity:.7; cursor:default; }
    .tombol-muat-ulang.berputar .bi { animation:putar-muat-ulang .7s linear infinite; }

    @keyframes putar-muat-ulang { to { transform:rotate(360deg); } }

    .lencana-nomor {
        width:2rem; height:2rem; border-radius:.5rem;
        display:grid; place-items:center; font-weight:700; font-size:.9rem;
        background:var(--biru-tua); color:#fff;
    }

    .petunjuk-soal {
        background:#e8f1fb; border:1px solid #cfe0f4; color:#1b3a63;
        border-radius:.6rem; padding:.55rem .85rem; font-size:.85rem;
    }

    .baris-jodoh {
        border:1.5px solid var(--garis); border-radius:.7rem;
        padding:.6rem .75rem; background:var(--kartu);
    }
    .daftar-pasangan {
        border:1px dashed #c9d6e6; border-radius:.7rem;
        padding:.9rem; background:#f8fbff; height:100%;
    }
    .daftar-pasangan .judul {
        font-size:.75rem; font-weight:600; text-transform:uppercase;
        letter-spacing:.05em; color:var(--teks-lembut); margin-bottom:.6rem;
    }

    .essay-jawab { border-radius:.7rem; line-height:1.7; padding:.9rem 1rem; }
    .essay-jawab:focus { border-color:var(--hijau); box-shadow:0 0 0 .25rem rgba(0,168,107,.15); }

    .jam-panel {
        font-family:ui-monospace,SFMono-Regular,Consolas,monospace;
        font-weight:700; font-size:.9rem;
        padding:.2rem .55rem; border-radius:.45rem;
        background:#eef2f7; color:var(--teks);
    }

    .keterangan-warna {
        display:flex; flex-wrap:wrap; gap:.35rem .9rem;
        font-size:.76rem; color:var(--teks-lembut);
    }
    .keterangan-warna .kotak {
        width:.75rem; height:.75rem; border-radius:.25rem;
        display:inline-block; margin-right:.3rem; vertical-align:-1px;
    }
    .kotak-biru { background:#0d6efd; }
    .kotak-putih { background:#fff; border:1.5px solid #adb5bd; }
    .kotak-kuning { background:var(--kuning); }

    .status-simpan {
        padding:.5rem .7rem; border-radius:.5rem; background:#f6f8fb;
        border:1px solid var(--garis);
    }

    .kotak-kosong {
        display:flex; gap:.6rem; align-items:flex-start;
        background:#fffaf0; border:1px solid #ffe1a8; color:#8a5a00;
        border-radius:.6rem; padding:.75rem .9rem; font-size:.87rem;
    }
</style>
@endpush

@push('scripts')
<script>
    /*
     * Halaman ini dibuka lewat ExamBro, yang memakai WebView Android bawaan
     * perangkat — pada perangkat sekolah kerap jauh lebih tua dari peramban
     * di meja guru. Karena itu blok ini sengaja tidak memakai sintaks yang
     * lebih baru dari ES2017: operator ?. dan ?? akan menjadi galat sintaks
     * di sana, dan satu galat sintaks membatalkan seluruh skrip — hitung
     * mundur mati, jawaban tidak tersimpan, tombol kumpulkan diam.
     */
    const urlSimpan  = @json(route('siswa.ujian.simpan', $peserta));
    const urlKeluar  = @json(route('siswa.ujian.keluar', $peserta));
    const urlSelesai = @json(route('siswa.ujian.selesai', $peserta));
    const urlDenyut  = @json(route('siswa.ujian.pengawasan', $peserta));
    const csrf       = document.querySelector('meta[name=csrf-token]').content;
    const totalSoal  = @json($jawaban->count());

    let sisaDetik = @json($sisaDetik);
    // Soal yang sudah terisi menurut server saat halaman dimuat.
    let sudahDijawab = new Set(@json($jawaban->filter(fn ($j) => $j->terisi)->pluck('soal_id')->values()));

    // ---------------------------------------------------------------- waktu
    function formatWaktu(detik) {
        const j = Math.floor(detik / 3600), m = Math.floor((detik % 3600) / 60), d = detik % 60;
        return [j, m, d].map(v => String(v).padStart(2, '0')).join(':');
    }

    function tickWaktu() {
        sisaDetik = Math.max(0, sisaDetik - 1);
        const teks = formatWaktu(sisaDetik);
        const jam = document.getElementById('penghitungWaktu');
        jam.textContent = teks;
        document.getElementById('penghitungWaktuPanel').textContent = teks;

        // Dua tahap peringatan: kuning pada sepuluh menit terakhir, merah
        // berdenyut pada lima menit terakhir. Satu tahap saja membuat
        // peringatannya datang terlalu mendadak.
        if (sisaDetik <= 600) jam.classList.add('waktu-hampir');
        if (sisaDetik <= 300) {
            jam.classList.remove('waktu-hampir');
            jam.classList.add('text-bg-danger', 'text-white');
        }

        if (sisaDetik === 0) {
            alert('Waktu ujian habis. Lembar jawaban Anda dikumpulkan otomatis.');
            kumpulkanOtomatis();
        }
    }

    function kumpulkanOtomatis() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = urlSelesai;
        form.innerHTML = `<input type="hidden" name="_token" value="${csrf}">`;
        document.body.appendChild(form);
        form.submit();
    }

    tickWaktu();
    sisaDetik += 1;            // tickWaktu pertama sudah mengurangi satu detik
    setInterval(tickWaktu, 1000);

    // ------------------------------------------------------------ navigasi
    function keSoal(nomor) {
        document.querySelectorAll('.kartu-soal').forEach(function (kartu) {
            kartu.style.display = Number(kartu.dataset.nomor) === nomor ? '' : 'none';
        });
        document.querySelectorAll('.nav-nomor').forEach(function (tombol) {
            tombol.classList.toggle('border-3', Number(tombol.dataset.nomor) === nomor);
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function perbaruiNavigasi() {
        document.querySelectorAll('.nav-nomor').forEach(function (tombol) {
            const dijawab = sudahDijawab.has(Number(tombol.dataset.soal));
            tombol.classList.toggle('btn-primary', dijawab);
            tombol.classList.toggle('btn-outline-secondary', !dijawab);
        });

        const jumlah = sudahDijawab.size;
        document.getElementById('jumlahTerjawab').textContent = jumlah;
        document.getElementById('barKemajuan').style.width = (jumlah / totalSoal * 100) + '%';
        document.getElementById('jumlahKosong').textContent = totalSoal - jumlah;
        document.getElementById('peringatanKosong').style.display = jumlah < totalSoal ? '' : 'none';
    }

    // -------------------------------------------------------------- simpan
    function statusSimpan(pesan, ikon, kelas) {
        // Kelas dasarnya ikut ditulis ulang di sini: tanpa itu, gaya kotak
        // status hilang pada penyimpanan pertama.
        document.getElementById('statusSimpan').className = 'status-simpan small ' + kelas;
        document.getElementById('statusSimpan').innerHTML = `<i class="bi ${ikon} me-1"></i>${pesan}`;
    }

    async function simpan(soalId, jawaban, ragu) {
        statusSimpan('Menyimpan…', 'bi-cloud-arrow-up', 'text-muted');

        const body = new FormData();
        body.append('_token', csrf);
        body.append('soal_id', soalId);
        body.append('ragu', ragu ? 1 : 0);

        if (Array.isArray(jawaban)) {
            jawaban.forEach(v => body.append('jawaban[]', v));
        } else if (jawaban !== null && typeof jawaban === 'object') {
            Object.entries(jawaban).forEach(([k, v]) => body.append(`jawaban[${k}]`, v));
        } else if (jawaban !== null && jawaban !== '') {
            body.append('jawaban', jawaban);
        }

        try {
            const respons = await fetch(urlSimpan, { method: 'POST', body, headers: { 'Accept': 'application/json' } });
            const data = await respons.json();

            if (!respons.ok) {
                statusSimpan(data.pesan || 'Gagal menyimpan', 'bi-exclamation-triangle', 'text-danger');
                if (respons.status === 409) { kumpulkanOtomatis(); }

                // 401 — akun ini masuk dari perangkat lain; sesi di sini sudah
                // diakhiri server. Halaman login menampilkan alasannya.
                if (respons.status === 401 && data.sesi_berakhir) { sesiBerakhir(data); }

                // 423 — lembar dikunci pengawasan. Halaman dimuat ulang supaya
                // tirai kuncinya muncul walau skrip pengawasan sempat dimatikan.
                if (respons.status === 423) { window.location.reload(); }
                return;
            }

            sisaDetik = data.sisa_detik;
            sudahDijawab = new Set(data.soal_terjawab);
            perbaruiNavigasi();
            statusSimpan('Tersimpan ' + new Date().toLocaleTimeString('id-ID'), 'bi-cloud-check', 'text-success');
        } catch (e) {
            // Jawaban tetap ada di layar; siswa diberi tahu agar mencoba lagi.
            statusSimpan('Gagal menyimpan — periksa koneksi', 'bi-wifi-off', 'text-danger');
        }
    }

    function ragudariSoal(soalId) {
        const kotak = document.querySelector(`.tandai-ragu[data-soal="${soalId}"]`);

        return kotak ? kotak.checked : false;
    }

    /**
     * Tandai baris opsi yang sedang terpilih.
     *
     * Penandaannya tidak diserahkan sepenuhnya kepada CSS :has(), yang baru
     * dikenal peramban terbitan 2022 ke atas. Komputer sekolah kerap memakai
     * yang lebih lama, dan opsi yang tidak terlihat terpilih berarti keraguan
     * di tengah ujian — mahal untuk hal sesepele gaya.
     */
    function segarkanPilihan() {
        document.querySelectorAll('.opsi-label').forEach(function (label) {
            label.classList.toggle('opsi-terpilih', !!label.querySelector('input:checked'));
        });
    }

    // Pilihan ganda, PG kompleks, benar/salah
    document.querySelectorAll('input[data-mode]').forEach(function (input) {
        input.addEventListener('change', function () {
            const soalId = input.dataset.soal;

            segarkanPilihan();

            const nilai = input.dataset.mode === 'ganda'
                ? Array.from(document.querySelectorAll(`input[data-soal="${soalId}"][data-mode="ganda"]:checked`)).map(el => el.value)
                : input.value;

            simpan(soalId, nilai, ragudariSoal(soalId));
        });
    });

    // Penjodohan
    document.querySelectorAll('.jodoh-pilih').forEach(function (select) {
        select.addEventListener('change', function () {
            const soalId = select.dataset.soal;
            const pasangan = {};
            document.querySelectorAll(`.jodoh-pilih[data-soal="${soalId}"]`).forEach(function (s) {
                if (s.value) pasangan[s.dataset.kiri] = s.value;
            });
            simpan(soalId, pasangan, ragudariSoal(soalId));
        });
    });

    // Essay — disimpan sesaat setelah siswa berhenti mengetik, bukan tiap ketukan.
    // Selama penundaan itu berlangsung, jawabannya baru ada di layar dan belum
    // sampai ke server; daftar berikut menandai mana yang masih menunggu.
    const essayTertunda = new Set();

    document.querySelectorAll('.essay-jawab').forEach(function (area) {
        let jeda;

        area.addEventListener('input', function () {
            essayTertunda.add(area);
            clearTimeout(jeda);

            jeda = setTimeout(function () {
                essayTertunda.delete(area);
                simpan(area.dataset.soal, { teks: area.value }, ragudariSoal(area.dataset.soal));
            }, 1200);
        });

        area.addEventListener('blur', function () {
            clearTimeout(jeda);
            essayTertunda.delete(area);
            simpan(area.dataset.soal, { teks: area.value }, ragudariSoal(area.dataset.soal));
        });
    });

    /**
     * Kirim jawaban essay yang masih tertunda sebelum halaman ditinggalkan.
     *
     * Dipakai sendBeacon, bukan fetch: saat halaman sedang ditutup atau dimuat
     * ulang, peramban membatalkan permintaan fetch yang belum selesai,
     * sedangkan sendBeacon dijamin tetap terkirim.
     *
     * Inilah yang membuat halaman ini aman disegarkan tanpa peringatan apa pun.
     */
    function kirimEssayTertunda() {
        if (! navigator.sendBeacon || essayTertunda.size === 0) return;

        essayTertunda.forEach(function (area) {
            const data = new URLSearchParams();
            data.append('_token', csrf);
            data.append('soal_id', area.dataset.soal);
            data.append('ragu', ragudariSoal(area.dataset.soal) ? 1 : 0);
            data.append('jawaban[teks]', area.value);

            navigator.sendBeacon(urlSimpan, data);
        });

        essayTertunda.clear();
    }

    // Tanda ragu-ragu
    document.querySelectorAll('.tandai-ragu').forEach(function (cb) {
        cb.addEventListener('change', function () {
            const soalId = cb.dataset.soal;
            const tanda = document.querySelector(`.nav-nomor[data-soal="${soalId}"] .tanda-ragu`);
            if (tanda) tanda.style.display = cb.checked ? '' : 'none';

            // Kirim ulang jawaban yang sekarang supaya tanda ragu ikut tersimpan.
            const gandaTerpilih = Array.from(document.querySelectorAll(`input[data-soal="${soalId}"][data-mode="ganda"]:checked`)).map(el => el.value);
            const tunggal = document.querySelector(`input[data-soal="${soalId}"][data-mode="tunggal"]:checked`);
            const tunggalTerpilih = tunggal ? tunggal.value : null;
            const essay = document.querySelector(`.essay-jawab[data-soal="${soalId}"]`);
            const jodoh = document.querySelectorAll(`.jodoh-pilih[data-soal="${soalId}"]`);

            let nilai = null;
            if (essay) {
                nilai = { teks: essay.value };
            } else if (jodoh.length) {
                nilai = {};
                jodoh.forEach(s => { if (s.value) nilai[s.dataset.kiri] = s.value; });
            } else if (gandaTerpilih.length) {
                nilai = gandaTerpilih;
            } else {
                nilai = tunggalTerpilih;
            }

            simpan(soalId, nilai, cb.checked);
        });
    });

    // Catat bila peserta berpindah tab/jendela selama ujian.
    document.addEventListener('visibilitychange', function () {
        if (! document.hidden) return;

        kirimEssayTertunda();

        // Saat proteksi kecurangan menyala, kepergian ini sudah dicatat
        // lapisan pengawasan sebagai pelanggaran berikut hitungannya.
        // Mencatatnya lagi di sini hanya menggandakan barisnya di layar
        // pengawas dengan nama kejadian yang berbeda.
        if (window.PengawasanUjian && window.PengawasanUjian.aktif) return;

        if (navigator.sendBeacon) {
            navigator.sendBeacon(urlKeluar, new URLSearchParams({ _token: csrf, keterangan: 'tab disembunyikan' }));
        }
    });

    // Halaman ditutup, disegarkan, atau ditinggalkan.
    window.addEventListener('pagehide', kirimEssayTertunda);


    /*
     * Muat ulang lembar ujian.
     *
     * Jawaban essay yang masih menunggu jeda ketik dikirim lebih dulu, supaya
     * menekan tombol ini tidak pernah menghilangkan ketikan yang belum sempat
     * tersimpan. Selebihnya aman: seluruh jawaban tersimpan di server, dan
     * memuat ulang tidak terhitung pelanggaran.
     */
    const tombolMuatUlang = document.getElementById('tombolMuatUlang');

    if (tombolMuatUlang) {
        tombolMuatUlang.addEventListener('click', function () {
            tombolMuatUlang.disabled = true;
            tombolMuatUlang.classList.add('berputar');

            kirimEssayTertunda();
            window.location.reload();
        });
    }

    /*
     * Satu akun, satu perangkat.
     *
     * Bila akun ini masuk dari perangkat lain, sesi di sini diakhiri server —
     * tetapi halamannya tidak tahu sampai ia meminta sesuatu. Tanpa denyut ini,
     * perangkat yang sudah dikalahkan tetap menampilkan soal selama siswanya
     * tidak menyentuh apa pun, dan soal itulah yang ingin dilindungi.
     */
    function sesiBerakhir(data) {
        document.body.innerHTML = '';
        window.location.href = data.alihkan;
    }

    setInterval(function () {
        if (document.hidden) return;

        fetch(urlDenyut, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (res) {
                if (res.status !== 401) return;
                return res.json().then(function (data) {
                    if (data && data.sesi_berakhir) sesiBerakhir(data);
                });
            })
            .catch(function () {});
    }, 15000);

    segarkanPilihan();
    perbaruiNavigasi();
    keSoal(1);
</script>
@endpush
