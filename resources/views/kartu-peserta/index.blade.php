@extends('layouts.app')
@section('title', 'Kartu Peserta Ujian')
@section('subtitle', 'Cetak kartu untuk siswa — satu kartu memuat seluruh jadwal ujian terpilih yang diikutinya')

@section('content')
@php
    use App\Support\Pengguna;
    use App\Support\Referensi;

    $jumlahKartu = $kartuPerKelas->sum(fn ($k) => $k->count());
    $namaSekolah = $sekolah?->nama_sekolah ?? config('app.name');
@endphp

<div class="card mb-3 no-print">
    <div class="card-body">
        <form class="row g-2 align-items-end">
            <div class="col-12">
                <label class="form-label">Jadwal ujian</label>
                <select name="ujian_id[]" class="form-select form-select-sm" multiple data-placeholder="Pilih jadwal">
                    @foreach ($daftarUjian as $u)
                        <option value="{{ $u->id }}" @selected($terpilih->has($u->id))>
                            {{ $u->nama_ujian }} — {{ $u->waktu_mulai->format('d/m/Y H:i') }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">Bawaannya semua jadwal yang belum lewat.</div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Kelas</label>
                <select name="rombongan_belajar_id" class="form-select form-select-sm">
                    <option value="">Semua kelas</option>
                    @foreach (Referensi::rombel(Pengguna::guruId()) as $k)
                        <option value="{{ $k->id }}" @selected(request('rombongan_belajar_id') == $k->id)>{{ $k->nama_rombel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-5">
                <label class="form-label">Nama kegiatan</label>
                <input name="judul" value="{{ request('judul', $judul) }}" maxlength="80"
                       class="form-control form-control-sm" placeholder="mis. Penilaian Akhir Semester">
            </div>
            <div class="col-12 col-md-4 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Tampilkan</button>
                <a href="{{ route('kartu-peserta.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
    <div class="card-footer bg-transparent d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span class="small text-muted">
            <strong class="text-body">{{ $jumlahKartu }}</strong> kartu
            &middot; {{ $kartuPerKelas->count() }} kelas
            &middot; {{ $terpilih->count() }} jadwal
        </span>
        <button type="button" class="btn btn-sm btn-success" onclick="window.print()" @disabled($jumlahKartu === 0)>
            <i class="bi bi-printer me-1"></i>Cetak Kartu
        </button>
    </div>
</div>

@if ($terpilih->isEmpty())
    <div class="alert alert-light border no-print">
        <i class="bi bi-info-circle me-1"></i>Pilih minimal satu jadwal ujian untuk mencetak kartunya.
    </div>
@elseif ($jumlahKartu === 0)
    <div class="alert alert-light border no-print">
        <i class="bi bi-info-circle me-1"></i>Belum ada peserta terdaftar pada jadwal dan kelas terpilih.
        Daftarkan kelas peserta lalu tekan <strong>Sinkron Peserta</strong> di menu Registrasi Ujian.
    </div>
@else
    <div class="alert alert-light border small no-print">
        <i class="bi bi-printer me-1"></i>
        Pratinjau di bawah ini adalah hasil cetaknya: kertas A4 tegak, dua kartu per baris, dan tiap kelas
        mulai di halaman baru. Pada jendela cetak, aktifkan <em>Background graphics</em> agar warna kop ikut tercetak.
    </div>

    <div class="lembar">
        @foreach ($kartuPerKelas as $kelas => $daftarKartu)
            <section class="kelas-kartu">
                <div class="judul-kelas no-print">Kelas {{ $kelas }} &middot; {{ $daftarKartu->count() }} kartu</div>

                <div class="kisi-kartu">
                    @foreach ($daftarKartu as $k)
                        <article class="kartu">
                            <header class="kartu-kop">
                                <div class="kartu-logo"><i class="bi bi-mortarboard-fill"></i></div>
                                <div class="min-width-0">
                                    <div class="kartu-judul">KARTU PESERTA UJIAN</div>
                                    <div class="kartu-sekolah">{{ $namaSekolah }}</div>
                                    <div class="kartu-sub">
                                        {{ collect([$judul, $periode])->filter()->implode(' · ') }}
                                    </div>
                                </div>
                            </header>

                            <table class="kartu-identitas">
                                <tr><td>No. peserta</td><td>:</td><td class="tebal">{{ $k->nomor ?: '-' }}</td></tr>
                                <tr><td>Nama</td><td>:</td><td class="tebal">{{ $k->siswa->nama_siswa }}</td></tr>
                                <tr><td>Kelas</td><td>:</td><td>{{ $k->kelas ?? '-' }}</td></tr>
                                <tr><td>Username</td><td>:</td><td class="mono tebal">{{ $k->siswa->nisn ?: '-' }}</td></tr>
                            </table>

                            <table class="kartu-jadwal">
                                <thead>
                                    <tr><th style="width:5mm">No</th><th>Mata pelajaran</th><th>Hari, tanggal</th><th>Jam</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($k->jadwal as $i => $u)
                                        <tr>
                                            <td class="tengah">{{ $i + 1 }}</td>
                                            <td>{{ $u->mataPelajaran->nama_mapel ?? $u->nama_ujian }}</td>
                                            <td class="nowrap">{{ $u->waktu_mulai->translatedFormat('D, j M Y') }}</td>
                                            <td class="nowrap">
                                                {{ $u->waktu_mulai->format('H:i') }}–{{ $u->waktu_selesai->isSameDay($u->waktu_mulai)
                                                    ? $u->waktu_selesai->format('H:i')
                                                    : $u->waktu_selesai->translatedFormat('j M H:i') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            {{-- Foto di kiri bawah, berdampingan dengan tanda tangan:
                                 bila foto di samping identitas, tinggi kotak foto
                                 memaksa bagian identitas ikut setinggi itu dan
                                 hanya empat kartu yang muat selembar. --}}
                            <footer class="kartu-kaki">
                                <div class="kartu-foto">Foto<br>3 × 4</div>
                                <div class="kartu-catatan">
                                    Masuk dengan username di atas dan kata sandi akun siswa sekolah.
                                    Token dibacakan pengawas. Bawa kartu ini setiap ujian.
                                </div>
                                <div class="kartu-ttd">
                                    {{ $sekolah?->kabupaten ? $sekolah->kabupaten.', ' : '' }}{{ now()->translatedFormat('j F Y') }}<br>
                                    Kepala Sekolah,
                                    <div class="ruang-ttd"></div>
                                    <div class="tebal">{{ $sekolah?->kepala_sekolah ?: '..............................' }}</div>
                                    @if ($sekolah?->nip_kepala_sekolah)
                                        <div>NIP. {{ $sekolah->nip_kepala_sekolah }}</div>
                                    @endif
                                </div>
                            </footer>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endif
@endsection

@push('head')
<style>
    /*
        Ukuran kartu ditulis dalam milimeter supaya pratinjau di layar sama
        dengan hasil cetak: kertas A4 dengan tepi 10 mm menyisakan 190 mm,
        dibagi dua kartu dengan sela 5 mm.
    */
    .lembar { max-width:190mm; margin:0 auto; }
    .kelas-kartu + .kelas-kartu { margin-top:8mm; }
    .judul-kelas { font-weight:600; font-size:.85rem; color:#5b6b83; margin-bottom:.5rem; }
    .kisi-kartu { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:5mm; }

    .kartu {
        background:#fff; color:#111; border:1px solid #9aa5b4; border-radius:2.5mm;
        padding:3.5mm 4mm; font-size:8pt; line-height:1.35;
        break-inside:avoid; page-break-inside:avoid;
        -webkit-print-color-adjust:exact; print-color-adjust:exact;
    }
    .kartu table { width:100%; border-collapse:collapse; }

    .kartu-kop { display:flex; align-items:center; gap:3mm; padding-bottom:2mm; margin-bottom:2mm; border-bottom:.6mm solid #1b3a63; }
    .kartu-logo {
        width:10mm; height:10mm; flex:none; border-radius:2mm; display:grid; place-items:center;
        background:#1b3a63; color:#fff; font-size:13pt;
    }
    .kartu-judul { font-weight:800; font-size:10pt; letter-spacing:.06em; color:#1b3a63; }
    .kartu-sekolah { font-weight:700; font-size:8pt; text-transform:uppercase; }
    .kartu-sub { font-size:7pt; color:#444; }

    .kartu-identitas { margin-bottom:2mm; }
    .kartu-identitas td { padding:.25mm 0; vertical-align:top; }
    .kartu-identitas td:first-child { width:17mm; color:#444; white-space:nowrap; }
    .kartu-identitas td:nth-child(2) { width:3mm; }
    .kartu-foto {
        width:17mm; height:22mm; flex:none; border:1px dashed #8a94a3; border-radius:1mm;
        display:grid; place-items:center; text-align:center; font-size:7pt; color:#8a94a3;
    }

    .kartu-jadwal { font-size:7pt; }
    .kartu-jadwal th { background:#e9eef5; font-weight:700; text-align:left; }
    .kartu-jadwal th, .kartu-jadwal td { border:.2mm solid #b8c1cc; padding:.5mm 1mm; }

    .kartu-kaki { display:flex; align-items:flex-end; gap:3mm; margin-top:2mm; font-size:7pt; }
    .kartu-catatan { color:#555; flex:1 1 auto; min-width:0; font-size:6.5pt; line-height:1.3; }
    .kartu-ttd { text-align:center; flex:none; }
    .ruang-ttd { height:8mm; }

    .tebal { font-weight:700; }
    .redup { color:#666; font-style:italic; }
    .mono { font-family:ui-monospace,Consolas,monospace; letter-spacing:.03em; }
    .tengah { text-align:center; }
    .nowrap { white-space:nowrap; }

    /* Di layar ponsel pratinjaunya satu kartu per baris. */
    @media screen and (max-width: 575.98px) {
        .kisi-kartu { grid-template-columns:minmax(0, 1fr); }
    }

    @media print {
        @page { size:A4 portrait; margin:10mm; }

        .sidebar, .sidebar-backdrop, .topbar, .content-wrapper > footer, .no-print, .alert { display:none !important; }
        .content-wrapper { margin-left:0 !important; min-height:0 !important; }
        main { padding:0 !important; }
        body { background:#fff !important; }

        .lembar { max-width:none; }
        .kelas-kartu + .kelas-kartu { margin-top:0; break-before:page; page-break-before:always; }
        .kisi-kartu { grid-template-columns:repeat(2, minmax(0, 1fr)) !important; }
    }
</style>
@endpush
