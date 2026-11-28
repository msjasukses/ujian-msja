{{--
    Isi satu butir soal: media, pertanyaan, opsi/pasangan, kunci, dan pembahasan.
    Dipakai halaman detail soal dan preview paket soal / ujian.

    Penanda kunci diberi kelas `opsi-kunci` (sorotan) dan `penanda-kunci`
    (lencana, ikon, jawaban model, pembahasan) supaya halaman preview bisa
    menyembunyikannya lewat CSS tanpa merender ulang.
--}}
@php use App\Models\Soal; use App\Support\TeksSoal; @endphp

<x-media-soal :soal="$item" />

<div class="soal-body mb-4" dir="auto">{!! TeksSoal::html($item->pertanyaan) !!}</div>

@switch ($item->jenis)
    @case (Soal::PENJODOHAN)
        <div class="row g-3">
            <div class="col-md-6">
                <div class="small text-muted mb-2">Pernyataan</div>
                @foreach ($item->opsiPilihan() as $kiri)
                    <div class="border rounded p-2 mb-2">
                        <strong>{{ $kiri['key'] }}.</strong> <span class="opsi-teks" dir="auto">{!! TeksSoal::html($kiri['text']) !!}</span>
                        <span class="badge text-bg-success-subtle text-success ms-1 penanda-kunci">
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

    @case (Soal::BENAR_SALAH_KOMPLEKS)
        @foreach ($item->opsiPilihan() as $pernyataan)
            @php $kunci = strtolower((string) ($item->kunci[$pernyataan['key']] ?? '')); @endphp
            <div class="border rounded p-2 mb-2 d-flex gap-2 align-items-start
                        {{ $kunci === 'benar' ? 'border-success bg-success-subtle opsi-kunci' : 'border-secondary-subtle' }}">
                <strong style="min-width:1.75rem">{{ $pernyataan['key'] }}.</strong>
                <span class="flex-grow-1 opsi-teks" dir="auto">{!! TeksSoal::html($pernyataan['text']) !!}</span>
                <span class="badge {{ $kunci === 'benar' ? 'text-bg-success' : 'text-bg-secondary' }} penanda-kunci">
                    {{ Soal::PILIHAN_BS[$kunci] ?? '-' }}
                </span>
            </div>
        @endforeach
        @break

    @case (Soal::ESSAY)
        <div class="border rounded p-3 bg-light-subtle penanda-kunci">
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
            <div class="border rounded p-2 mb-2 {{ $kunci ? 'border-success bg-success-subtle opsi-kunci' : '' }}">
                <strong>{{ strtoupper($opsi['key']) }}.</strong> <span class="opsi-teks" dir="auto">{!! TeksSoal::html($opsi['text']) !!}</span>
                @if ($kunci)
                    <i class="bi bi-check-circle-fill text-success float-end penanda-kunci"></i>
                @endif
            </div>
        @endforeach
@endswitch

@if ($item->pembahasan)
    <div class="alert alert-light border mt-4 mb-0 penanda-kunci">
        <div class="fw-semibold small mb-1"><i class="bi bi-lightbulb me-1"></i>Pembahasan</div>
        <div class="small">{!! TeksSoal::html($item->pembahasan) !!}</div>
    </div>
@endif
