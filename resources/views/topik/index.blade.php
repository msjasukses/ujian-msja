@extends('layouts.app')
@section('title', 'Topik')
@section('subtitle', 'Materi/topik payung butir soal — bisa diinput manual atau ditarik dari CP-TP-ATP kurikulum')

@section('content')
@php use App\Models\Topik; use App\Support\Referensi; @endphp

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-stat label="Total topik" :value="$stat['total']" icon="bi-diagram-3" /></div>
    <div class="col-6 col-lg-3"><x-stat label="Input manual" :value="$stat['manual']" icon="bi-pencil-square" warna="info" /></div>
    <div class="col-6 col-lg-3"><x-stat label="Hasil sinkron CP-TP-ATP" :value="$stat['sinkron']" icon="bi-arrow-repeat" warna="success" /></div>
    <div class="col-6 col-lg-3"><x-stat label="Belum punya soal" :value="$stat['tanpa_soal']" icon="bi-exclamation-circle" warna="warning" /></div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span>Daftar Topik</span>
        <div class="d-flex flex-wrap gap-2">
            <x-hapus-massal :action="route('topik.hapus-massal')" label="topik" />
            <a href="{{ route('topik.sinkron') }}" class="btn btn-sm btn-success">
                <i class="bi bi-arrow-repeat me-1"></i>Sinkron CP-TP-ATP
            </a>
            <a href="{{ route('topik.export', request()->query()) }}" class="btn btn-sm btn-outline-success">
                <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
            </a>
            <a href="{{ route('topik.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Tambah Topik
            </a>
        </div>
    </div>

    <div class="card-body border-bottom bg-light-subtle py-2">
        <form class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label">Cari</label>
                <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Nama topik / elemen">
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
                <label class="form-label">Sumber</label>
                <select name="sumber" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="manual" @selected(request('sumber') === 'manual')>Manual</option>
                    <option value="sinkron" @selected(request('sumber') === 'sinkron')>Sinkron CP-TP-ATP</option>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('topik.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <x-pilih semua />
                    <th style="width:3rem">#</th>
                    <th>Topik</th>
                    <th>Mata Pelajaran</th>
                    <th>Tingkat / Fase</th>
                    <th>Semester</th>
                    <th class="text-center">Soal</th>
                    <th>Sumber</th>
                    <th style="width:7rem"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $i => $t)
                    <tr>
                        <x-pilih :value="$t->id" />
                        <td class="text-muted small">{{ $items->firstItem() + $i }}</td>
                        <td>
                            <div class="fw-semibold text-wrap-2">{{ $t->nama_topik }}</div>
                            <div class="small text-muted">
                                {{ $t->kode_topik }}
                                @if ($t->elemen) &middot; Elemen: {{ Str::limit(strip_tags($t->elemen), 60) }} @endif
                            </div>
                        </td>
                        <td class="small">{{ $t->mataPelajaran->nama_mapel ?? '-' }}</td>
                        <td class="small">{{ $t->tingkatKelas->nama ?? '-' }}{{ $t->fase ? ' / Fase '.$t->fase : '' }}</td>
                        <td class="small">{{ $t->semester ?: '-' }}</td>
                        <td class="text-center">
                            <a href="{{ route('soal.index', ['topik_id' => $t->id]) }}" class="badge badge-soft text-decoration-none">
                                {{ $t->soal_count }}
                            </a>
                        </td>
                        <td>
                            @if ($t->dari_sinkron)
                                <span class="badge text-bg-success-subtle text-success border border-success-subtle"
                                      title="Disinkronkan {{ $t->disinkron_pada?->format('d/m/Y H:i') }}">
                                    <i class="bi bi-arrow-repeat me-1"></i>CP-TP-ATP
                                </span>
                            @else
                                <span class="badge badge-soft">Manual</span>
                            @endif
                            @unless ($t->is_aktif)
                                <span class="badge text-bg-secondary">Nonaktif</span>
                            @endunless
                        </td>
                        <td class="text-end">
                            <a href="{{ route('topik.edit', $t) }}" class="btn btn-sm btn-outline-secondary" title="Ubah">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="{{ route('topik.destroy', $t) }}" class="d-inline"
                                  data-konfirmasi="Hapus topik &quot;{{ $t->nama_topik }}&quot;?">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <x-kosong kolom="9" pesan="Belum ada topik.">
                        <a href="{{ route('topik.sinkron') }}" class="btn btn-sm btn-success">
                            <i class="bi bi-arrow-repeat me-1"></i>Tarik dari CP-TP-ATP kurikulum
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
