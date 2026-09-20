@extends('layouts.app')
@section('title', 'Bank Soal')
@section('subtitle', 'Kumpulan butir soal semua jenis')

@section('content')
@php use App\Models\Soal; use App\Support\TeksSoal; use App\Support\Referensi; @endphp

<div class="row g-2 mb-3">
    <div class="col-6 col-lg-2"><x-stat label="Total butir" :value="$total" icon="bi-journal-text" /></div>
    @foreach (Soal::JENIS as $jenis => $label)
        <div class="col-6 col-lg-2">
            <x-stat :label="$label" :value="$stat[$jenis] ?? 0" icon="bi-dot"
                    :warna="['pg'=>'primary','pg_kompleks'=>'info','essay'=>'warning','penjodohan'=>'success','benar_salah'=>'secondary'][$jenis]" />
        </div>
    @endforeach
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span>Daftar Soal</span>
        <div class="d-flex flex-wrap gap-2">
            <x-hapus-massal :action="route('soal.hapus-massal')" label="soal" />
            <a href="{{ route('soal.import.form') }}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-upload me-1"></i>Import Word / Excel
            </a>
            <a href="{{ route('soal.export', request()->query()) }}" class="btn btn-sm btn-outline-success">
                <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
            </a>
            <a href="{{ route('soal.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Input Soal
            </a>
        </div>
    </div>

    <div class="card-body border-bottom bg-light-subtle py-2">
        <form class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label">Cari pertanyaan</label>
                <input name="q" value="{{ request('q') }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Jenis</label>
                <select name="jenis" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach (Soal::JENIS as $nilai => $label)
                        <option value="{{ $nilai }}" @selected(request('jenis') === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Topik</label>
                <select name="topik_id" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach ($daftarTopik as $t)
                        <option value="{{ $t->id }}" @selected(request('topik_id') == $t->id)>{{ Str::limit($t->nama_topik, 60) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Mapel</label>
                <select name="mata_pelajaran_id" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach (Referensi::mapel() as $m)
                        <option value="{{ $m->id }}" @selected(request('mata_pelajaran_id') == $m->id)>{{ $m->nama_mapel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('soal.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <x-pilih semua />
                    <th style="width:3rem">#</th>
                    <th>Pertanyaan</th>
                    <th>Jenis</th>
                    <th>Kunci</th>
                    <th class="text-center">Bobot</th>
                    <th>Kesukaran</th>
                    <th style="width:9rem"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $i => $s)
                    <tr class="{{ $s->is_aktif ? '' : 'opacity-50' }}">
                        <x-pilih :value="$s->id" />
                        <td class="text-muted small">{{ $items->firstItem() + $i }}</td>
                        <td>
                            <div class="text-wrap-2">{{ Str::limit(TeksSoal::polos($s->pertanyaan), 160) }}</div>
                            <div class="small text-muted">
                                {{-- Panah aman wajib: butir boleh tidak bertopik, dan ternari
                                     di belakangnya baru berjalan setelah propertinya terbaca. --}}
                                {{ $s->topik?->nama_topik ? Str::limit($s->topik->nama_topik, 45) : 'tanpa topik' }}
                                &middot; {{ $s->mataPelajaran->nama_mapel ?? 'tanpa mapel' }}
                                @if ($s->level_kognitif) &middot; {{ $s->level_kognitif }} @endif
                            </div>
                        </td>
                        <td><x-jenis-soal :jenis="$s->jenis" /></td>
                        <td class="small text-muted" style="max-width:14rem">{{ Str::limit($s->kunci_ringkas, 40) }}</td>
                        <td class="text-center">{{ rtrim(rtrim(number_format($s->bobot, 2, ',', '.'), '0'), ',') }}</td>
                        <td class="small">{{ $s->tingkat_kesukaran ? ucfirst($s->tingkat_kesukaran) : '-' }}</td>
                        <td class="text-end">
                            <a href="{{ route('soal.show', $s) }}" class="btn btn-sm btn-outline-secondary" title="Lihat">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('soal.edit', $s) }}" class="btn btn-sm btn-outline-secondary" title="Ubah">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="{{ route('soal.toggle', $s) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <button class="btn btn-sm btn-outline-secondary" title="{{ $s->is_aktif ? 'Nonaktifkan' : 'Aktifkan' }}">
                                    <i class="bi {{ $s->is_aktif ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('soal.destroy', $s) }}" class="d-inline"
                                  data-konfirmasi="Hapus butir soal ini?">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <x-kosong kolom="8" icon="bi-journal-x" pesan="Bank soal masih kosong.">
                        <a href="{{ route('soal.create') }}" class="btn btn-sm btn-primary me-1">
                            <i class="bi bi-plus-lg me-1"></i>Input manual
                        </a>
                        <a href="{{ route('soal.import.form') }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-upload me-1"></i>Import dari Word / Excel
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
