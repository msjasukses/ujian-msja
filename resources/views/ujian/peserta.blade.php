@extends('layouts.app')
@section('title', 'Peserta — '.$ujian->nama_ujian)
@section('subtitle', $ujian->kode_ujian.' · '.$ujian->waktu_mulai->format('d/m/Y H:i').' · durasi '.$ujian->durasi_menit.' menit')

@section('content')
@php use App\Models\UjianPeserta; use App\Support\Referensi; @endphp

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <div class="d-flex flex-wrap gap-3 align-items-center">
            <span class="badge badge-soft fs-6">
                Token: <code class="user-select-all">{{ $ujian->token ?: 'tanpa token' }}</code>
            </span>
            <form method="POST" action="{{ route('ujian.token', $ujian) }}"
                  data-konfirmasi="Buat token baru? Token lama langsung tidak berlaku.">
                @csrf @method('PATCH')
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-clockwise me-1"></i>Token baru</button>
            </form>

            <form method="POST" action="{{ route('ujian.status', $ujian) }}" class="d-flex gap-2 align-items-center">
                @csrf @method('PATCH')
                <select name="status" class="form-select form-select-sm" style="width:auto">
                    @foreach (\App\Models\Ujian::STATUS as $nilai => $label)
                        <option value="{{ $nilai }}" @selected($ujian->status === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-outline-primary">Ubah status</button>
            </form>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <form method="POST" action="{{ route('ujian.peserta.sinkron', $ujian) }}"
                  data-konfirmasi="Sinkronkan daftar peserta dengan data kelas terbaru di Data Center?">
                @csrf
                <button class="btn btn-sm btn-success"><i class="bi bi-arrow-repeat me-1"></i>Sinkron Peserta</button>
            </form>
            <a href="{{ route('ujian.peserta.export', $ujian) }}" class="btn btn-sm btn-outline-success">
                <i class="bi bi-file-earmark-excel me-1"></i>Export Daftar Hadir
            </a>
            <a href="{{ route('ujian.edit', $ujian) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-pencil me-1"></i>Ubah Ujian
            </a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Daftar Peserta ({{ $items->total() }})</div>

    <div class="card-body border-bottom bg-light-subtle py-2">
        <form class="row g-2 align-items-end">
            <div class="col-6 col-md-4">
                <label class="form-label">Cari siswa</label>
                <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Nama / NISN">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Kelas</label>
                <select name="rombongan_belajar_id" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach (Referensi::rombel() as $k)
                        <option value="{{ $k->id }}" @selected(request('rombongan_belajar_id') == $k->id)>{{ $k->nama_rombel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach (UjianPeserta::STATUS as $nilai => $label)
                        <option value="{{ $nilai }}" @selected(request('status') === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('ujian.peserta', $ujian) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
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
                    <th>No. Peserta</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Nilai</th>
                    <th style="width:6rem"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $i => $p)
                    <tr class="{{ $p->status === UjianPeserta::DIBATALKAN ? 'opacity-50' : '' }}">
                        <td class="text-muted small">{{ $items->firstItem() + $i }}</td>
                        <td class="small">{{ $p->siswa->nisn ?? '-' }}</td>
                        <td>{{ $p->siswa->nama_siswa ?? '—' }}</td>
                        <td class="small">{{ $p->rombel->nama_rombel ?? '-' }}</td>
                        <td class="small">{{ $p->nomor_peserta ?: '-' }}</td>
                        <td class="text-center">
                            <span class="badge {{ ['terdaftar' => 'badge-soft', 'mulai' => 'text-bg-warning',
                                                   'selesai' => 'text-bg-success', 'dibatalkan' => 'text-bg-danger'][$p->status] }}">
                                {{ $p->status_label }}
                            </span>
                        </td>
                        <td class="text-center">{{ $p->nilai !== null ? (float) $p->nilai : '-' }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('ujian.peserta.toggle', [$ujian, $p]) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <button class="btn btn-sm btn-outline-secondary"
                                        title="{{ $p->status === UjianPeserta::DIBATALKAN ? 'Aktifkan kembali' : 'Batalkan keikutsertaan' }}">
                                    <i class="bi {{ $p->status === UjianPeserta::DIBATALKAN ? 'bi-arrow-counterclockwise' : 'bi-slash-circle' }}"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <x-kosong kolom="8" icon="bi-people" pesan="Belum ada peserta terdaftar.">
                        <form method="POST" action="{{ route('ujian.peserta.sinkron', $ujian) }}">
                            @csrf
                            <button class="btn btn-sm btn-success"><i class="bi bi-arrow-repeat me-1"></i>Tarik peserta dari kelas</button>
                        </form>
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
