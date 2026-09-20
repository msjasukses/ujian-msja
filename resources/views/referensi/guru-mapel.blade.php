@extends('layouts.app')
@section('title', 'Data Guru Mata Pelajaran')
@section('subtitle', 'Penugasan guru mengajar mapel di rombel, sumber: Data Center')

@section('content')
@php use App\Support\Referensi; @endphp

@include('referensi.partials.catatan')

<div class="card">
    <div class="card-header">Penugasan Mengajar ({{ $items->total() }})</div>

    <div class="card-body border-bottom bg-light-subtle py-2">
        <form class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label">Guru</label>
                <select name="guru_id" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach (Referensi::guru() as $g)
                        <option value="{{ $g->id }}" @selected(request('guru_id') == $g->id)>{{ $g->nama_ptk }}</option>
                    @endforeach
                </select>
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
                <a href="{{ route('referensi.guru-mapel') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th style="width:3rem">#</th><th>Guru</th><th>NIP</th><th>Mata Pelajaran</th><th>Rombel</th><th>Tahun Ajaran</th></tr>
            </thead>
            <tbody>
                @forelse ($items as $i => $gm)
                    <tr>
                        <td class="text-muted small">{{ $items->firstItem() + $i }}</td>
                        <td>{{ $gm->guru->nama_ptk ?? '-' }}</td>
                        <td class="small font-monospace">{{ $gm->guru->nip ?? '-' }}</td>
                        <td class="small">{{ $gm->mataPelajaran->nama_mapel ?? '-' }}</td>
                        <td class="small">{{ $gm->rombel->nama_rombel ?? 'semua rombel' }}</td>
                        <td class="small">{{ $gm->tahunAjaran->nama_tahun_ajaran ?? '-' }}</td>
                    </tr>
                @empty
                    <x-kosong kolom="6" icon="bi-easel" pesan="Tidak ada penugasan mengajar yang cocok." />
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($items->hasPages())
        <div class="card-body border-top">{{ $items->links() }}</div>
    @endif
</div>
@endsection
