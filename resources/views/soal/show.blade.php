@extends('layouts.app')
@section('title', 'Detail Soal')

@section('content')
@php use App\Models\Soal; use App\Support\TeksSoal; @endphp

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Butir Soal #{{ $item->id }}</span>
                <x-jenis-soal :jenis="$item->jenis" />
            </div>
            <div class="card-body">
                @include('soal.partials.isi-butir', ['item' => $item])
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">Atribut</div>
            <table class="table table-sm mb-0">
                <tbody>
                    <tr><td class="text-muted">Kode</td><td>{{ $item->kode_soal ?: '-' }}</td></tr>
                    <tr><td class="text-muted">Topik</td><td>{{ $item->topik->nama_topik ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Mata pelajaran</td><td>{{ $item->mataPelajaran->nama_mapel ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Tingkat</td><td>{{ $item->tingkatKelas->nama ?? '-' }}</td></tr>
                    @if (\App\Support\Referensi::pakaiJurusan())
                        <tr><td class="text-muted">Jurusan</td><td>{{ $item->jurusan->label ?? 'Semua jurusan' }}</td></tr>
                    @endif
                    <tr><td class="text-muted">Bobot</td><td>{{ (float) $item->bobot }}</td></tr>
                    <tr><td class="text-muted">Kesukaran</td><td>{{ $item->tingkat_kesukaran ? ucfirst($item->tingkat_kesukaran) : '-' }}</td></tr>
                    <tr><td class="text-muted">Level kognitif</td><td>{{ $item->level_kognitif ?: '-' }}</td></tr>
                    <tr><td class="text-muted">Status</td>
                        <td>
                            <span class="badge {{ $item->is_aktif ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ $item->is_aktif ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="d-flex gap-2">
            {{-- Butir se-mata pelajaran buatan guru lain boleh dibaca,
                 disalin, dan disunting; pembuatnya tetap dicatat. --}}
            @if ($item->bisa_diubah)
                <a href="{{ route('soal.edit', $item) }}" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-pencil me-1"></i>Ubah
                </a>
            @endif
            @unless ($item->milik_saya)
                <span class="small text-muted flex-grow-1 align-self-center">
                    <i class="bi bi-person me-1"></i>Dibuat {{ $item->guru?->nama_ptk ?? 'guru lain' }}
                </span>
            @endunless
            <a href="{{ route('soal.index') }}" class="btn btn-outline-secondary">Kembali</a>
        </div>
    </div>
</div>
@endsection
