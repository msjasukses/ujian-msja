@extends('layouts.app')
@section('title', 'Preview Soal — '.($ujian->nama_ujian ?? $paket->nama_paket))
@section('subtitle', $paket->kode_paket.' · '.($paket->mataPelajaran->nama_mapel ?? 'semua mapel').' · '.($paket->tingkatKelas->nama ?? 'semua tingkat'))

@push('head')
<style>
    /* Mode "sembunyikan kunci": tampilan mirip yang dilihat peserta. */
    .sembunyi-kunci .penanda-kunci { display: none !important; }
    .sembunyi-kunci .opsi-kunci {
        border-color: var(--bs-border-color) !important;
        background-color: transparent !important;
    }
    .butir-preview { break-inside: avoid; }
    @media print {
        .sidebar, .sidebar-backdrop, .topbar, .no-print, .alert-dismissible { display: none !important; }
        .content-wrapper { margin-left: 0 !important; }
        .butir-preview { border: 0 !important; box-shadow: none !important; border-bottom: 1px solid #ccc !important; border-radius: 0 !important; }
    }
</style>
@endpush

@section('content')
@php
    $totalBobot = $detail->sum('bobot');
    $acakSoal = $ujian ? $ujian->acak_soal : $paket->acak_soal;
    $acakOpsi = $ujian ? $ujian->acak_opsi : $paket->acak_opsi;
@endphp

<div class="card mb-3 no-print">
    <div class="card-body d-flex flex-wrap gap-3 justify-content-between align-items-center">
        <div class="small">
            @if ($ujian)
                <div class="fw-semibold">{{ $ujian->nama_ujian }} <span class="text-muted">({{ $ujian->kode_ujian }})</span></div>
                <div class="text-muted">
                    {{ $ujian->waktu_mulai?->format('d/m/Y H:i') }} &middot; {{ $ujian->durasi_menit }} menit
                    &middot; Paket: {{ $paket->nama_paket }}
                </div>
            @else
                <div class="fw-semibold">{{ $paket->nama_paket }}</div>
            @endif
            <div class="mt-1">
                <span class="badge badge-soft">{{ $detail->count() }} butir</span>
                <span class="badge badge-soft">Total bobot {{ rtrim(rtrim(number_format($totalBobot, 2, ',', '.'), '0'), ',') }}</span>
                @if ($acakSoal)<span class="badge text-bg-info-subtle text-info">Urutan soal diacak per peserta</span>@endif
                @if ($acakOpsi)<span class="badge text-bg-info-subtle text-info">Opsi diacak per peserta</span>@endif
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <div class="form-check form-switch mb-0 me-2">
                <input class="form-check-input" type="checkbox" id="tampil-kunci" checked>
                <label class="form-check-label small" for="tampil-kunci">Tampilkan kunci &amp; pembahasan</label>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>Cetak
            </button>
            @if ($ujian)
                <a href="{{ route('ujian.index') }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
            @else
                <a href="{{ route('paket-soal.kelola', $paket) }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-list-check me-1"></i>Pilih soal
                </a>
                <a href="{{ route('paket-soal.index') }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
            @endif
        </div>
    </div>
</div>

<div id="wadah-preview">
    @forelse ($detail as $d)
        @continue (! $d->soal)
        <div class="card mb-3 butir-preview">
            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <span class="fw-semibold">Soal {{ $d->nomor_urut }}</span>
                <span class="d-flex flex-wrap gap-1 align-items-center">
                    <x-jenis-soal :jenis="$d->soal->jenis" />
                    @if ($d->soal->tingkat_kesukaran)
                        <span class="badge badge-soft">{{ ucfirst($d->soal->tingkat_kesukaran) }}</span>
                    @endif
                    @if ($d->soal->topik)
                        <span class="badge badge-soft">{{ $d->soal->topik->nama_topik }}</span>
                    @endif
                    <span class="badge badge-soft">Bobot {{ (float) $d->bobot }}</span>
                </span>
            </div>
            <div class="card-body">
                @include('soal.partials.isi-butir', ['item' => $d->soal])
            </div>
        </div>
    @empty
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                Paket ini belum berisi soal.
                <div class="mt-2">
                    <a href="{{ route('paket-soal.kelola', $paket) }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-list-check me-1"></i>Pilih soal
                    </a>
                </div>
            </div>
        </div>
    @endforelse
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const sakelar = document.getElementById('tampil-kunci');
        const wadah = document.getElementById('wadah-preview');
        const kunciSimpan = 'preview-soal-tampil-kunci';
        if (localStorage.getItem(kunciSimpan) === '0') sakelar.checked = false;
        const terapkan = () => {
            wadah.classList.toggle('sembunyi-kunci', !sakelar.checked);
            localStorage.setItem(kunciSimpan, sakelar.checked ? '1' : '0');
        };
        sakelar.addEventListener('change', terapkan);
        terapkan();
    })();
</script>
@endpush
