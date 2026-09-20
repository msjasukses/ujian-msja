@extends('layouts.app')
@section('title', 'Registrasi Ujian')
@section('subtitle', 'Menjadwalkan pelaksanaan ujian dan mendaftarkan peserta dari kelas')

@section('content')
@php use App\Models\Ujian; use App\Support\Referensi; @endphp

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-stat label="Total ujian" :value="$stat['total']" icon="bi-calendar-check" /></div>
    <div class="col-6 col-lg-3"><x-stat label="Draft" :value="$stat['draft']" icon="bi-pencil-square" warna="secondary" /></div>
    <div class="col-6 col-lg-3"><x-stat label="Aktif" :value="$stat['aktif']" icon="bi-broadcast" warna="success" /></div>
    <div class="col-6 col-lg-3"><x-stat label="Selesai" :value="$stat['selesai']" icon="bi-check2-all" warna="info" /></div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span>Jadwal Ujian</span>
        <div class="d-flex flex-wrap gap-2">
            <x-hapus-massal :action="route('ujian.hapus-massal')" label="ujian" />
            <a href="{{ route('ujian.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Registrasi Ujian
            </a>
        </div>
    </div>

    <div class="card-body border-bottom bg-light-subtle py-2">
        <form class="row g-2 align-items-end">
            <div class="col-6 col-md-4">
                <label class="form-label">Cari</label>
                <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Nama / kode ujian">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Mata pelajaran</label>
                <select name="mata_pelajaran_id" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach (Referensi::mapel() as $m)
                        <option value="{{ $m->id }}" @selected(request('mata_pelajaran_id') == $m->id)>{{ $m->nama_mapel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach (Ujian::STATUS as $nilai => $label)
                        <option value="{{ $nilai }}" @selected(request('status') === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('ujian.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <x-pilih semua />
                    <th style="width:3rem">#</th>
                    <th>Ujian</th>
                    <th>Waktu Pelaksanaan</th>
                    <th class="text-center">Durasi</th>
                    <th class="text-center">Token</th>
                    <th class="text-center">Peserta</th>
                    <th class="text-center">Status</th>
                    <th style="width:12rem"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $i => $u)
                    <tr>
                        <x-pilih :value="$u->id" />
                        <td class="text-muted small">{{ $items->firstItem() + $i }}</td>
                        <td>
                            <div class="fw-semibold">
                                {{ $u->nama_ujian }}
                                @if ($u->is_remidial)
                                    <span class="badge text-bg-warning-subtle text-warning border border-warning-subtle">Remidial</span>
                                @endif
                                @if ($u->wajib_exambro)
                                    <span class="badge text-bg-info-subtle text-info border border-info-subtle"
                                          title="Peserta hanya bisa mengerjakan lewat aplikasi ExamBro">
                                        <i class="bi bi-shield-lock me-1"></i>ExamBro
                                    </span>
                                @endif
                            </div>
                            <div class="small text-muted">
                                {{ $u->kode_ujian }} &middot; {{ $u->mataPelajaran->nama_mapel ?? '-' }}
                                &middot; {{ $u->paketSoal->nama_paket ?? '-' }}
                            </div>
                        </td>
                        <td class="small">
                            {{ $u->waktu_mulai->format('d/m/Y H:i') }}<br>
                            <span class="text-muted">s.d. {{ $u->waktu_selesai->format('d/m/Y H:i') }}</span>
                        </td>
                        <td class="text-center small">{{ $u->durasi_menit }}'</td>
                        <td class="text-center">
                            @if ($u->token)
                                <code class="user-select-all">{{ $u->token }}</code>
                            @else
                                <span class="small text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="badge badge-soft" title="Selesai / terdaftar">
                                {{ $u->peserta_selesai_count }} / {{ $u->peserta_count }}
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge {{ ['draft' => 'text-bg-secondary', 'aktif' => 'text-bg-success', 'selesai' => 'text-bg-info'][$u->status] }}">
                                {{ $u->status_label }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('ujian.peserta', $u) }}" class="btn btn-sm btn-outline-primary" title="Peserta">
                                <i class="bi bi-people"></i>
                            </a>
                            <a href="{{ route('monitoring.show', $u) }}" class="btn btn-sm btn-outline-secondary" title="Monitoring">
                                <i class="bi bi-display"></i>
                            </a>
                            <a href="{{ route('laporan.nilai.show', $u) }}" class="btn btn-sm btn-outline-secondary" title="Nilai">
                                <i class="bi bi-card-checklist"></i>
                            </a>
                            <a href="{{ route('ujian.edit', $u) }}" class="btn btn-sm btn-outline-secondary" title="Ubah">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="{{ route('ujian.destroy', $u) }}" class="d-inline"
                                  data-konfirmasi="Hapus jadwal ujian &quot;{{ $u->nama_ujian }}&quot;?">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <x-kosong kolom="9" icon="bi-calendar-x" pesan="Belum ada ujian yang diregistrasi.">
                        <a href="{{ route('ujian.create') }}" class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-lg me-1"></i>Registrasi ujian pertama
                        </a>
                    </x-kosong>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($items->hasPages())
        <div class="card-body border-top">{{ $items->links() }}</div>
    @endif
</div>
@endsection
