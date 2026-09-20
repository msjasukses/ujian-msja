@extends('layouts.app')
@section('title', 'Pengguna')
@section('subtitle', 'Akun admin dan operator aplikasi ujian')

@section('content')

<div class="alert alert-light border py-2 small d-flex gap-2 align-items-start">
    <i class="bi bi-info-circle mt-1 text-primary"></i>
    <div>
        Halaman ini hanya mengelola akun <strong>admin</strong> dan <strong>operator</strong>.
        Guru dan siswa masuk memakai NIP/NISN beserta kata sandi yang tersimpan di aplikasi Data Center.
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Daftar Pengguna ({{ $items->total() }})</span>
        <div class="d-flex flex-wrap gap-2">
            <x-hapus-massal :action="route('pengguna.hapus-massal')" label="pengguna" />
            <a href="{{ route('pengguna.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Tambah Pengguna
            </a>
        </div>
    </div>

    <div class="card-body border-bottom bg-light-subtle py-2">
        <form class="row g-2 align-items-end">
            <div class="col-8 col-md-4">
                <label class="form-label">Cari</label>
                <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Nama / email">
            </div>
            <div class="col-4 col-md-2">
                <button class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel me-1"></i>Cari</button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><x-pilih semua /><th style="width:3rem">#</th><th>Nama</th><th>Email</th><th>Peran</th><th>Status</th>
                    <th>Dibuat</th><th style="width:7rem"></th></tr>
            </thead>
            <tbody>
                @forelse ($items as $i => $u)
                    <tr>
                        <x-pilih :value="$u->id" />
                        <td class="text-muted small">{{ $items->firstItem() + $i }}</td>
                        <td class="fw-semibold">{{ $u->name }}</td>
                        <td class="small">{{ $u->email }}</td>
                        <td>
                            <span class="badge {{ $u->isAdmin() ? 'text-bg-primary' : 'badge-soft' }}">{{ ucfirst($u->role) }}</span>
                        </td>
                        <td>
                            <span class="badge {{ $u->is_aktif ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ $u->is_aktif ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="small text-muted">{{ $u->created_at?->format('d/m/Y') }}</td>
                        <td class="text-end">
                            <a href="{{ route('pengguna.edit', $u) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="{{ route('pengguna.destroy', $u) }}" class="d-inline"
                                  data-konfirmasi="Hapus pengguna {{ $u->name }}?">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <x-kosong kolom="8" icon="bi-person-gear" pesan="Belum ada pengguna." />
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($items->hasPages())
        <div class="card-body border-top">{{ $items->links() }}</div>
    @endif
</div>
@endsection
