@extends('layouts.app')
@section('title', 'Remidial')
@section('subtitle', 'Siswa yang nilainya belum mencapai KKM beserta rencana tindak lanjutnya')

@section('content')
@include('laporan.partials.pilih-ujian', [
    'items' => $items,
    'routeShow' => 'laporan.remidial.show',
    'judul' => 'Pilih ujian untuk melihat daftar siswa yang perlu remidial',
])
@endsection
