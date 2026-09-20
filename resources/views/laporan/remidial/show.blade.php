@extends('layouts.app')
@section('title', 'Remidial — '.$ujian->nama_ujian)
@section('subtitle', $ujian->kode_ujian.' · '.($ujian->mataPelajaran->nama_mapel ?? '-').' · KKM '.(float) $ujian->kkm)

@section('content')

@include('laporan.partials.tindak-lanjut', compact('ujian', 'daftar', 'bentuk', 'jenis', 'routeDasar'))

@if ($daftar->isNotEmpty())
    <div class="card mt-3 border-primary-subtle">
        <div class="card-header"><i class="bi bi-calendar-plus me-1"></i>Buat Jadwal Ujian Remidial</div>
        <form method="POST" action="{{ route('laporan.remidial.buat-ujian', $ujian) }}" class="card-body">
            @csrf
            <p class="small text-muted">
                Membuat jadwal ujian baru berstatus <strong>Draft</strong> yang pesertanya hanya
                {{ $daftar->count() }} siswa di atas. Naskahnya memakai paket soal yang sama, kecuali Anda memilih paket lain.
            </p>
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Waktu mulai <span class="text-danger">*</span></label>
                    <input type="datetime-local" name="waktu_mulai" required
                           value="{{ now()->addWeek()->setTime(7, 30)->format('Y-m-d\TH:i') }}"
                           class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Waktu selesai <span class="text-danger">*</span></label>
                    <input type="datetime-local" name="waktu_selesai" required
                           value="{{ now()->addWeek()->setTime(9, 30)->format('Y-m-d\TH:i') }}"
                           class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Durasi (menit)</label>
                    <input type="number" name="durasi_menit" value="{{ $ujian->durasi_menit }}" min="5" max="600"
                           class="form-control form-control-sm" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Paket soal remidial</label>
                    <select name="paket_soal_id" class="form-select form-select-sm">
                        <option value="">Pakai paket yang sama ({{ $ujian->paketSoal->nama_paket ?? '-' }})</option>
                        @foreach (\App\Models\PaketSoal::aktif()->orderBy('nama_paket')->get() as $p)
                            <option value="{{ $p->id }}">{{ $p->nama_paket }} ({{ $p->kode_paket }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary btn-sm">
                        <i class="bi bi-calendar-plus me-1"></i>Buat Ujian Remidial
                    </button>
                </div>
            </div>
        </form>
    </div>
@endif

<div class="mt-3">
    <a href="{{ route('laporan.remidial.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>
@endsection
