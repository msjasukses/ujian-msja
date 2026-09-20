@extends('layouts.app')
@section('title', 'Mata Pelajaran')
@section('subtitle', 'Sumber: aplikasi Data Center — hanya bisa dibaca dari sini')

@section('content')
@include('referensi.partials.catatan')

<div class="card">
    <div class="card-header">Daftar Mata Pelajaran ({{ $items->total() }})</div>

    <div class="card-body border-bottom bg-light-subtle py-2">
        <form class="row g-2 align-items-end">
            <div class="col-6 col-md-4">
                <label class="form-label">Cari</label>
                <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Nama / kode mapel">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Kelompok</label>
                <input name="kelompok" value="{{ request('kelompok') }}" class="form-control form-control-sm"
                       placeholder="Umum / Kejuruan">
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('referensi.mapel') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th style="width:3rem">#</th><th>Kode</th><th>Nama Mata Pelajaran</th><th>Kelompok</th>
                    <th>Tingkat</th><th>Jurusan</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($items as $i => $m)
                    <tr>
                        <td class="text-muted small">{{ $items->firstItem() + $i }}</td>
                        <td class="small font-monospace">{{ $m->kode_mapel }}</td>
                        <td>{{ $m->nama_mapel }}</td>
                        <td class="small">{{ $m->kelompok ?: '-' }}</td>
                        <td class="small">{{ $m->tingkat ?: 'semua' }}</td>
                        <td class="small">{{ $m->jurusan->nama_jurusan ?? 'semua jurusan' }}</td>
                        <td>
                            <span class="badge {{ $m->is_aktif ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ $m->is_aktif ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <x-kosong kolom="7" icon="bi-journal-bookmark" pesan="Tidak ada mata pelajaran yang cocok." />
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($items->hasPages())
        <div class="card-body border-top">{{ $items->links() }}</div>
    @endif
</div>
@endsection
