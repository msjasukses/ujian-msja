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
                <div class="soal-body mb-4" dir="auto">{!! TeksSoal::html($item->pertanyaan) !!}</div>

                @switch ($item->jenis)
                    @case (Soal::PENJODOHAN)
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="small text-muted mb-2">Pernyataan</div>
                                @foreach ($item->opsiPilihan() as $kiri)
                                    <div class="border rounded p-2 mb-2">
                                        <strong>{{ $kiri['key'] }}.</strong> <span class="opsi-teks" dir="auto">{!! TeksSoal::html($kiri['text']) !!}</span>
                                        <span class="badge text-bg-success-subtle text-success ms-1">
                                            &rarr; {{ $item->kunci[$kiri['key']] ?? '-' }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                            <div class="col-md-6">
                                <div class="small text-muted mb-2">Pasangan</div>
                                @foreach ($item->opsiKanan() as $kanan)
                                    <div class="border rounded p-2 mb-2">
                                        <strong>{{ $kanan['key'] }}.</strong> <span class="opsi-teks" dir="auto">{!! TeksSoal::html($kanan['text']) !!}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @break

                    @case (Soal::ESSAY)
                        <div class="border rounded p-3 bg-light-subtle">
                            <div class="small text-muted mb-1">Jawaban model</div>
                            <div>{!! TeksSoal::html($item->kunci['jawaban'] ?? '-') !!}</div>
                            @if (! empty($item->kunci['kata_kunci']))
                                <div class="mt-2">
                                    <span class="small text-muted me-1">Kata kunci:</span>
                                    @foreach ($item->kunci['kata_kunci'] as $kata)
                                        <span class="badge badge-soft">{{ $kata }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        @break

                    @default
                        @foreach ($item->opsiPilihan() as $opsi)
                            @php $kunci = in_array((string) $opsi['key'], array_map('strval', $item->kunci ?? []), true); @endphp
                            <div class="border rounded p-2 mb-2 {{ $kunci ? 'border-success bg-success-subtle' : '' }}">
                                <strong>{{ strtoupper($opsi['key']) }}.</strong> <span class="opsi-teks" dir="auto">{!! TeksSoal::html($opsi['text']) !!}</span>
                                @if ($kunci)
                                    <i class="bi bi-check-circle-fill text-success float-end"></i>
                                @endif
                            </div>
                        @endforeach
                @endswitch

                @if ($item->pembahasan)
                    <div class="alert alert-light border mt-4 mb-0">
                        <div class="fw-semibold small mb-1"><i class="bi bi-lightbulb me-1"></i>Pembahasan</div>
                        <div class="small">{!! TeksSoal::html($item->pembahasan) !!}</div>
                    </div>
                @endif
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
            <a href="{{ route('soal.edit', $item) }}" class="btn btn-primary flex-grow-1">
                <i class="bi bi-pencil me-1"></i>Ubah
            </a>
            <a href="{{ route('soal.index') }}" class="btn btn-outline-secondary">Kembali</a>
        </div>
    </div>
</div>
@endsection
