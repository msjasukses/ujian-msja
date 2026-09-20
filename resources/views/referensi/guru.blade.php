@extends('layouts.app')
@section('title', 'Data Guru')
@section('subtitle', 'Sumber: aplikasi Data Center — hanya bisa dibaca dari sini')

@section('content')
@include('referensi.partials.catatan')

<div class="card">
    <div class="card-header">Daftar Guru / PTK ({{ $items->total() }})</div>

    <div class="card-body border-bottom bg-light-subtle py-2">
        <form class="row g-2 align-items-end">
            <div class="col-6 col-md-4">
                <label class="form-label">Cari</label>
                <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Nama / NIP / NUPTK">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Status kepegawaian</label>
                <input name="status_kepegawaian" value="{{ request('status_kepegawaian') }}"
                       class="form-control form-control-sm" placeholder="mis. PNS">
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('referensi.guru') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:3rem">#</th><th>NIP</th><th>NUPTK</th><th>Nama PTK</th><th>L/P</th>
                    <th>Mata Pelajaran</th><th>Jabatan</th><th>Status</th><th>Kontak</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $i => $g)
                    <tr>
                        <td class="text-muted small">{{ $items->firstItem() + $i }}</td>
                        <td class="small font-monospace">{{ $g->nip }}</td>
                        <td class="small font-monospace">{{ $g->nuptk ?: '-' }}</td>
                        <td>{{ $g->nama_ptk }}</td>
                        <td class="small">{{ $g->jenis_kelamin ?: '-' }}</td>
                        <td class="small">{{ $g->mataPelajaran->nama_mapel ?? '-' }}</td>
                        <td class="small">{{ $g->jabatan ?: '-' }}</td>
                        <td>
                            <span class="badge badge-soft">{{ $g->status_kepegawaian ?: '-' }}</span>
                            @unless ($g->is_aktif)<span class="badge text-bg-secondary">Nonaktif</span>@endunless
                        </td>
                        <td class="small text-muted">{{ $g->nomor_hp ?: $g->email ?: '-' }}</td>
                    </tr>
                @empty
                    <x-kosong kolom="9" icon="bi-person-badge" pesan="Tidak ada data guru yang cocok." />
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($items->hasPages())
        <div class="card-body border-top">{{ $items->links() }}</div>
    @endif
</div>
@endsection
