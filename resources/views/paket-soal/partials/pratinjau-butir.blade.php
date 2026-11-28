{{--
    Tombol pratinjau satu butir di halaman pemilihan soal, beserta isi
    lengkapnya (pertanyaan, opsi, gambar, kunci) di dalam <template> yang
    disalin ke modal #modalPratinjauSoal saat tombol ditekan.
--}}
@php
    $teksSemua = $soal->pertanyaan
        .collect($soal->opsiPilihan())->pluck('text')->implode(' ')
        .($soal->jenis === \App\Models\Soal::PENJODOHAN ? collect($soal->opsiKanan())->pluck('text')->implode(' ') : '');
    $adaGambar = stripos((string) $teksSemua, '<img') !== false;
@endphp

@if ($adaGambar)
    <span class="badge text-bg-light border" title="Soal ini memuat gambar"><i class="bi bi-image"></i></span>
@endif
<button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline tombol-pratinjau"
        data-pratinjau="pratinjau-soal-{{ $soal->id }}"
        data-judul="{{ isset($nomor) ? 'Soal '.$nomor : 'Butir #'.$soal->id }}"
        title="Lihat soal lengkap beserta opsi dan gambarnya">
    <i class="bi bi-eye me-1"></i>Lihat
</button>
<template id="pratinjau-soal-{{ $soal->id }}">
    <div class="mb-2 d-flex flex-wrap gap-1">
        <x-jenis-soal :jenis="$soal->jenis" />
        @if ($soal->tingkat_kesukaran)
            <span class="badge badge-soft">{{ ucfirst($soal->tingkat_kesukaran) }}</span>
        @endif
        <span class="badge badge-soft">Bobot {{ (float) ($bobot ?? $soal->bobot) }}</span>
    </div>
    @include('soal.partials.isi-butir', ['item' => $soal])
</template>
