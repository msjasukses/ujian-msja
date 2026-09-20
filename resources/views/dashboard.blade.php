@extends('layouts.app')
@section('title', 'Dashboard')
@section('subtitle', 'Ringkasan penyusunan soal dan pelaksanaan ujian')

@section('content')
@php use App\Models\Soal; use App\Support\Pengguna; @endphp

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <x-stat label="Topik" :value="$stat['topik']" icon="bi-diagram-3" warna="primary" />
    </div>
    <div class="col-6 col-lg-3">
        <x-stat label="Butir Soal" :value="$stat['soal']" icon="bi-journal-text" warna="info" />
    </div>
    <div class="col-6 col-lg-3">
        <x-stat label="Paket Soal" :value="$stat['paket']" icon="bi-check2-square" warna="success" />
    </div>
    <div class="col-6 col-lg-3">
        <x-stat label="Ujian Aktif" :value="$stat['ujian_aktif']" icon="bi-broadcast" warna="warning" />
    </div>
</div>

@if ($sedangBerlangsung->isNotEmpty())
    <div class="card mb-3 border-warning-subtle">
        <div class="card-header d-flex align-items-center gap-2">
            <span class="spinner-grow spinner-grow-sm text-warning"></span> Sedang berlangsung sekarang
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Ujian</th><th>Mata Pelajaran</th><th class="text-center">Peserta</th>
                        <th class="text-center">Mengerjakan</th><th class="text-center">Selesai</th>
                        <th class="text-end">Berakhir</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sedangBerlangsung as $u)
                        <tr>
                            <td><div class="fw-semibold">{{ $u->nama_ujian }}</div>
                                <div class="small text-muted">{{ $u->kode_ujian }}</div></td>
                            <td>{{ $u->mataPelajaran->nama_mapel ?? '-' }}</td>
                            <td class="text-center">{{ $u->peserta_count }}</td>
                            <td class="text-center"><span class="badge text-bg-warning">{{ $u->sedang_count }}</span></td>
                            <td class="text-center"><span class="badge text-bg-success">{{ $u->selesai_count }}</span></td>
                            <td class="text-end small">{{ $u->waktu_selesai->format('H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('monitoring.show', $u) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-display me-1"></i>Pantau
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Ujian terdekat</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr><th>Ujian</th><th>Waktu</th><th class="text-center">Peserta</th><th class="text-center">Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($ujianTerdekat as $u)
                            <tr>
                                <td>
                                    <a href="{{ route('ujian.edit', $u) }}" class="fw-semibold text-decoration-none">{{ $u->nama_ujian }}</a>
                                    <div class="small text-muted">{{ $u->mataPelajaran->nama_mapel ?? '-' }}</div>
                                </td>
                                <td class="small">{{ $u->waktu_mulai->format('d/m/Y H:i') }}</td>
                                <td class="text-center">{{ $u->peserta_count }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $u->status === 'aktif' ? 'text-bg-success' : 'badge-soft' }}">
                                        {{ $u->status_label }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <x-kosong kolom="4" pesan="Belum ada ujian yang dijadwalkan.">
                                <a href="{{ route('ujian.create') }}" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-lg me-1"></i>Registrasi Ujian
                                </a>
                            </x-kosong>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Komposisi bank soal</div>
            <div class="card-body">
                @php $totalSoal = max(1, array_sum($soalPerJenis)); @endphp
                @foreach (Soal::JENIS as $jenis => $label)
                    @php $jumlah = $soalPerJenis[$jenis] ?? 0; @endphp
                    <div class="d-flex justify-content-between small mb-1">
                        <span>{{ $label }}</span>
                        <span class="text-muted">{{ $jumlah }}</span>
                    </div>
                    <div class="progress mb-3" style="height:.45rem">
                        <div class="progress-bar" style="width:{{ round($jumlah / $totalSoal * 100) }}%"></div>
                    </div>
                @endforeach

                <a href="{{ route('soal.create') }}" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-plus-lg me-1"></i>Tambah soal
                </a>
            </div>
        </div>
    </div>
</div>

@if (Pengguna::isAdmin() && $loginTerakhir->isNotEmpty())
    <div class="card mt-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Aktivitas login terakhir</span>
            <a href="{{ route('log-login.index') }}" class="btn btn-sm btn-outline-secondary">Lihat semua</a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>Waktu</th><th>Username</th><th>Peran</th><th>Hasil</th><th>IP</th><th>Perangkat</th></tr></thead>
                <tbody>
                    @foreach ($loginTerakhir as $l)
                        <tr>
                            <td class="small">{{ $l->created_at->format('d/m H:i') }}</td>
                            <td class="small">{{ $l->username }}</td>
                            <td class="small">{{ $l->guard_label }}</td>
                            <td>
                                <span class="badge {{ $l->success ? 'text-bg-success' : 'text-bg-danger' }}">
                                    {{ $l->success ? 'Berhasil' : 'Gagal' }}
                                </span>
                            </td>
                            <td class="small text-muted">{{ $l->ip_address }}</td>
                            <td class="small text-muted">{{ $l->browser }} / {{ $l->os }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
