@extends('layouts.app')
@section('title', 'Daftar Nilai — '.$ujian->nama_ujian)
@section('subtitle', $ujian->kode_ujian.' · '.($ujian->mataPelajaran->nama_mapel ?? '-').' · KKM '.(float) $ujian->kkm)

@section('content')
@php use App\Models\UjianPeserta; use App\Support\Referensi; @endphp

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-2"><x-stat label="Selesai" :value="$ringkasan['jumlah']" icon="bi-check2-all" /></div>
    <div class="col-6 col-lg-2"><x-stat label="Rata-rata" :value="$ringkasan['rata_rata']" icon="bi-calculator" warna="info" /></div>
    <div class="col-6 col-lg-2"><x-stat label="Tertinggi" :value="$ringkasan['tertinggi']" icon="bi-arrow-up-circle" warna="success" /></div>
    <div class="col-6 col-lg-2"><x-stat label="Terendah" :value="$ringkasan['terendah']" icon="bi-arrow-down-circle" warna="danger" /></div>
    <div class="col-6 col-lg-2"><x-stat label="Tuntas" :value="$ringkasan['tuntas']" icon="bi-patch-check" warna="success" /></div>
    <div class="col-6 col-lg-2"><x-stat label="Belum tuntas" :value="$ringkasan['belum_tuntas']" icon="bi-exclamation-circle" warna="warning" /></div>
</div>

@if ($ringkasan['menunggu_koreksi'] > 0)
    <div class="alert alert-warning py-2 small">
        <i class="bi bi-hourglass-split me-1"></i>
        <strong>{{ $ringkasan['menunggu_koreksi'] }} lembar</strong> masih punya butir essay yang belum dinilai —
        nilai akhirnya belum final. Klik ikon <i class="bi bi-pencil-square"></i> pada baris siswa untuk menilai.
    </div>
@endif

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span>Nilai Peserta</span>
        <div class="d-flex flex-wrap gap-2">
            <form method="POST" action="{{ route('laporan.nilai.koreksi-ulang', $ujian) }}"
                  data-konfirmasi="Koreksi ulang seluruh lembar jawaban objektif? Gunakan ini setelah kunci jawaban diperbaiki.">
                @csrf
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-repeat me-1"></i>Koreksi Ulang</button>
            </form>
            <a href="{{ route('laporan.nilai.export', array_merge([$ujian], request()->query())) }}" class="btn btn-sm btn-outline-success">
                <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
            </a>
            <a href="{{ route('laporan.statistik.show', [$ujian] + request()->only('rombongan_belajar_id')) }}"
               class="btn btn-sm btn-outline-primary">
                <i class="bi bi-bar-chart-line me-1"></i>Statistik
            </a>
        </div>
    </div>

    <div class="card-body border-bottom bg-light-subtle py-2">
        <form class="row g-2 align-items-end">
            <div class="col-8 col-md-3">
                <label class="form-label">Kelas</label>
                {{-- Kelas yang ikut ujian ini saja, bukan seluruh rombel sekolah. --}}
                <x-pilih-kelas :ujian="$ujian" />
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:3rem">#</th>
                    <th>NISN</th>
                    <th>Nama Siswa</th>
                    <th>Kelas</th>
                    <th class="text-center">B</th>
                    <th class="text-center">S</th>
                    <th class="text-center">Kosong</th>
                    <th class="text-center">Objektif</th>
                    <th class="text-center">Essay</th>
                    <th class="text-center">Nilai</th>
                    <th class="text-center">Ketuntasan</th>
                    <th style="width:4rem"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($peserta as $i => $p)
                    @php $selesai = $p->status === UjianPeserta::SELESAI; @endphp
                    <tr>
                        <td class="text-muted small">{{ $i + 1 }}</td>
                        <td class="small">{{ $p->siswa->nisn ?? '-' }}</td>
                        <td>
                            {{ $p->siswa->nama_siswa ?? '—' }}
                            @unless ($selesai)
                                <span class="badge badge-soft">{{ $p->status_label }}</span>
                            @endunless
                            @if ($selesai && ! $p->essay_dinilai)
                                <span class="badge text-bg-warning-subtle text-warning border border-warning-subtle">
                                    essay belum dinilai
                                </span>
                            @endif
                        </td>
                        <td class="small">{{ $p->rombel->nama_rombel ?? '-' }}</td>
                        <td class="text-center small">{{ $selesai ? $p->jumlah_benar : '-' }}</td>
                        <td class="text-center small">{{ $selesai ? $p->jumlah_salah : '-' }}</td>
                        <td class="text-center small">{{ $selesai ? $p->jumlah_kosong : '-' }}</td>
                        <td class="text-center small">{{ $selesai ? (float) $p->skor_objektif : '-' }}</td>
                        <td class="text-center small">{{ $selesai ? (float) $p->skor_essay : '-' }}</td>
                        <td class="text-center fw-semibold">{{ $selesai ? (float) $p->nilai : '-' }}</td>
                        <td class="text-center">
                            @if ($selesai)
                                <span class="badge {{ $p->lulus ? 'text-bg-success' : 'text-bg-danger' }}">
                                    {{ $p->lulus ? 'Tuntas' : 'Belum' }}
                                </span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if ($selesai)
                                <a href="{{ route('laporan.nilai.detail', [$ujian, $p]) }}"
                                   class="btn btn-sm btn-outline-primary" title="Lihat lembar jawaban &amp; nilai essay">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-kosong kolom="12" icon="bi-clipboard-x" pesan="Belum ada peserta pada ujian ini." />
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    <a href="{{ route('laporan.nilai.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali ke daftar ujian
    </a>
</div>
@endsection
