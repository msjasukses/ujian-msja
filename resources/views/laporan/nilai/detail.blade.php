@extends('layouts.app')
@section('title', 'Lembar Jawaban — '.($peserta->siswa->nama_siswa ?? '-'))
@section('subtitle', $ujian->nama_ujian.' · '.($peserta->rombel->nama_rombel ?? '-'))

@section('content')
@php use App\Models\Soal; use App\Support\TeksSoal; @endphp

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-stat label="Nilai akhir" :value="(float) $peserta->nilai" icon="bi-award"
                                        :warna="$peserta->lulus ? 'success' : 'danger'"
                                        :keterangan="'KKM '.(float) $ujian->kkm" /></div>
    <div class="col-6 col-lg-3"><x-stat label="Skor objektif" :value="(float) $peserta->skor_objektif" icon="bi-check2-square" warna="info" /></div>
    <div class="col-6 col-lg-3"><x-stat label="Skor essay" :value="(float) $peserta->skor_essay" icon="bi-pencil" warna="warning" /></div>
    <div class="col-6 col-lg-3"><x-stat label="Benar / Salah / Kosong"
                                        :value="$peserta->jumlah_benar.' / '.$peserta->jumlah_salah.' / '.$peserta->jumlah_kosong"
                                        icon="bi-list-check" warna="secondary" /></div>
</div>

<form method="POST" action="{{ route('laporan.nilai.essay', [$ujian, $peserta]) }}">
    @csrf

    @php $adaEssay = $jawaban->contains(fn ($j) => $j->soal?->jenis === Soal::ESSAY); @endphp

    @if ($adaEssay)
        <div class="alert alert-info py-2 small d-flex justify-content-between align-items-center">
            <span><i class="bi bi-info-circle me-1"></i>Isi skor tiap butir essay di bawah, lalu simpan untuk memperbarui nilai akhir.</span>
            <button class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Simpan Penilaian Essay</button>
        </div>
    @endif

    @foreach ($jawaban as $j)
        @php
            $soal = $j->soal;
            $maks = (float) ($bobot[$j->soal_id] ?? 1);
            $essay = $soal?->jenis === Soal::ESSAY;
        @endphp

        <div class="card mb-3">
            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <span>
                    Nomor {{ $j->nomor_urut }}
                    <x-jenis-soal :jenis="$soal?->jenis ?? 'pg'" />
                    @if ($j->ragu)
                        <span class="badge text-bg-warning-subtle text-warning border border-warning-subtle">
                            <i class="bi bi-flag me-1"></i>ditandai ragu
                        </span>
                    @endif
                </span>
                <span>
                    @if ($essay)
                        <span class="badge badge-soft">Skor {{ (float) $j->skor }} / {{ $maks }}</span>
                    @elseif ($j->is_benar)
                        <span class="badge text-bg-success"><i class="bi bi-check-lg me-1"></i>Benar &middot; {{ (float) $j->skor }}</span>
                    @elseif (! $j->terisi)
                        <span class="badge badge-soft">Tidak dijawab</span>
                    @else
                        <span class="badge text-bg-danger"><i class="bi bi-x-lg me-1"></i>Salah &middot; {{ (float) $j->skor }}</span>
                    @endif
                </span>
            </div>

            <div class="card-body">
                <div class="soal-body mb-3" dir="auto">{!! TeksSoal::html($soal?->pertanyaan) !!}</div>

                @if ($essay)
                    <div class="row g-3">
                        <div class="col-md-7">
                            <div class="small text-muted mb-1">Jawaban siswa</div>
                            <div class="border rounded p-3 bg-white" style="min-height:6rem">
                                {!! $j->teks_essay !== '' ? nl2br(e($j->teks_essay)) : '<span class="text-muted">— tidak dijawab —</span>' !!}
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="small text-muted mb-1">Kunci &amp; rubrik</div>
                            <div class="border rounded p-3 bg-light-subtle small">
                                {!! TeksSoal::html($soal->kunci['jawaban'] ?? '-') !!}
                                @if (! empty($soal->kunci['kata_kunci']))
                                    <div class="mt-2">
                                        @foreach ($soal->kunci['kata_kunci'] as $kata)
                                            <span class="badge badge-soft">{{ $kata }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <div class="mt-3">
                                <label class="form-label">Skor butir ini (maks {{ $maks }})</label>
                                <input type="number" step="0.5" min="0" max="{{ $maks }}"
                                       name="skor[{{ $j->id }}]" value="{{ (float) $j->skor }}"
                                       class="form-control">
                            </div>
                        </div>
                    </div>
                @else
                    @php
                        $jawabanSiswa = array_map('strval', (array) ($j->jawaban ?? []));
                        $kunci = array_map('strval', (array) ($soal->kunci ?? []));
                    @endphp

                    @if ($soal?->jenis === Soal::PENJODOHAN)
                        <table class="table table-sm mb-0" style="max-width:40rem">
                            <thead><tr><th>Pernyataan</th><th>Jawaban siswa</th><th>Kunci</th></tr></thead>
                            <tbody>
                                @foreach ($soal->opsiPilihan() as $kiri)
                                    @php
                                        $dijawab = (array) $j->jawaban;
                                        $siswa = $dijawab[$kiri['key']] ?? null;
                                        $benar = $soal->kunci[$kiri['key']] ?? null;
                                    @endphp
                                    <tr class="{{ $siswa === $benar ? 'table-success' : ($siswa ? 'table-danger' : '') }}">
                                        <td class="small">{{ $kiri['key'] }}. <span class="opsi-teks" dir="auto">{!! TeksSoal::html($kiri['text']) !!}</span></td>
                                        <td class="small">{{ $siswa ?: '—' }}</td>
                                        <td class="small fw-semibold">{{ $benar }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        @foreach ($soal->opsiPilihan() as $opsi)
                            @php
                                $key = (string) $opsi['key'];
                                $dipilih = in_array($key, $jawabanSiswa, true);
                                $adalahKunci = in_array($key, $kunci, true);
                            @endphp
                            <div class="border rounded p-2 mb-2 d-flex align-items-start gap-2
                                        {{ $adalahKunci ? 'border-success bg-success-subtle' : ($dipilih ? 'border-danger bg-danger-subtle' : '') }}">
                                <span class="fw-semibold" style="min-width:1.75rem">{{ strtoupper($key) }}.</span>
                                <span class="flex-grow-1"><span class="opsi-teks" dir="auto">{!! TeksSoal::html($opsi['text']) !!}</span></span>
                                @if ($dipilih)
                                    <span class="badge {{ $adalahKunci ? 'text-bg-success' : 'text-bg-danger' }}">dipilih siswa</span>
                                @elseif ($adalahKunci)
                                    <span class="badge text-bg-success-subtle text-success border border-success-subtle">kunci</span>
                                @endif
                            </div>
                        @endforeach
                    @endif
                @endif
            </div>
        </div>
    @endforeach

    @if ($adaEssay)
        <button class="btn btn-primary mb-3"><i class="bi bi-save me-1"></i>Simpan Penilaian Essay</button>
    @endif
</form>

<a href="{{ route('laporan.nilai.show', $ujian) }}" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Kembali ke daftar nilai
</a>
@endsection
