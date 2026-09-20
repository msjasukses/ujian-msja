@extends('layouts.app')
@section('title', 'Data Wali Kelas')
@section('subtitle', 'Penunjukan wali kelas menempel pada rombongan belajar di Data Center')

@section('content')
@php use App\Support\Referensi; @endphp

@include('referensi.partials.catatan')

<div class="card">
    <div class="card-header">Rombongan Belajar &amp; Wali Kelasnya ({{ $items->total() }})</div>

    <div class="card-body border-bottom bg-light-subtle py-2">
        <form class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label">Cari kelas</label>
                <input name="q" value="{{ request('q') }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Tahun ajaran</label>
                <select name="tahun_ajaran_id" class="form-select form-select-sm">
                    <option value="">Tahun ajaran aktif</option>
                    @foreach (Referensi::tahunAjaran() as $ta)
                        <option value="{{ $ta->id }}" @selected(request('tahun_ajaran_id') == $ta->id)>{{ $ta->nama_tahun_ajaran }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('referensi.wali-kelas') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:3rem">#</th><th>Rombel</th><th>Tingkat</th><th>Jurusan</th>
                    <th>Wali Kelas</th><th>NIP Wali</th><th class="text-center">Jumlah Siswa</th><th>Tahun Ajaran</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $i => $k)
                    <tr>
                        <td class="text-muted small">{{ $items->firstItem() + $i }}</td>
                        <td class="fw-semibold">{{ $k->nama_rombel }}</td>
                        <td class="small">{{ $k->tingkat }}</td>
                        <td class="small">{{ $k->jurusan->nama_jurusan ?? '-' }}</td>
                        <td>
                            @if ($k->waliKelas)
                                {{ $k->waliKelas->nama_ptk }}
                            @else
                                <span class="badge text-bg-warning">belum ditunjuk</span>
                            @endif
                        </td>
                        <td class="small font-monospace">{{ $k->waliKelas->nip ?? '-' }}</td>
                        <td class="text-center"><span class="badge badge-soft">{{ $k->siswa_rombel_count }}</span></td>
                        <td class="small">{{ $k->tahunAjaran->nama_tahun_ajaran ?? '-' }}</td>
                    </tr>
                @empty
                    <x-kosong kolom="8" icon="bi-person-check" pesan="Tidak ada rombongan belajar yang cocok." />
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($items->hasPages())
        <div class="card-body border-top">{{ $items->links() }}</div>
    @endif
</div>
@endsection
