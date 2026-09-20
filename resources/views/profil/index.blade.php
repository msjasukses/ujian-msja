@extends('layouts.app')
@section('title', 'Profil Saya')
@section('subtitle', 'Data akun yang sedang dipakai masuk')

@section('content')
@php use App\Support\Pengguna; @endphp

<div class="row g-3 justify-content-center">
    <div class="col-lg-7">

        {{-- ---------- Identitas ---------- --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-circle me-1"></i>Identitas</span>
                <span class="badge badge-soft">{{ Pengguna::peran() }}</span>
            </div>

            @if ($bisaDiubah)
                <form method="POST" action="{{ route('profil.update') }}">
                    @csrf @method('PATCH')
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Nama <span class="text-danger">*</span></label>
                            <input name="name" value="{{ old('name', $item->name) }}"
                                   class="form-control @error('name') is-invalid @enderror" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-0">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" value="{{ old('email', $item->email) }}"
                                   class="form-control @error('email') is-invalid @enderror" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Email ini sekaligus username saat masuk.</div>
                        </div>
                    </div>
                    <div class="card-body border-top text-end py-2">
                        <button class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
                    </div>
                </form>
            @else
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 fw-normal text-muted">Nama</dt>
                        <dd class="col-sm-8 fw-semibold">{{ $item->nama_ptk }}</dd>

                        <dt class="col-sm-4 fw-normal text-muted">NIP</dt>
                        <dd class="col-sm-8 font-monospace">{{ $item->nip ?: '-' }}</dd>

                        <dt class="col-sm-4 fw-normal text-muted">Mata pelajaran</dt>
                        <dd class="col-sm-8">{{ $item->mataPelajaran->nama_mapel ?? '-' }}</dd>

                        <dt class="col-sm-4 fw-normal text-muted">Status</dt>
                        <dd class="col-sm-8">
                            <span class="badge {{ $item->is_aktif ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ $item->is_aktif ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </dd>
                    </dl>
                </div>
                <div class="card-body border-top py-2 small text-muted">
                    <i class="bi bi-info-circle me-1 text-primary"></i>
                    Data dan kata sandi guru berasal dari aplikasi Data Center, jadi perubahannya
                    dilakukan di sana — bukan di aplikasi ujian ini.
                </div>
            @endif
        </div>

        {{-- ---------- Kata sandi ---------- --}}
        @if ($bisaDiubah)
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-key me-1"></i>Ubah Kata Sandi</div>
                <form method="POST" action="{{ route('profil.sandi') }}">
                    @csrf @method('PUT')
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Kata sandi saat ini <span class="text-danger">*</span></label>
                            <input type="password" name="password_lama"
                                   class="form-control @error('password_lama') is-invalid @enderror" required>
                            @error('password_lama')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Kata sandi baru <span class="text-danger">*</span></label>
                            <input type="password" name="password"
                                   class="form-control @error('password') is-invalid @enderror" required>
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Minimal 8 karakter.</div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label">Ulangi kata sandi baru <span class="text-danger">*</span></label>
                            <input type="password" name="password_confirmation" class="form-control" required>
                        </div>
                    </div>
                    <div class="card-body border-top text-end py-2">
                        <button class="btn btn-sm btn-primary"><i class="bi bi-shield-lock me-1"></i>Ubah kata sandi</button>
                    </div>
                </form>
            </div>
        @endif

        {{-- ---------- Riwayat masuk ---------- --}}
        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history me-1"></i>Aktivitas Masuk Terakhir</div>
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
                            <x-kosong kolom="4" icon="bi-clock-history" pesan="Belum ada catatan masuk untuk akun ini." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
@endsection
