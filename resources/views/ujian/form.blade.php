@extends('layouts.app')
@section('title', $item->exists ? 'Ubah Registrasi Ujian' : 'Registrasi Ujian')

@section('content')
@php use App\Models\Ujian; use App\Support\Pengguna; use App\Support\Referensi; @endphp

@php $rombel = Referensi::rombel(Pengguna::guruId()); @endphp

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
                <div class="card-header">Kelas Peserta <span class="text-danger">*</span></div>
                <div class="card-body">
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
                                <div class="col-6 col-md-4 col-xl-3">
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
        document.querySelectorAll('.kelas-cb').forEach(cb => cb.checked = nilai);
    }
</script>
@endpush
@endsection
