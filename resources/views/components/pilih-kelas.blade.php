@props([
    'ujian' => null,
    'name' => 'rombongan_belajar_id',
    'kosong' => 'Semua kelas',
    'otomatis' => true,
])

{{--
    Penyaring kelas untuk menu Hasil & Laporan.

    Dengan :ujian diisi, pilihannya hanya kelas yang ikut ujian itu; tanpa
    itu — pada daftar ujian — seluruh rombel tahun ajaran aktif, dibatasi
    rombel yang diajar bila yang masuk seorang guru.

    otomatis: begitu pilihannya berubah, formulirnya langsung terkirim,
    sehingga tidak perlu tombol Filter tersendiri.
--}}
@php
    $daftar = $ujian
        ? $ujian->rombelDipakai()
        : \App\Support\Referensi::rombel(\App\Support\Pengguna::guruId());
    $terpilih = request($name);
@endphp

<select name="{{ $name }}" {{ $attributes->class(['form-select', 'form-select-sm']) }}
        @if ($otomatis) data-kirim-otomatis @endif>
    <option value="">{{ $kosong }}</option>
    @foreach ($daftar as $k)
        <option value="{{ $k->id }}" @selected((string) $terpilih === (string) $k->id)>{{ $k->nama_rombel }}</option>
    @endforeach
</select>
