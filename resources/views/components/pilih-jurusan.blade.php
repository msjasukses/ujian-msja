@props(['name' => 'jurusan_id', 'value' => null, 'kosong' => '— semua jurusan (umum) —'])

{{--
    Dropdown jurusan, hanya dipasang pada jenjang SMA/SMK (lihat
    Referensi::pakaiJurusan). Mengikuti pola pilih-tingkat: guru hanya melihat
    jurusan rombel yang diajarnya, dan nilai di luar itu tetap tampil supaya
    tidak hilang saat disimpan ulang.

    Pilihan kosong bukan "belum diisi": artinya berlaku untuk semua jurusan,
    seperti mapel umum.
--}}
@php
    $daftar = \App\Support\Referensi::jurusan();
    $terpilih = old($name, $value);
    $luar = filled($terpilih) && ! $daftar->contains('id', $terpilih)
        ? \App\Models\Jurusan::find($terpilih)
        : null;
@endphp

<select name="{{ $name }}" {{ $attributes->class(['form-select', 'is-invalid' => $errors->has($name)]) }}>
    <option value="">{{ $kosong }}</option>
    @if ($luar)
        <option value="{{ $luar->id }}" selected>{{ $luar->label }} (di luar jurusan yang Anda ajar)</option>
    @endif
    @foreach ($daftar as $j)
        <option value="{{ $j->id }}" @selected((string) $terpilih === (string) $j->id)>{{ $j->label }}</option>
    @endforeach
</select>
@error($name)
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
