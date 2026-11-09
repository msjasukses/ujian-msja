@extends('layouts.app')
@section('title', 'Tambah Peserta — '.$ujian->nama_ujian)
@section('subtitle', $ujian->kode_ujian.' · pilih siswa peserta, boleh lintas kelas')

@section('content')
@php use App\Models\UjianPeserta; @endphp

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <div class="small text-muted">
            <i class="bi bi-info-circle me-1"></i>
            Siswa yang dipilih di sini terdaftar sebagai peserta pilihan guru, jadi tidak ikut tercabut
            saat daftar peserta disinkronkan dengan kelas.
            @if ($kelasMoving->isNotEmpty())
                <span class="d-block mt-1">
                    Kandidat diambil dari <strong>kelas moving</strong> mata pelajaran
                    <strong>{{ $ujian->mataPelajaran->nama_mapel }}</strong> di Data Center:
                    {{ $kelasMoving->pluck('nama_lengkap')->implode(', ') }}.
                </span>
            @elseif (! $alasanTidakMengerucut)
                <span class="d-block mt-1">
                    Mata pelajaran <strong>{{ $ujian->mataPelajaran->nama_mapel }}</strong>
                    belum punya kelas moving di Data Center,
                    jadi kandidat diambil dari seluruh siswa kelas pengambil mapel ini
                    ({{ $daftarKelas->pluck('nama_rombel')->implode(', ') ?: 'belum ada kelas' }}).
                </span>
            @endif
        </div>
        <a href="{{ route('ujian.peserta', $ujian) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke daftar peserta
        </a>
    </div>

    {{-- Kalau seluruh kelas tetap tampil, sebutkan sebabnya: tanpa ini guru
         hanya melihat daftar yang tidak mengerucut tanpa tahu apa yang kurang. --}}
    @if ($alasanTidakMengerucut === \App\Support\Referensi::TANPA_MAPEL)
        <div class="alert alert-warning border-0 rounded-0 mb-0 small">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Paket soal ujian ini belum diisi <strong>mata pelajaran</strong>-nya, jadi kandidat tidak bisa
            dikerucutkan dan seluruh kelas ditampilkan. Isi mata pelajaran pada paket soalnya, lalu buka
            halaman ini lagi.
        </div>
    @elseif ($alasanTidakMengerucut === \App\Support\Referensi::TANPA_PENUGASAN)
        <div class="alert alert-warning border-0 rounded-0 mb-0 small">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Data Center belum mencatat kelas mana yang mengambil
            <strong>{{ $ujian->mataPelajaran->nama_mapel ?? 'mata pelajaran ini' }}</strong>
            (tabel penugasan mengajar), jadi seluruh kelas ditampilkan. Lengkapi penugasan mengajar mapel
            tersebut supaya daftar kandidat menyempit sendiri.
        </div>
    @elseif ($daftarKelas->isEmpty())
        <div class="alert alert-warning border-0 rounded-0 mb-0 small">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Tidak ada kelas yang mengambil
            <strong>{{ $ujian->mataPelajaran->nama_mapel ?? 'mata pelajaran ini' }}</strong>
            di antara kelas yang boleh Anda akses, jadi belum ada kandidat yang bisa dipilih.
        </div>
    @endif
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <span>Kandidat Peserta ({{ $kandidat->total() }})</span>
        <span class="small text-muted">{{ $sudahTerdaftar->count() }} sudah jadi peserta</span>
    </div>

    <div class="card-body border-bottom bg-light-subtle py-2">
        <form class="row g-2 align-items-end">
            @if ($kelasMoving->count() > 1)
                <div class="col-12 col-md-4">
                    <label class="form-label">Kelas moving</label>
                    <select name="kelas_moving_id" class="form-select form-select-sm">
                        <option value="">— semua kelas moving mapel ini —</option>
                        @foreach ($kelasMoving as $km)
                            <option value="{{ $km->id }}" @selected($movingTerpilih === (int) $km->id)>
                                {{ $km->nama_lengkap }} ({{ $km->anggota->count() }} siswa)
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-12 col-md-{{ $kelasMoving->count() > 1 ? 3 : 4 }}">
                <label class="form-label">Kelas asal</label>
                <select name="rombongan_belajar_id" class="form-select form-select-sm">
                    <option value="">— semua kelas asal —</option>
                    @foreach ($namaRombel as $id => $nama)
                        <option value="{{ $id }}" @selected($kelasTerpilih === (int) $id)>{{ $nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-{{ $kelasMoving->count() > 1 ? 2 : 5 }}">
                <label class="form-label">Cari siswa</label>
                <input name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                       placeholder="Nama / NISN / NIS">
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-search me-1"></i>Cari</button>
                <a href="{{ route('ujian.peserta.pilih', $ujian) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <form method="POST" action="{{ route('ujian.peserta.tambah', $ujian) }}">
        @csrf
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:3rem">
                            <input type="checkbox" class="form-check-input" id="pilih-semua"
                                   title="Pilih semua di halaman ini">
                        </th>
                        <th>NISN</th>
                        <th>Nama Siswa</th>
                        <th>Kelas asal</th>
                        @if ($kelasMoving->isNotEmpty())
                            <th>Kelas moving</th>
                        @endif
                        <th class="text-center">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($kandidat as $s)
                        @php $terdaftar = $sudahTerdaftar->has($s->id); @endphp
                        <tr class="{{ $terdaftar ? 'opacity-50' : '' }}">
                            <td>
                                <input type="checkbox" class="form-check-input siswa-cb" name="siswa_id[]"
                                       value="{{ $s->id }}" @disabled($terdaftar)>
                            </td>
                            <td class="small">{{ $s->nisn ?: '-' }}</td>
                            <td>{{ $s->nama_siswa }}</td>
                            <td class="small">{{ $namaRombel->get($kelasSiswa->get($s->id)) ?: '-' }}</td>
                            @if ($kelasMoving->isNotEmpty())
                                <td class="small">{{ $movingSiswa->get($s->id) ?: '-' }}</td>
                            @endif
                            <td class="text-center small">
                                @if ($terdaftar)
                                    <span class="badge badge-soft">
                                        {{ UjianPeserta::STATUS[$sudahTerdaftar->get($s->id)] ?? 'Sudah terdaftar' }}
                                    </span>
                                @else
                                    <span class="text-muted">belum terdaftar</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-kosong kolom="{{ $kelasMoving->isNotEmpty() ? 6 : 5 }}" icon="bi-person-x"
                                  pesan="Tidak ada siswa aktif yang cocok." />
                    @endforelse
                </tbody>
            </table>
        </div>

        @error('siswa_id')<div class="card-body border-top text-danger small py-2">{{ $message }}</div>@enderror

        @if ($kandidat->count() > 0)
            <div class="card-body border-top d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <span class="small text-muted"><span id="jumlah-pilih">0</span> siswa dipilih</span>
                <button class="btn btn-sm btn-primary">
                    <i class="bi bi-person-plus me-1"></i>Tambahkan sebagai peserta
                </button>
            </div>
        @endif
    </form>

    @if ($kandidat->hasPages())
        <div class="card-body border-top">{{ $kandidat->links() }}</div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    // Centang massal + penghitung pilihan. Pindah halaman mengosongkan pilihan,
    // jadi tombol tambah sebaiknya ditekan sebelum berganti halaman.
    (function () {
        const semua = document.getElementById('pilih-semua');
        const kotak = () => Array.from(document.querySelectorAll('.siswa-cb:not(:disabled)'));
        const jumlah = document.getElementById('jumlah-pilih');

        function hitung() {
            if (jumlah) jumlah.textContent = kotak().filter(c => c.checked).length;
        }

        if (semua) semua.addEventListener('change', () => {
            kotak().forEach(c => c.checked = semua.checked);
            hitung();
        });

        kotak().forEach(c => c.addEventListener('change', hitung));
        hitung();
    })();
</script>
@endpush
