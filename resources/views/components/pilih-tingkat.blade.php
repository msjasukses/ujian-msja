@props(['name' => 'tingkat_kelas_id', 'value' => null, 'kosong' => '— pilih —'])

{{--
    Dropdown tingkat kelas, mengikuti pola komponen pilih-mapel: guru hanya
    melihat tingkat kelas yang diajarnya, dan nilai di luar itu tetap tampil
    supaya data lama tidak hilang saat disimpan ulang.
--}}
@php
    $daftar = \App\Support\Referensi::tingkat();
    $terpilih = old($name, $value);
    $luar = filled($terpilih) && ! $daftar->contains('id', $terpilih)
        ? \App\Models\TingkatKelas::find($terpilih)
        : null;
@endphp

<select name="{{ $name }}" {{ $attributes->class(['form-select', 'is-invalid' => $errors->has($name)]) }}>
    <option value="">{{ $kosong }}</option>
    @if ($luar)
        <option value="{{ $luar->id }}" selected>{{ $luar->nama }} (di luar kelas yang Anda ajar)</option>
    @endif
    @foreach ($daftar as $t)
        <option value="{{ $t->id }}" @selected((string) $terpilih === (string) $t->id)>{{ $t->nama }}</option>
    @endforeach
</select>
@error($name)
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
