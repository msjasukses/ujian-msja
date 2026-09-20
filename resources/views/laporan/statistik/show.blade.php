@extends('layouts.app')
@section('title', 'Statistik — '.$ujian->nama_ujian)
@section('subtitle', $ujian->kode_ujian.' · '.($ujian->mataPelajaran->nama_mapel ?? '-').' · KKM '.(float) $ujian->kkm)

@section('content')

<div class="d-flex flex-wrap gap-2 justify-content-end mb-3">
    <a href="{{ route('laporan.statistik.export', $ujian) }}" class="btn btn-sm btn-outline-success">
        <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
    </a>
    <a href="{{ route('laporan.nilai.show', $ujian) }}" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-card-checklist me-1"></i>Daftar Nilai
    </a>
    <a href="{{ route('laporan.analisis.show', $ujian) }}" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-graph-up me-1"></i>Analisis Butir
    </a>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-stat label="Peserta selesai" :value="$ringkasan['selesai'].' / '.$ringkasan['terdaftar']" icon="bi-people" /></div>
    <div class="col-6 col-lg-3"><x-stat label="Nilai rata-rata" :value="$ringkasan['rata_rata']" icon="bi-calculator" warna="info" /></div>
    <div class="col-6 col-lg-3"><x-stat label="Ketuntasan" :value="$ringkasan['persen_tuntas'].'%'" icon="bi-patch-check" warna="success"
                                        :keterangan="$ringkasan['tuntas'].' tuntas, '.$ringkasan['belum_tuntas'].' belum'" /></div>
    <div class="col-6 col-lg-3"><x-stat label="Simpangan baku" :value="$ringkasan['simpangan_baku']" icon="bi-distribute-vertical" warna="secondary"
                                        keterangan="sebaran nilai" /></div>
</div>

@if ($ringkasan['menunggu_koreksi'] > 0)
    <div class="alert alert-warning py-2 small">
        <i class="bi bi-hourglass-split me-1"></i>
        Angka di halaman ini belum final: {{ $ringkasan['menunggu_koreksi'] }} lembar masih menunggu penilaian essay.
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Distribusi Nilai</div>
            <div class="card-body">
                @php $maksJumlah = max(1, collect($distribusi)->max('jumlah')); @endphp
                @foreach ($distribusi as $d)
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="small text-muted text-end" style="width:5rem">{{ $d['label'] }}</div>
                        <div class="progress flex-grow-1" style="height:1.35rem">
                            <div class="progress-bar {{ $d['min'] >= (float) $ujian->kkm ? 'bg-success' : 'bg-danger' }}"
                                 style="width:{{ round($d['jumlah'] / $maksJumlah * 100) }}%">
                                @if ($d['jumlah'] > 0) {{ $d['jumlah'] }} @endif
                            </div>
                        </div>
                        <div class="small text-muted" style="width:3.5rem">{{ $d['persen'] }}%</div>
                    </div>
                @endforeach
                <div class="small text-muted mt-3">
                    Batang hijau berada pada atau di atas KKM ({{ (float) $ujian->kkm }}), batang merah di bawahnya.
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Ukuran Statistik</div>
            <table class="table table-sm mb-0">
                <tbody>
                    <tr><td class="text-muted">Peserta terdaftar</td><td class="text-end fw-semibold">{{ $ringkasan['terdaftar'] }}</td></tr>
                    <tr><td class="text-muted">Menyelesaikan</td><td class="text-end fw-semibold">{{ $ringkasan['selesai'] }}</td></tr>
                    <tr><td class="text-muted">Belum mengerjakan</td><td class="text-end fw-semibold">{{ $ringkasan['belum'] }}</td></tr>
                    <tr><td class="text-muted">Rata-rata</td><td class="text-end fw-semibold">{{ $ringkasan['rata_rata'] }}</td></tr>
                    <tr><td class="text-muted">Median</td><td class="text-end fw-semibold">{{ $ringkasan['median'] }}</td></tr>
                    <tr><td class="text-muted">Modus</td><td class="text-end fw-semibold">{{ $ringkasan['modus'] ?? 'tidak ada' }}</td></tr>
                    <tr><td class="text-muted">Nilai tertinggi</td><td class="text-end fw-semibold">{{ $ringkasan['tertinggi'] }}</td></tr>
                    <tr><td class="text-muted">Nilai terendah</td><td class="text-end fw-semibold">{{ $ringkasan['terendah'] }}</td></tr>
                    <tr><td class="text-muted">Jangkauan</td><td class="text-end fw-semibold">{{ $ringkasan['jangkauan'] }}</td></tr>
                    <tr><td class="text-muted">Simpangan baku</td><td class="text-end fw-semibold">{{ $ringkasan['simpangan_baku'] }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">Perbandingan Antar Kelas</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Kelas</th><th class="text-center">Terdaftar</th><th class="text-center">Selesai</th>
                    <th class="text-center">Rata-rata</th><th class="text-center">Tertinggi</th><th class="text-center">Terendah</th>
                    <th class="text-center">Tuntas</th><th class="text-center">Belum</th><th style="width:12rem">Ketuntasan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($per_kelas as $k)
                    <tr>
                        <td class="fw-semibold">{{ $k->nama_kelas }}</td>
                        <td class="text-center">{{ $k->terdaftar }}</td>
                        <td class="text-center">{{ $k->selesai }}</td>
                        <td class="text-center fw-semibold">{{ $k->rata_rata }}</td>
                        <td class="text-center small">{{ $k->tertinggi }}</td>
                        <td class="text-center small">{{ $k->terendah }}</td>
                        <td class="text-center"><span class="badge text-bg-success">{{ $k->tuntas }}</span></td>
                        <td class="text-center"><span class="badge text-bg-danger">{{ $k->belum_tuntas }}</span></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:.5rem">
                                    <div class="progress-bar bg-success" style="width:{{ $k->persen_tuntas }}%"></div>
                                </div>
                                <span class="small text-muted">{{ $k->persen_tuntas }}%</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-kosong kolom="9" icon="bi-bar-chart" pesan="Belum ada peserta yang menyelesaikan ujian ini." />
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    <a href="{{ route('laporan.statistik.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>
@endsection
