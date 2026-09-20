@extends('layouts.app')
@section('title', 'Pengayaan — '.$ujian->nama_ujian)
@section('subtitle', $ujian->kode_ujian.' · '.($ujian->mataPelajaran->nama_mapel ?? '-').' · KKM '.(float) $ujian->kkm)

@section('content')

@include('laporan.partials.tindak-lanjut', compact('ujian', 'daftar', 'bentuk', 'jenis', 'routeDasar'))

<div class="mt-3">
    <a href="{{ route('laporan.pengayaan.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>
@endsection
