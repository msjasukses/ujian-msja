@extends('layouts.app')
@section('title', 'Analisis Butir Soal')
@section('subtitle', 'Tingkat kesukaran, daya pembeda, dan efektivitas pengecoh tiap butir')

@section('content')
@include('laporan.partials.pilih-ujian', [
    'items' => $items,
    'routeShow' => 'laporan.analisis.show',
    'judul' => 'Pilih ujian untuk dianalisis butir soalnya',
    'judulEkstra' => 'Jumlah Butir',
    'kolomEkstra' => fn ($u) => '<span class="badge badge-soft">'.($u->paketSoal?->detail()->count() ?? 0).'</span>',
])
@endsection
