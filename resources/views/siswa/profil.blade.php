@extends('layouts.siswa')
@section('title', 'Profil Saya')

@section('content')
<div class="row justify-content-center g-3">
    <div class="col-lg-7">

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-circle me-1"></i>Identitas Saya</span>
                <a href="{{ route('siswa.ujian.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Ruang ujian
                </a>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 fw-normal text-muted">Nama</dt>
                    <dd class="col-sm-8 fw-semibold">{{ $siswa?->nama_siswa ?? '-' }}</dd>

                    <dt class="col-sm-4 fw-normal text-muted">NISN</dt>
                    <dd class="col-sm-8 font-monospace">{{ $siswa?->nisn ?: '-' }}</dd>

                    <dt class="col-sm-4 fw-normal text-muted">Kelas</dt>
                    <dd class="col-sm-8">{{ $kelas?->nama_rombel ?? '-' }}</dd>

                    <dt class="col-sm-4 fw-normal text-muted">Status</dt>
                    <dd class="col-sm-8 mb-0">
                        <span class="badge {{ $siswa?->is_aktif ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $siswa?->is_aktif ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </dd>
                </dl>
            </div>
            <div class="card-body border-top py-2 small text-muted">
                <i class="bi bi-info-circle me-1 text-primary"></i>
                Identitas dan kata sandi berasal dari data sekolah. Bila ada yang keliru atau kata
                sandi perlu diganti, hubungi wali kelas atau operator sekolah.
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history me-1"></i>Aktivitas Masuk Terakhir</div>
            <div class="card-body border-bottom py-2 small text-muted">
                Bila ada baris yang bukan Anda, segera laporkan ke pengawas atau operator sekolah.
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Perangkat</th>
                            <th>Alamat IP</th>
                            <th class="text-center">Hasil</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($riwayat as $log)
                            <tr>
                                <td class="small">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="small">{{ trim($log->browser.' '.$log->os) ?: ($log->device_type ?: '-') }}</td>
                                <td class="small font-monospace">{{ $log->ip_address ?: '-' }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $log->success ? 'text-bg-success' : 'text-bg-danger' }}">
                                        {{ $log->success ? 'Berhasil' : 'Gagal' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <x-kosong kolom="4" icon="bi-clock-history" pesan="Belum ada catatan masuk." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
@endsection
