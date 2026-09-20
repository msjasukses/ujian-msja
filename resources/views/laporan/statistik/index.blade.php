@extends('layouts.app')
@section('title', 'Statistik Ujian')
@section('subtitle', 'Sebaran nilai, ketuntasan, dan perbandingan antar kelas')

@section('content')
@include('laporan.partials.pilih-ujian', [
    'items' => $items,
    'routeShow' => 'laporan.statistik.show',
    'judul' => 'Pilih ujian untuk melihat statistiknya',
    'judulEkstra' => 'Rata-rata',
    'kolomEkstra' => fn ($u) => '<span class="badge badge-soft">'.round((float) $u->rata_nilai, 2).'</span>',
])
@endsection
