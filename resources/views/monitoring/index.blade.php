@extends('layouts.app')
@section('title', 'Monitoring Ujian')
@section('subtitle', 'Pengawasan pelaksanaan ujian secara langsung')

@section('content')
@php use App\Models\Ujian; @endphp

<div class="card">
    <div class="card-header">Ujian yang Dapat Dipantau</div>

    <div class="card-body border-bottom bg-light-subtle py-2">
        <form class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label">Cari ujian</label>
                <input name="q" value="{{ request('q') }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Mata pelajaran</label>
                <select name="mata_pelajaran_id" class="form-select form-select-sm">
                    <option value="">Semua mapel</option>
                    @foreach ($pilihanMapel as $m)
                        <option value="{{ $m->id }}" @selected((int) request('mata_pelajaran_id') === $m->id)>{{ $m->nama_mapel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Kelas / rombel</label>
                <select name="rombongan_belajar_id" class="form-select form-select-sm">
                    <option value="">Semua kelas</option>
                    @foreach ($pilihanRombel as $k)
                        <option value="{{ $k->id }}" @selected((int) request('rombongan_belajar_id') === $k->id)>{{ $k->nama_rombel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Aktif &amp; draft</option>
                    @foreach (Ujian::STATUS as $nilai => $label)
                        <option value="{{ $nilai }}" @selected(request('status') === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('monitoring.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    {{-- Tanpa keterangan ini, jumlah terdaftar yang tiba-tiba mengecil
         terbaca seperti data yang hilang. --}}
    @if ($rombelTerpilih)
        <div class="px-3 py-2 small bg-info-subtle border-bottom">
            <i class="bi bi-info-circle me-1"></i>
            Angka pada tabel hanya menghitung peserta kelas <strong>{{ $rombelTerpilih->nama_rombel }}</strong>.
        </div>
    @endif

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:3rem">#</th>
                    <th>Ujian</th>
                    <th>Jendela Waktu</th>
                    <th class="text-center">Terdaftar</th>
                    <th class="text-center">Mengerjakan</th>
                    <th class="text-center">Selesai</th>
                    <th class="text-center" title="Siswa yang pernah tercatat melanggar selama ujian ini — satu siswa dihitung satu">Siswa Melanggar</th>
                    <th class="text-center">Status</th>
                    <th style="width:7rem"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $i => $u)
                    <tr>
                        <td class="text-muted small">{{ $items->firstItem() + $i }}</td>
                        <td>
                            <div class="fw-semibold">{{ $u->nama_ujian }}</div>
                            <div class="small text-muted">{{ $u->kode_ujian }} &middot; {{ $u->mataPelajaran->nama_mapel ?? '-' }}</div>
                        </td>
                        <td class="small">
                            {{ $u->waktu_mulai->format('d/m/Y H:i') }}
                            <span class="text-muted">— {{ $u->waktu_selesai->format('H:i') }}</span>
                        </td>
                        <td class="text-center">{{ $u->peserta_count }}</td>
                        <td class="text-center">
                            <span class="badge {{ $u->sedang_count ? 'text-bg-warning' : 'badge-soft' }}">{{ $u->sedang_count }}</span>
                        </td>
                        <td class="text-center"><span class="badge badge-soft">{{ $u->selesai_count }}</span></td>
                        <td class="text-center text-nowrap">
                            @if ($u->melanggar_count)
                                <span class="badge text-bg-danger" title="{{ $u->melanggar_count }} siswa pernah melanggar">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $u->melanggar_count }}
                                </span>
                            @else
                                <span class="badge badge-soft">0</span>
                            @endif
                            {{-- Yang terkunci ditandai tersendiri: hanya mereka yang
                                 menunggu tindakan pengawas saat ini juga. --}}
                            @if ($u->terkunci_count)
                                <div class="small text-danger mt-1" title="Lembar jawaban terkunci, menunggu dibuka pengawas">
                                    <i class="bi bi-lock-fill"></i> {{ $u->terkunci_count }} terkunci
                                </div>
                            @endif
                        </td>
                        <td class="text-center">
                            @if ($u->sedang_berlangsung)
                                <span class="badge text-bg-success"><span class="spinner-grow spinner-grow-sm me-1"></span>Berlangsung</span>
                            @else
                                <span class="badge badge-soft">{{ $u->status_label }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('monitoring.show', $rombelTerpilih ? [$u, 'rombongan_belajar_id' => $rombelTerpilih->id] : $u) }}"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-display me-1"></i>Pantau
                            </a>
                        </td>
                    </tr>
                @empty
                    <x-kosong kolom="9" icon="bi-display" pesan="Tidak ada ujian yang cocok dengan penyaring ini." />
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($items->hasPages())
        <div class="card-body border-top">{{ $items->links() }}</div>
    @endif
</div>
@endsection
