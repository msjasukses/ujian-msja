@extends('layouts.app')
@section('title', 'Sinkron CP-TP-ATP')
@section('subtitle', 'Menarik pemetaan CP-TP-ATP dari aplikasi SIM Kurikulum menjadi topik ujian')

@section('content')
@php use App\Support\Referensi; @endphp

@unless ($tersedia)
    <div class="alert alert-warning">
        <h6 class="alert-heading"><i class="bi bi-plug me-1"></i>Database kurikulum belum terhubung</h6>
        <p class="mb-2 small">
            Fitur ini membaca tabel <code>pemetaan_cp_tp_atp</code> pada database aplikasi SIM Kurikulum.
            Isi kredensialnya pada berkas <code>.env</code>:
        </p>
        <pre class="small bg-white border rounded p-2 mb-0">KURIKULUM_DB_HOST=127.0.0.1
KURIKULUM_DB_PORT=3306
KURIKULUM_DB_DATABASE=kurikulum
KURIKULUM_DB_USERNAME=root
KURIKULUM_DB_PASSWORD=</pre>
    </div>
    <a href="{{ route('topik.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
@else

<div class="alert alert-info py-2 small d-flex gap-2">
    <i class="bi bi-info-circle mt-1"></i>
    <div>
        Baris yang sudah pernah ditarik akan <strong>diperbarui</strong>, bukan digandakan — jadi sinkron aman
        dijalankan berulang kali. Judul topik diambil dari Tujuan Pembelajaran; bila kosong dipakai Elemen,
        lalu Capaian Pembelajaran.
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form class="row g-2 align-items-end">
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
                <label class="form-label">Semester</label>
                <select name="semester" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach (Referensi::semester() as $nilai => $label)
                        <option value="{{ $nilai }}" @selected(request('semester') === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Tahun ajaran (kurikulum)</label>
                <select name="tahun_ajaran" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach ($tahunAjaranKurikulum as $ta)
                        <option value="{{ $ta }}" @selected(request('tahun_ajaran') === $ta)>{{ $ta }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Tampilkan</button>
                <a href="{{ route('topik.sinkron') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<form method="POST" action="{{ route('topik.sinkron.jalankan') }}">
    @csrf
    @foreach (request()->only(['mata_pelajaran_id', 'tingkat_kelas_id', 'semester', 'tahun_ajaran']) as $k => $v)
        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
    @endforeach

    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <span>Pemetaan CP-TP-ATP ditemukan: <strong>{{ $items->count() }}</strong> baris</span>
            <div class="d-flex gap-2">
                <a href="{{ route('topik.index') }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
                <button class="btn btn-sm btn-success" @disabled($items->isEmpty())
                        data-konfirmasi-tombol="1">
                    <i class="bi bi-arrow-repeat me-1"></i>Jalankan Sinkron
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:2.5rem">
                            <input type="checkbox" class="form-check-input" id="pilihSemua" checked>
                        </th>
                        <th>Calon Topik</th>
                        <th>Mata Pelajaran</th>
                        <th>Tingkat / Fase</th>
                        <th>Semester</th>
                        <th>Elemen</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $p)
                        <tr class="{{ $p->nama_topik === null ? 'table-warning' : '' }}">
                            <td>
                                <input type="checkbox" name="ids[]" value="{{ $p->id }}"
                                       class="form-check-input pilih-baris" checked @disabled($p->nama_topik === null)>
                            </td>
                            <td>
                                <div class="fw-semibold text-wrap-2">
                                    {{ $p->nama_topik ?? '(tidak punya TP / elemen — akan dilewati)' }}
                                </div>
                                @if ($p->tujuan_pembelajaran)
                                    <div class="small text-muted text-wrap-2">TP: {{ Str::limit(strip_tags($p->tujuan_pembelajaran), 140) }}</div>
                                @endif
                            </td>
                            <td class="small">{{ $p->nama_mapel }}</td>
                            <td class="small">{{ $p->nama_tingkat }}{{ $p->fase ? ' / '.$p->fase : '' }}</td>
                            <td class="small">{{ $p->semester ?: '-' }}</td>
                            <td class="small text-muted text-wrap-2">{{ Str::limit(strip_tags((string) $p->elemen), 80) }}</td>
                            <td>
                                @if ($p->sudah_disinkron)
                                    <span class="badge badge-soft" title="Terakhir {{ $p->disinkron_pada }}">Sudah ditarik</span>
                                @else
                                    <span class="badge text-bg-success-subtle text-success border border-success-subtle">Baru</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-kosong kolom="7" icon="bi-diagram-2"
                                  pesan="Tidak ada pemetaan CP-TP-ATP yang cocok dengan filter di atas." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</form>

@push('scripts')
<script>
    const pilihSemua = document.getElementById('pilihSemua');
    const baris = () => document.querySelectorAll('.pilih-baris:not(:disabled)');

    pilihSemua?.addEventListener('change', function () {
        baris().forEach(cb => cb.checked = this.checked);
    });
</script>
@endpush

@endunless
@endsection
