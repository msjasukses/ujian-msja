@extends('layouts.app')
@section('title', $item->exists ? 'Ubah Pengguna' : 'Tambah Pengguna')

@section('content')
<form method="POST" action="{{ $item->exists ? route('pengguna.update', $item) : route('pengguna.store') }}">
    @csrf
    @if ($item->exists) @method('PUT') @endif

    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">Data Pengguna</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Nama <span class="text-danger">*</span></label>
                        <input name="name" value="{{ old('name', $item->name) }}"
                               class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" value="{{ old('email', $item->email) }}"
                               class="form-control @error('email') is-invalid @enderror" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Email ini dipakai sebagai username saat masuk sebagai Admin.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Peran <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <option value="admin" @selected(old('role', $item->role) === 'admin')>Admin — akses penuh termasuk Log Login &amp; Pengguna</option>
                            <option value="operator" @selected(old('role', $item->role) === 'operator')>Operator — akses penuh kecuali menu sistem</option>
                        </select>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">
                                Kata sandi @unless ($item->exists)<span class="text-danger">*</span>@endunless
                            </label>
                            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                                   {{ $item->exists ? '' : 'required' }}>
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @if ($item->exists)
                                <div class="form-text">Kosongkan bila tidak ingin mengganti kata sandi.</div>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ulangi kata sandi</label>
                            <input type="password" name="password_confirmation" class="form-control"
                                   {{ $item->exists ? '' : 'required' }}>
                        </div>
                    </div>

                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="is_aktif" value="1" id="is_aktif"
                               @checked(old('is_aktif', $item->is_aktif ?? true))>
                        <label class="form-check-label" for="is_aktif">Akun aktif</label>
                    </div>
                </div>
                <div class="card-body border-top d-flex gap-2">
                    <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
                    <a href="{{ route('pengguna.index') }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
