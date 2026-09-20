@extends('layouts.app')
@section('title', 'Tingkat Kelas')
@section('subtitle', 'Sumber: aplikasi Data Center — hanya bisa dibaca dari sini')

@section('content')
@include('referensi.partials.catatan')

<div class="card">
    <div class="card-header">Daftar Tingkat Kelas ({{ $items->count() }})</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th style="width:3rem">#</th><th>Kode</th><th>Nama</th><th class="text-center">Nomor</th>
                    <th>Jenjang</th><th class="text-center">Urutan</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($items as $i => $t)
                    <tr>
                        <td class="text-muted small">{{ $i + 1 }}</td>
                        <td class="small font-monospace">{{ $t->kode }}</td>
                        <td class="fw-semibold">{{ $t->nama }}</td>
                        <td class="text-center">{{ $t->nomor }}</td>
                        <td class="small">{{ $t->jenjang ?: '-' }}</td>
                        <td class="text-center small">{{ $t->urutan }}</td>
                        <td>
                            <span class="badge {{ $t->is_aktif ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ $t->is_aktif ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <x-kosong kolom="7" icon="bi-stack" pesan="Belum ada tingkat kelas di Data Center." />
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
