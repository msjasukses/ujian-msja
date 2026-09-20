@extends('layouts.app')
@section('title', 'Data Siswa')
@section('subtitle', 'Sumber: aplikasi Data Center — hanya bisa dibaca dari sini')

@section('content')
@php use App\Support\Referensi; @endphp

@include('referensi.partials.catatan')

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Daftar Siswa ({{ $items->total() }})</span>
        <a href="{{ route('referensi.siswa.export', request()->query()) }}" class="btn btn-sm btn-outline-success">
            <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
        </a>
    </div>

    <div class="card-body border-bottom bg-light-subtle py-2">
        <form class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label">Cari</label>
                <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Nama / NISN / NIS">
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
            <div class="col-6 col-md-2">
                <label class="form-label">L/P</label>
                <select name="jenis_kelamin" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="L" @selected(request('jenis_kelamin') === 'L')>Laki-laki</option>
                    <option value="P" @selected(request('jenis_kelamin') === 'P')>Perempuan</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    @foreach (['Aktif', 'Lulus', 'Keluar'] as $s)
                        <option value="{{ $s }}" @selected(request('status', 'Aktif') === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('referensi.siswa') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:3rem">#</th><th>NISN</th><th>NIS</th><th>Nama Siswa</th>
                    <th>L/P</th><th>Kelas</th><th>Tempat, Tanggal Lahir</th><th>No. HP</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $i => $s)
                    <tr>
                        <td class="text-muted small">{{ $items->firstItem() + $i }}</td>
                        <td class="small font-monospace">{{ $s->nisn }}</td>
                        <td class="small">{{ $s->nis ?: '-' }}</td>
                        <td>{{ $s->nama_siswa }}</td>
                        <td class="small">{{ $s->jenis_kelamin ?: '-' }}</td>
                        <td class="small">{{ $kelas[$s->id] ?? '-' }}</td>
                        <td class="small text-muted">
                            {{ $s->tempat_lahir ?: '-' }}{{ $s->tanggal_lahir ? ', '.$s->tanggal_lahir->format('d/m/Y') : '' }}
                        </td>
                        <td class="small">{{ $s->nomor_hp ?: '-' }}</td>
                        <td>
                            <span class="badge {{ $s->status_siswa === 'Aktif' ? 'text-bg-success' : 'badge-soft' }}">
                                {{ $s->status_siswa }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <x-kosong kolom="9" icon="bi-people" pesan="Tidak ada data siswa yang cocok." />
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($items->hasPages())
        <div class="card-body border-top">{{ $items->links() }}</div>
    @endif
</div>
@endsection
