@extends('layouts.app')
@section('title', 'Jejak Aktivitas — '.$ujian->nama_ujian)
@section('subtitle', $ujian->kode_ujian.' · '.($ujian->mataPelajaran->nama_mapel ?? '-').' · seluruh kejadian selama ujian')

@section('content')
@php use App\Models\UjianLog; @endphp

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-stat label="Total kejadian" :value="$stat['total']" icon="bi-clock-history" /></div>
    <div class="col-6 col-lg-3"><x-stat label="Pelanggaran" :value="$stat['pelanggaran']" icon="bi-exclamation-triangle" warna="danger" /></div>
    <div class="col-6 col-lg-3"><x-stat label="Login ganda" :value="$stat['sesi_ganda']" icon="bi-people-fill" warna="warning" /></div>
    <div class="col-6 col-lg-3"><x-stat label="Ditolak masuk" :value="$stat['ditolak']" icon="bi-shield-x" warna="secondary" /></div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span>Jejak Aktivitas</span>
        <a href="{{ route('monitoring.show', $ujian) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-display me-1"></i>Kembali ke Monitoring
        </a>
    </div>

    <div class="card-body border-bottom bg-light-subtle py-2">
        <form class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label">Cari siswa</label>
                <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Nama / NISN">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Jenis kejadian</label>
                <select name="event" class="form-select form-select-sm">
                    <option value="">Semua kejadian</option>
                    <option value="pelanggaran" @selected(request('event') === 'pelanggaran')>Pelanggaran saja</option>
                    @foreach (UjianLog::EVENT as $nilai => $label)
                        <option value="{{ $nilai }}" @selected(request('event') === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Kelas</label>
                <x-pilih-kelas :ujian="$ujian" :otomatis="false" />
            </div>
            <div class="col-6 col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('monitoring.jejak', $ujian) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:3rem">#</th>
                    <th style="width:7rem">Waktu</th>
                    <th>Kejadian</th>
                    <th>Siswa</th>
                    <th>Kelas</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $i => $l)
                    <tr class="{{ $l->pelanggaran ? 'jejak-pelanggaran' : ($l->event === 'sesi_ganda' ? 'jejak-ganda' : '') }}">
                        <td class="text-muted small">{{ $items->firstItem() + $i }}</td>
                        <td class="small">
                            {{ $l->created_at->format('H:i:s') }}
                            <div class="text-muted">{{ $l->created_at->format('d/m/Y') }}</div>
                        </td>
                        <td class="small fw-semibold">
                            @if ($l->pelanggaran)<i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>@endif
                            @if ($l->event === 'sesi_ganda')<i class="bi bi-people-fill text-warning-emphasis me-1"></i>@endif
                            {{ $l->event_label }}
                        </td>
                        <td class="small">{{ $l->peserta?->siswa->nama_siswa ?? 'sistem' }}</td>
                        <td class="small">{{ $l->peserta?->rombel->nama_rombel ?? '-' }}</td>
                        <td class="small text-muted text-wrap-2">{{ $l->keterangan ?: '—' }}</td>
                    </tr>
                @empty
                    <x-kosong kolom="6" icon="bi-clock-history"
                              :pesan="request()->hasAny(['q', 'event', 'rombongan_belajar_id'])
                                  ? 'Tidak ada kejadian yang cocok dengan penyaring ini.'
                                  : 'Belum ada aktivitas tercatat pada ujian ini.'" />
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($items->hasPages())
        <div class="card-body border-top">{{ $items->links() }}</div>
    @endif
</div>

@push('head')
<style>
    /* Sama seperti pada layar monitoring: pelanggaran dan login ganda harus
       menonjol di antara jejak biasa yang jumlahnya jauh lebih banyak. */
    .jejak-pelanggaran { background:#fff5f5; border-left:3px solid var(--bs-danger); }
    .jejak-ganda { background:#fff8e6; border-left:3px solid var(--bs-warning); }
</style>
@endpush
@endsection
