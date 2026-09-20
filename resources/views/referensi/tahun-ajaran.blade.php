@extends('layouts.app')
@section('title', 'Tahun Ajaran')
@section('subtitle', 'Sumber: aplikasi Data Center — hanya bisa dibaca dari sini')

@section('content')
@include('referensi.partials.catatan')

<div class="card">
    <div class="card-header">Daftar Tahun Ajaran ({{ $items->count() }})</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th style="width:3rem">#</th><th>Kode</th><th>Nama Tahun Ajaran</th>
                    <th>Mulai</th><th>Selesai</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($items as $i => $ta)
                    <tr class="{{ $ta->is_aktif ? 'table-success' : '' }}">
                        <td class="text-muted small">{{ $i + 1 }}</td>
                        <td class="small font-monospace">{{ $ta->kode_tahun_ajaran }}</td>
                        <td class="fw-semibold">{{ $ta->nama_tahun_ajaran }}</td>
                        <td class="small">{{ $ta->tanggal_mulai?->format('d/m/Y') ?? '-' }}</td>
                        <td class="small">{{ $ta->tanggal_selesai?->format('d/m/Y') ?? '-' }}</td>
                        <td>
                            <span class="badge {{ $ta->is_aktif ? 'text-bg-success' : 'badge-soft' }}">
                                {{ $ta->is_aktif ? 'Aktif' : 'Tidak aktif' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <x-kosong kolom="6" icon="bi-calendar-range" pesan="Belum ada tahun ajaran di Data Center." />
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body border-top small text-muted">
        Tahun ajaran bertanda <strong>Aktif</strong> dipakai sebagai acuan saat mendaftarkan peserta ujian
        dan menampilkan daftar rombongan belajar.
    </div>
</div>
@endsection
