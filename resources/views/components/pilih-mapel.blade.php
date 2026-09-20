@props(['name' => 'mata_pelajaran_id', 'value' => null, 'kosong' => '— pilih —'])

{{--
    Dropdown mata pelajaran. Isinya sudah menyesuaikan siapa yang masuk:
    guru hanya melihat mapel yang diampunya (lihat Referensi::mapel).

    Data yang dibuat admin bisa memakai mapel di luar pengampuan guru; nilai
    seperti itu tetap ditampilkan agar tidak terhapus diam-diam saat guru
    menyimpan ulang formulirnya.
--}}
@php
    $daftar = \App\Support\Referensi::mapel();
    $terpilih = old($name, $value);
    $luar = filled($terpilih) && ! $daftar->contains('id', $terpilih)
        ? \App\Models\MataPelajaran::find($terpilih)
        : null;
@endphp

<select name="{{ $name }}" {{ $attributes->class(['form-select', 'is-invalid' => $errors->has($name)]) }}>
    <option value="">{{ $kosong }}</option>
    @if ($luar)
        <option value="{{ $luar->id }}" selected>{{ $luar->nama_mapel }} (di luar mapel yang Anda ampu)</option>
    @endif
    @foreach ($daftar as $m)
        <option value="{{ $m->id }}" @selected((string) $terpilih === (string) $m->id)>{{ $m->nama_mapel }}</option>
    @endforeach
</select>
@error($name)
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
