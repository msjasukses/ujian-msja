@props(['jenis'])

@php
    $peta = [
        'pg'          => ['Pilihan Ganda',   'primary'],
        'pg_kompleks' => ['PG Kompleks',     'info'],
        'essay'       => ['Essay',           'warning'],
        'penjodohan'  => ['Penjodohan',      'success'],
        'benar_salah' => ['Benar / Salah',   'secondary'],
    ];
    [$label, $warna] = $peta[$jenis] ?? [$jenis, 'secondary'];
@endphp

<span class="badge text-bg-{{ $warna }}-subtle text-{{ $warna }} border border-{{ $warna }}-subtle">{{ $label }}</span>
