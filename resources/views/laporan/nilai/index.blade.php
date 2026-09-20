@extends('layouts.app')
@section('title', 'Daftar Nilai Ujian')
@section('subtitle', 'Rekap nilai peserta dan penilaian butir essay')

@section('content')
@include('laporan.partials.pilih-ujian', [
    'items' => $items,
    'routeShow' => 'laporan.nilai.show',
    'judul' => 'Pilih ujian untuk melihat daftar nilainya',
    'judulEkstra' => 'Essay Belum Dinilai',
    'kolomEkstra' => fn ($u) => $u->belum_dinilai_count > 0
        ? '<span class="badge text-bg-warning">'.$u->belum_dinilai_count.'</span>'
        : '<span class="badge badge-soft">0</span>',
])
@endsection
