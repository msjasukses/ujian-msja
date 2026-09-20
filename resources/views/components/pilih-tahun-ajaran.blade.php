@props(['name' => 'tahun_ajaran', 'value' => null])

{{--
    Dropdown tahun ajaran dari Data Center. Yang disimpan tetap namanya
    (mis. "2026/2027"), sama seperti isian teks sebelumnya, jadi data lama dan
    laporan tidak berubah. Nilai lama yang tidak ada lagi di Data Center tetap
    ditampilkan agar tidak hilang diam-diam saat formulir disimpan ulang.
--}}
@php
    $daftar = \App\Support\Referensi::tahunAjaran();
    $terpilih = old($name, $value);
    $dikenal = $daftar->pluck('nama_tahun_ajaran');
@endphp

<select name="{{ $name }}" {{ $attributes->class(['form-select', 'is-invalid' => $errors->has($name)]) }}>
    <option value="">—</option>
    @if (filled($terpilih) && ! $dikenal->contains($terpilih))
        <option value="{{ $terpilih }}" selected>{{ $terpilih }} (tidak ada di Data Center)</option>
    @endif
    @foreach ($daftar as $ta)
        <option value="{{ $ta->nama_tahun_ajaran }}" @selected($terpilih === $ta->nama_tahun_ajaran)>
            {{ $ta->nama_tahun_ajaran }}{{ $ta->is_aktif ? ' (aktif)' : '' }}
        </option>
    @endforeach
</select>
@error($name)
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
