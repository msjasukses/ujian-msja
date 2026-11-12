@extends('layouts.app')
@section('title', $item->exists ? 'Ubah Registrasi Ujian' : 'Registrasi Ujian')

@section('content')
@php use App\Models\Ujian; use App\Support\Pengguna; use App\Support\Referensi; @endphp

@php
    $rombel = Referensi::rombel(Pengguna::guruId());

    // Semua kelas moving yang mungkin dipakai paket-paket ini; yang tampil
    // disaring JS mengikuti mapel paket yang sedang dipilih.
    $semuaKelasMoving = $daftarPaket
        ->pluck('mata_pelajaran_id')
        ->filter()
        ->unique()
        ->flatMap(fn ($mapelId) => Referensi::kelasMoving($mapelId))
        ->unique('id')
        ->sortBy('nama_kelas_moving')
        ->values();

    // Peta untuk mengerucutkan centang kelas mengikuti mapel paket soal yang
    // dipilih — pada kelas moving tidak semua rombel mengambil mapel sama.
    // "rombel" null berarti tidak bisa dikerucutkan; "alasan" menerangkan
    // sebabnya supaya guru tidak menduga aplikasinya yang keliru.
    $infoPaket = $daftarPaket->mapWithKeys(function ($p) {
        $alasan = Referensi::alasanKelasTidakMengerucut($p->mata_pelajaran_id);

        $moving = Referensi::kelasMoving($p->mata_pelajaran_id);

        return [$p->id => [
            'mapel' => $p->mataPelajaran->nama_mapel ?? null,
            'alasan' => $alasan,
            'moving' => $moving->map(fn ($km) => $km->nama_lengkap.' ('.$km->anggota->count().' siswa)')->values(),
            'movingIds' => $moving->pluck('id')->map('intval')->values(),
            'rombel' => $alasan
                ? null
                : Referensi::rombelMapel($p->mata_pelajaran_id, Pengguna::guruId())
                    ->pluck('id')->map('intval')->values(),
        ]];
    });
@endphp

<form method="POST" action="{{ $item->exists ? route('ujian.update', $item) : route('ujian.store') }}">
    @csrf
    @if ($item->exists) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header">Identitas Ujian</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Kode ujian <span class="text-danger">*</span></label>
                            <input name="kode_ujian" value="{{ old('kode_ujian', $item->kode_ujian) }}"
                                   class="form-control @error('kode_ujian') is-invalid @enderror" required>
                            @error('kode_ujian')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Nama ujian <span class="text-danger">*</span></label>
                            <input name="nama_ujian" value="{{ old('nama_ujian', $item->nama_ujian) }}"
                                   class="form-control @error('nama_ujian') is-invalid @enderror"
                                   placeholder="mis. PAS Ganjil Matematika Kelas X" required>
                            @error('nama_ujian')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label">Paket soal <span class="text-danger">*</span></label>
                            <select name="paket_soal_id" class="form-select @error('paket_soal_id') is-invalid @enderror" required>
                                <option value="">— pilih paket soal —</option>
                                @foreach ($daftarPaket as $p)
                                    <option value="{{ $p->id }}" @selected(old('paket_soal_id', $item->paket_soal_id) == $p->id)>
                                        {{ $p->nama_paket }} ({{ $p->kode_paket }}) — {{ $p->detail_count }} butir
                                    </option>
                                @endforeach
                            </select>
                            @error('paket_soal_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @if ($daftarPaket->isEmpty())
                                <div class="form-text text-danger">
                                    Belum ada paket soal aktif.
                                    <a href="{{ route('paket-soal.create') }}">Buat paket soal dulu</a>.
                                </div>
                            @else
                                <div class="form-text">Mata pelajaran ujian otomatis mengikuti mata pelajaran paket.</div>
                            @endif
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Tahun ajaran</label>
                            <x-pilih-tahun-ajaran :value="$item->tahun_ajaran" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Semester</label>
                            <select name="semester" class="form-select">
                                <option value="">—</option>
                                @foreach (Referensi::semester() as $nilai => $label)
                                    <option value="{{ $nilai }}" @selected(old('semester', $item->semester) === $nilai)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                @foreach (Ujian::STATUS as $nilai => $label)
                                    <option value="{{ $nilai }}" @selected(old('status', $item->status) === $nilai)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Siswa hanya bisa mengerjakan ujian berstatus <strong>Aktif</strong>.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Waktu Pelaksanaan</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Waktu mulai <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="waktu_mulai" required
                                   value="{{ old('waktu_mulai', $item->waktu_mulai?->format('Y-m-d\TH:i')) }}"
                                   class="form-control @error('waktu_mulai') is-invalid @enderror">
                            @error('waktu_mulai')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Waktu selesai <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="waktu_selesai" required
                                   value="{{ old('waktu_selesai', $item->waktu_selesai?->format('Y-m-d\TH:i')) }}"
                                   class="form-control @error('waktu_selesai') is-invalid @enderror">
                            @error('waktu_selesai')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Durasi pengerjaan (menit) <span class="text-danger">*</span></label>
                            <input type="number" name="durasi_menit" min="5" max="600" required
                                   value="{{ old('durasi_menit', $item->durasi_menit) }}" class="form-control">
                            <div class="form-text">Hitung mundur mulai saat siswa menekan "Mulai".</div>
                        </div>
                    </div>
                    <div class="alert alert-light border small mt-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Jendela waktu adalah rentang kapan ujian boleh dibuka; durasi adalah lama pengerjaan tiap siswa.
                        Pengerjaan berhenti pada yang lebih dulu tercapai di antara keduanya.
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Peserta Ujian <span class="text-danger">*</span></div>
                <div class="card-body">
                    @php $mode = old('mode_peserta', $item->mode_peserta ?: \App\Models\Ujian::MODE_KELAS); @endphp
                    <div class="mb-3">
                        @foreach (\App\Models\Ujian::MODE_PESERTA as $nilai => $label)
                            <div class="form-check">
                                <input class="form-check-input mode-peserta" type="radio" name="mode_peserta"
                                       value="{{ $nilai }}" id="mode-{{ $nilai }}" @checked($mode === $nilai)>
                                <label class="form-check-label" for="mode-{{ $nilai }}">{{ $label }}</label>
                            </div>
                        @endforeach
                        @error('mode_peserta')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        <div class="form-text" id="catatan-moving">
                            Kelas moving dipakai bila pesertanya campuran siswa dari beberapa kelas yang mengambil
                            mata pelajaran yang sama. Centang kelas movingnya di bawah ini, atau pilih siswanya
                            satu per satu di halaman Peserta; daftar kelas biasa boleh dikosongkan.
                        </div>
                        <div class="form-text text-success-emphasis d-none" id="catatan-kelas-moving-dc"></div>
                    </div>

                    {{-- Kelas moving dari Data Center: rombongan lintas rombel yang
                         mengambil mapel paket soal ini. Mencentangnya langsung
                         mendaftarkan seluruh anggotanya sebagai peserta. --}}
                    <div class="mb-3 d-none" id="blok-kelas-moving">
                        <div class="fw-semibold small mb-2">Kelas moving peserta</div>
                        <div class="row g-2">
                            @foreach ($semuaKelasMoving as $km)
                                <div class="col-12 col-md-6 col-xl-4 kolom-kelas-moving d-none"
                                     data-kelas-moving="{{ $km->id }}">
                                    <div class="form-check border rounded p-2 ps-4">
                                        <input class="form-check-input kelas-moving-cb" type="checkbox"
                                               name="kelas_moving_id[]" value="{{ $km->id }}"
                                               id="km-{{ $km->id }}"
                                               @checked(in_array($km->id, old('kelas_moving_id', $kelasMovingTerpilih)))>
                                        <label class="form-check-label small" for="km-{{ $km->id }}">
                                            {{ $km->nama_kelas_moving }}
                                            <span class="d-block text-muted">
                                                {{ $km->anggota->count() }} siswa{{ $km->ruang ? ' · ruang '.$km->ruang : '' }}
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="form-text mt-2">
                            Anggotanya langsung terdaftar sebagai peserta dan bisa disinkronkan ulang bila
                            susunan kelas moving di Data Center berubah. Biarkan kosong bila pesertanya mau
                            dipilih sendiri satu per satu di halaman Peserta.
                        </div>
                    </div>

                    <div class="fw-semibold small mb-2" id="label-kelas">Kelas peserta</div>
                    @if ($rombel->isEmpty())
                        <div class="alert alert-warning mb-0 small">
                            Tidak ada rombongan belajar pada tahun ajaran aktif di Data Center.
                        </div>
                    @else
                        <div class="d-flex gap-2 mb-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="pilihKelas(true)">Pilih semua</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="pilihKelas(false)">Kosongkan</button>
                        </div>
                        <div class="row g-2">
                            @foreach ($rombel as $k)
                                <div class="col-6 col-md-4 col-xl-3 kolom-kelas">
                                    <div class="form-check border rounded p-2 ps-4">
                                        <input class="form-check-input kelas-cb" type="checkbox" name="rombongan_belajar_id[]"
                                               value="{{ $k->id }}" id="kelas-{{ $k->id }}"
                                               @checked(in_array($k->id, old('rombongan_belajar_id', $kelasTerpilih)))>
                                        <label class="form-check-label small" for="kelas-{{ $k->id }}">
                                            {{ $k->nama_rombel }}
                                            <span class="d-block text-muted">{{ $k->jurusan->singkatan ?? $k->jurusan->nama_jurusan ?? '' }}</span>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('rombongan_belajar_id')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                        <div class="form-text mt-2" id="catatan-kelas-mapel"></div>
                        <div class="form-text mt-2">
                            Siswa tiap kelas diambil langsung dari Data Center saat disimpan, dan bisa disinkronkan ulang kapan saja.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">Aturan Ujian</div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label">Token masuk</label>
                            <input name="token" value="{{ old('token', $item->token) }}" class="form-control text-uppercase"
                                   maxlength="10" placeholder="kosongkan = tanpa token">
                        </div>
                        <div class="col-5">
                            <label class="form-label">KKM <span class="text-danger">*</span></label>
                            <input type="number" step="0.5" min="0" max="100" name="kkm"
                                   value="{{ old('kkm', $item->kkm) }}" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="acak_soal" value="1" id="acak_soal"
                               @checked(old('acak_soal', $item->acak_soal))>
                        <label class="form-check-label" for="acak_soal">Acak urutan soal tiap siswa</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="acak_opsi" value="1" id="acak_opsi"
                               @checked(old('acak_opsi', $item->acak_opsi))>
                        <label class="form-check-label" for="acak_opsi">Acak urutan opsi jawaban</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tampilkan_hasil" value="1" id="tampilkan_hasil"
                               @checked(old('tampilkan_hasil', $item->tampilkan_hasil))>
                        <label class="form-check-label" for="tampilkan_hasil">Tampilkan nilai &amp; pembahasan ke siswa</label>
                    </div>
                    <div class="form-text">Pembahasan baru terbuka setelah jendela waktu ujian berakhir.</div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Proteksi Kecurangan</div>
                <div class="card-body">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="proteksi_ketat" value="1" id="proteksi_ketat"
                               @checked(old('proteksi_ketat', $item->exists ? $item->proteksi_ketat : true))>
                        <label class="form-check-label fw-semibold" for="proteksi_ketat">Aktifkan proteksi ketat</label>
                    </div>
                    <div class="form-text mb-3">
                        Menutup soal begitu peserta membuka tab baru, membelah layar,
                        atau memakai jendela mengambang. Percobaan menyalin dan menempel
                        isi soal juga dicegah.
                    </div>

                    <label class="form-label" for="maks_pelanggaran">Kunci lembar setelah</label>
                    <select name="maks_pelanggaran" id="maks_pelanggaran" class="form-select">
                        @foreach ([0 => 'Tidak pernah — hanya diperingatkan &amp; dicatat',
                                   2 => '2 pelanggaran',
                                   3 => '3 pelanggaran',
                                   5 => '5 pelanggaran',
                                   10 => '10 pelanggaran'] as $nilai => $label)
                            <option value="{{ $nilai }}"
                                @selected((int) old('maks_pelanggaran', $item->exists ? $item->maks_pelanggaran : 3) === $nilai)>
                                {!! $label !!}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">
                        Lembar yang terkunci hanya dapat dibuka pengawas lewat menu Monitoring Ujian.
                        Jawaban yang sudah tersimpan tidak hilang.
                    </div>

                    <hr class="my-3">

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="wajib_exambro" value="1" id="wajib_exambro"
                               @checked(old('wajib_exambro', $item->wajib_exambro ?? false))>
                        <label class="form-check-label fw-semibold" for="wajib_exambro">Wajib lewat ExamBro</label>
                    </div>
                    <div class="form-text">
                        Peserta yang membuka ujian dari peramban biasa tidak diizinkan menekan
                        "Mulai", dan lembar yang sudah berjalan pun berhenti bila dilanjutkan
                        di luar ExamBro. Penolakannya tercatat di Monitoring Ujian.
                    </div>

                    @if (empty(config('ujian.penanda_exambro')))
                        <div class="alert alert-warning py-2 small mt-2 mb-0">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            <strong>Uji dulu sebelum dipakai serentak.</strong>
                            Peramban ujian dikenali dari user agent-nya. Sebagian build ExamBro
                            mengirim user agent yang sama persis dengan Chrome biasa — pada build
                            seperti itu seluruh peserta akan tertolak. Bila itu terjadi, setel
                            <code>UJIAN_PENANDA_EXAMBRO</code> pada berkas <code>.env</code>
                            sesuai penanda yang terlihat di menu Log Login.
                        </div>
                    @endif
                </div>
            </div>

            @if ($item->exists)
                <div class="card mb-3">
                    <div class="card-header">Tindakan</div>
                    <div class="list-group list-group-flush">
                        <a href="{{ route('ujian.peserta', $item) }}" class="list-group-item list-group-item-action">
                            <i class="bi bi-people me-2"></i>Kelola peserta
                        </a>
                        <a href="{{ route('monitoring.show', $item) }}" class="list-group-item list-group-item-action">
                            <i class="bi bi-display me-2"></i>Monitoring pelaksanaan
                        </a>
                        <a href="{{ route('laporan.nilai.show', $item) }}" class="list-group-item list-group-item-action">
                            <i class="bi bi-card-checklist me-2"></i>Daftar nilai
                        </a>
                    </div>
                </div>
            @endif

            <div class="d-flex gap-2">
                <button class="btn btn-primary flex-grow-1"><i class="bi bi-save me-1"></i>Simpan</button>
                <a href="{{ route('ujian.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
    function pilihKelas(nilai) {
        // Hanya kelas yang sedang tampil (pengambil mapel paket) yang ikut.
        document.querySelectorAll('.kelas-cb').forEach(cb => {
            if (!cb.closest('.kolom-kelas')?.classList.contains('d-none')) cb.checked = nilai;
        });
    }

    // Centang kelas mengerucut mengikuti mata pelajaran paket soal: kelas yang
    // tidak mengambil mapel itu disembunyikan dan centangnya dilepas.
    (function () {
        const infoPaket = @json($infoPaket);
        const paket = document.querySelector('select[name="paket_soal_id"]');
        const catatan = document.getElementById('catatan-kelas-mapel');

        function segarkanKelas(muatAwal) {
            const info = paket ? infoPaket[paket.value] : null;
            const boleh = info ? info.rombel : null;
            let tersembunyi = 0;

            document.querySelectorAll('.kelas-cb').forEach(cb => {
                const kolom = cb.closest('.kolom-kelas');
                // Saat halaman baru dimuat, kelas yang sudah tercentang pada
                // ujian lama tetap ditampilkan — jangan sampai pilihan guru
                // hilang diam-diam hanya karena penugasan mapel di Data Center
                // belum lengkap.
                const cocok = !boleh || boleh.includes(parseInt(cb.value, 10)) || (muatAwal && cb.checked);
                if (kolom) kolom.classList.toggle('d-none', !cocok);
                if (!cocok && cb.checked) cb.checked = false;
                if (!cocok) tersembunyi++;
            });

            if (catatan) {
                catatan.classList.remove('text-warning-emphasis');

                if (info && info.alasan === 'tanpa_mapel') {
                    catatan.textContent = 'Paket soal ini belum diisi mata pelajarannya, jadi daftar kelas '
                        + 'tidak bisa dikerucutkan. Isi mata pelajaran pada paket soalnya.';
                    catatan.classList.add('text-warning-emphasis');
                } else if (info && info.alasan === 'tanpa_penugasan') {
                    catatan.textContent = 'Data Center belum mencatat kelas mana yang mengambil '
                        + (info.mapel || 'mata pelajaran ini')
                        + ', jadi seluruh kelas ditampilkan.';
                    catatan.classList.add('text-warning-emphasis');
                } else {
                    catatan.textContent = tersembunyi > 0
                        ? tersembunyi + ' kelas disembunyikan karena tidak mengambil mata pelajaran paket soal ini.'
                        : '';
                }
            }
        }

        // Centang kelas moving hanya masuk akal pada mode kelas moving, dan
        // hanya untuk kelas moving bermapel sama dengan paket soalnya.
        const catatanDc = document.getElementById('catatan-kelas-moving-dc');
        const blokMoving = document.getElementById('blok-kelas-moving');

        function segarkanKelasMoving(muatAwal) {
            const info = paket ? infoPaket[paket.value] : null;
            const daftar = info && info.moving ? info.moving : [];
            const ids = info && info.movingIds ? info.movingIds : [];
            const modeMoving = document.querySelector('.mode-peserta:checked')?.value === 'manual';

            if (catatanDc) {
                const tampil = daftar.length > 0 && ! modeMoving;
                catatanDc.classList.toggle('d-none', ! tampil);
                catatanDc.textContent = tampil
                    ? 'Mata pelajaran ini punya kelas moving di Data Center: ' + daftar.join(', ')
                      + '. Pilih mode kelas moving untuk memakainya.'
                    : '';
            }

            if (! blokMoving) return;

            blokMoving.classList.toggle('d-none', ! (modeMoving && ids.length > 0));

            document.querySelectorAll('.kolom-kelas-moving').forEach(kolom => {
                const cocok = ids.includes(parseInt(kolom.dataset.kelasMoving, 10));
                const cb = kolom.querySelector('.kelas-moving-cb');
                // Seperti centang kelas, pilihan lama tidak dilepas saat muat.
                const simpan = muatAwal && cb.checked;
                kolom.classList.toggle('d-none', ! (cocok || simpan));
                if (! cocok && ! simpan && cb.checked) cb.checked = false;
            });
        }

        function segarkanSemua(muatAwal) {
            segarkanKelas(muatAwal);
            segarkanKelasMoving(muatAwal);
        }

        if (paket) paket.addEventListener('change', () => segarkanSemua(false));
        document.querySelectorAll('.mode-peserta').forEach(r =>
            r.addEventListener('change', () => segarkanKelasMoving(false)));

        segarkanSemua(true);
    })();

    // Keterangan kelas menyesuaikan mode peserta: pada kelas moving daftar
    // kelas hanya pelengkap (boleh kosong), bukan penentu peserta.
    (function () {
        const radio = document.querySelectorAll('.mode-peserta');
        const catatan = document.getElementById('catatan-moving');
        const label = document.getElementById('label-kelas');

        function segarkan() {
            const moving = document.querySelector('.mode-peserta:checked')?.value === 'manual';
            if (catatan) catatan.classList.toggle('d-none', !moving);
            if (label) label.textContent = moving ? 'Kelas peserta (opsional)' : 'Kelas peserta';
        }

        radio.forEach(r => r.addEventListener('change', segarkan));
        segarkan();
    })();
</script>
@endpush
@endsection
