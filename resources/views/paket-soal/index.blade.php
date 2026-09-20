@extends('layouts.app')
@section('title', 'Pemilihan Soal yang Diujikan')
@section('subtitle', 'Paket soal — kumpulan butir terpilih yang siap dipasang ke jadwal ujian')

@section('content')
@php use App\Models\PaketSoal; use App\Support\Referensi; @endphp

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span>Daftar Paket Soal</span>
        <div class="d-flex flex-wrap gap-2">
            <x-hapus-massal :action="route('paket-soal.hapus-massal')" label="paket soal" />
            <a href="{{ route('paket-soal.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Buat Paket
            </a>
        </div>
    </div>

    <div class="card-body border-bottom bg-light-subtle py-2">
        <form class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label">Cari</label>
                <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Nama / kode paket">
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
            <div class="col-6 col-md-2">
                <label class="form-label">Tingkat</label>
                <select name="tingkat_kelas_id" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach (Referensi::tingkat() as $t)
                        <option value="{{ $t->id }}" @selected(request('tingkat_kelas_id') == $t->id)>{{ $t->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Jenis ujian</label>
                <select name="jenis_ujian" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach (PaketSoal::JENIS_UJIAN as $kode => $label)
                        <option value="{{ $kode }}" @selected(request('jenis_ujian') === $kode)>{{ $kode }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('paket-soal.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <x-pilih semua />
                    <th style="width:3rem">#</th>
                    <th>Paket</th>
                    <th>Mata Pelajaran</th>
                    <th>Tingkat</th>
                    <th class="text-center">Butir</th>
                    <th class="text-center">Dipakai Ujian</th>
                    <th style="width:11rem"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $i => $p)
                    <tr>
                        <x-pilih :value="$p->id" />
                        <td class="text-muted small">{{ $items->firstItem() + $i }}</td>
                        <td>
                            <a href="{{ route('paket-soal.kelola', $p) }}" class="fw-semibold text-decoration-none">{{ $p->nama_paket }}</a>
                            <div class="small text-muted">
                                {{ $p->kode_paket }}
                                @if ($p->jenis_ujian) &middot; {{ PaketSoal::JENIS_UJIAN[$p->jenis_ujian] ?? $p->jenis_ujian }} @endif
                                @if ($p->semester) &middot; {{ $p->semester }} @endif
                            </div>
                        </td>
                        <td class="small">{{ $p->mataPelajaran->nama_mapel ?? '-' }}</td>
                        <td class="small">{{ $p->tingkatKelas->nama ?? '-' }}</td>
                        <td class="text-center">
                            <span class="badge {{ $p->detail_count ? 'badge-soft' : 'text-bg-warning' }}">{{ $p->detail_count }}</span>
                        </td>
                        <td class="text-center"><span class="badge badge-soft">{{ $p->ujian_count }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('paket-soal.kelola', $p) }}" class="btn btn-sm btn-outline-primary" title="Pilih soal">
                                <i class="bi bi-list-check"></i>
                            </a>
                            <a href="{{ route('paket-soal.export', $p) }}" class="btn btn-sm btn-outline-success" title="Export naskah">
                                <i class="bi bi-file-earmark-excel"></i>
                            </a>
                            <a href="{{ route('paket-soal.edit', $p) }}" class="btn btn-sm btn-outline-secondary" title="Ubah">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="{{ route('paket-soal.destroy', $p) }}" class="d-inline"
                                  data-konfirmasi="Hapus paket &quot;{{ $p->nama_paket }}&quot;?">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <x-kosong kolom="8" icon="bi-check2-square" pesan="Belum ada paket soal.">
                        <a href="{{ route('paket-soal.create') }}" class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-lg me-1"></i>Buat paket pertama
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
