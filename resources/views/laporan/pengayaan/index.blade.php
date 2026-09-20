@extends('layouts.app')
@section('title', 'Pengayaan')
@section('subtitle', 'Siswa yang sudah mencapai KKM dan layak diberi pengayaan')

@section('content')
@include('laporan.partials.pilih-ujian', [
    'items' => $items,
    'routeShow' => 'laporan.pengayaan.show',
    'judul' => 'Pilih ujian untuk melihat kandidat pengayaan',
])
@endsection
