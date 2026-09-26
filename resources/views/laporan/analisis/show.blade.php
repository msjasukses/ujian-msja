@extends('layouts.app')
@php use App\Support\TeksSoal; @endphp
@section('title', 'Analisis Butir — '.$ujian->nama_ujian)
@section('subtitle', $ujian->kode_ujian.' · dianalisis dari '.$jumlah_peserta.' lembar jawaban yang selesai')

@section('content')

<div class="d-flex flex-wrap gap-2 justify-content-end mb-3">
    <a href="{{ route('laporan.analisis.export', [$ujian] + request()->only('rombongan_belajar_id')) }}"
       class="btn btn-sm btn-outline-success">
        <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
    </a>
    <a href="{{ route('laporan.statistik.show', [$ujian] + request()->only('rombongan_belajar_id')) }}"
       class="btn btn-sm btn-outline-primary">
        <i class="bi bi-bar-chart-line me-1"></i>Statistik
    </a>
</div>

@include('laporan.partials.filter-kelas', compact('ujian'))

@if ($jumlah_peserta === 0)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Belum ada peserta yang menyelesaikan ujian ini, sehingga butir soalnya belum bisa dianalisis.
    </div>
@else

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-2"><x-stat label="Jumlah butir" :value="$ringkasan['jumlah_butir']" icon="bi-list-ol" /></div>
    <div class="col-6 col-lg-2"><x-stat label="Mudah" :value="$ringkasan['mudah']" icon="bi-emoji-smile" warna="success" /></div>
    <div class="col-6 col-lg-2"><x-stat label="Sedang" :value="$ringkasan['sedang']" icon="bi-emoji-neutral" warna="info" /></div>
    <div class="col-6 col-lg-2"><x-stat label="Sukar" :value="$ringkasan['sukar']" icon="bi-emoji-frown" warna="danger" /></div>
    <div class="col-6 col-lg-2"><x-stat label="Butir diterima" :value="$ringkasan['diterima']" icon="bi-check2-circle" warna="success" /></div>
    <div class="col-6 col-lg-2"><x-stat label="Perlu revisi/buang" :value="$ringkasan['revisi'] + $ringkasan['dibuang']"
                                        icon="bi-tools" warna="warning" /></div>
</div>

<div class="alert alert-light border small">
    <strong>Cara membaca:</strong>
    <span class="d-block mt-1">
        <strong>Tingkat kesukaran (P)</strong> = rata-rata skor butir dibagi skor maksimalnya —
        makin besar makin mudah (≤0,30 sukar; 0,31–0,70 sedang; &gt;0,70 mudah).
    </span>
    <span class="d-block">
        <strong>Daya pembeda (D)</strong> = selisih rata-rata skor 27% peserta teratas dan 27% terbawah, dibagi skor maksimal.
        &lt;0,20 jelek; 0,20–0,29 cukup; 0,30–0,39 baik; ≥0,40 sangat baik. Nilai <em>negatif</em> berarti siswa pandai justru
        lebih banyak salah — kunci jawabannya patut diperiksa.
    </span>
</div>

<div class="card">
    <div class="card-header">Hasil Analisis per Butir</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:3rem">No</th>
                    <th>Butir Soal</th>
                    <th>Jenis</th>
                    <th class="text-center">Benar</th>
                    <th class="text-center">P</th>
                    <th>Kategori</th>
                    <th class="text-center">D</th>
                    <th>Daya Pembeda</th>
                    <th>Keputusan</th>
                    <th style="width:3rem"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($butir as $b)
                    <tr>
                        <td class="text-muted">{{ $b->nomor }}</td>
                        <td>
                            <div class="text-wrap-2 small">{{ Str::limit(TeksSoal::polos($b->soal?->pertanyaan), 130) }}</div>
                        </td>
                        <td><x-jenis-soal :jenis="$b->jenis ?? 'pg'" /></td>
                        <td class="text-center small">
                            {{ $b->jumlah_benar }}
                            <span class="text-muted">({{ $b->persen_benar }}%)</span>
                        </td>
                        <td class="text-center fw-semibold">{{ number_format($b->tingkat_kesukaran, 2) }}</td>
                        <td>
                            <span class="badge {{ ['Mudah' => 'text-bg-success', 'Sedang' => 'text-bg-info', 'Sukar' => 'text-bg-danger'][$b->kategori_kesukaran] }}">
                                {{ $b->kategori_kesukaran }}
                            </span>
                        </td>
                        <td class="text-center fw-semibold {{ $b->daya_pembeda < 0 ? 'text-danger' : '' }}">
                            {{ number_format($b->daya_pembeda, 2) }}
                        </td>
                        <td><span class="badge badge-soft">{{ $b->kategori_daya_pembeda }}</span></td>
                        <td>
                            <span class="badge {{ $b->keputusan === 'Diterima' ? 'text-bg-success'
                                : ($b->keputusan === 'Buang / perbaiki kunci' ? 'text-bg-danger' : 'text-bg-warning') }}">
                                {{ $b->keputusan }}
                            </span>
                        </td>
                        <td class="text-end">
                            @if ($b->pengecoh)
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse"
                                        data-bs-target="#pengecoh-{{ $b->nomor }}" title="Sebaran pengecoh">
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                            @endif
                        </td>
                    </tr>
                    @if ($b->pengecoh)
                        <tr class="collapse" id="pengecoh-{{ $b->nomor }}">
                            <td colspan="10" class="bg-light-subtle">
                                <div class="small fw-semibold mb-2">Sebaran pilihan jawaban butir nomor {{ $b->nomor }}</div>
                                <table class="table table-sm mb-0" style="max-width:45rem">
                                    <thead><tr><th>Opsi</th><th>Teks</th><th class="text-center">Dipilih</th><th class="text-center">%</th><th>Status</th></tr></thead>
                                    <tbody>
                                        @foreach ($b->pengecoh as $o)
                                            <tr class="{{ $o['kunci'] ? 'table-success' : '' }}">
                                                <td class="fw-semibold">{{ strtoupper($o['key']) }}</td>
                                                <td class="small">{{ Str::limit($o['text'], 70) }}</td>
                                                <td class="text-center">{{ $o['dipilih'] }}</td>
                                                <td class="text-center">{{ $o['persen'] }}</td>
                                                <td>
                                                    <span class="badge {{ $o['status'] === 'Kunci' ? 'text-bg-success'
                                                        : ($o['status'] === 'Berfungsi' ? 'badge-soft' : 'text-bg-warning') }}">
                                                        {{ $o['status'] }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                <div class="small text-muted mt-2">
                                    Pengecoh dianggap berfungsi bila dipilih minimal 5% peserta.
                                </div>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="mt-3">
    <a href="{{ route('laporan.analisis.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>
@endsection
