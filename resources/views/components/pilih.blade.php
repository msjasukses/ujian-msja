@props(['value' => null, 'semua' => false])

{{-- Kotak centang hapus massal: atribut "semua" untuk thead, "value" berisi id untuk tiap baris. --}}
@if ($semua)
    <th style="width:2.5rem">
        <input type="checkbox" class="form-check-input" data-pilih-semua title="Pilih semua di halaman ini" aria-label="Pilih semua">
    </th>
@else
    <td>
        <input type="checkbox" class="form-check-input" name="ids[]" value="{{ $value }}"
               form="hapusMassal" data-pilih aria-label="Pilih baris">
    </td>
@endif
