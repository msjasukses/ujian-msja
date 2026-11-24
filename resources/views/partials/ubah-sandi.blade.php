{{--
    Kartu "Ubah Kata Sandi" di halaman profil.

    Dipakai ketiga peran — admin/operator, guru, dan peserta — karena isiannya
    sama; yang berbeda hanya alamat tujuannya.

    @param string $aksi Nama route tujuan (profil.sandi / siswa.profil.sandi)
--}}
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-key me-1"></i>Ubah Kata Sandi</div>
    <form method="POST" action="{{ route($aksi) }}" autocomplete="off">
        @csrf @method('PUT')
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Kata sandi saat ini <span class="text-danger">*</span></label>
                <input type="password" name="password_lama" autocomplete="current-password"
                       class="form-control @error('password_lama') is-invalid @enderror" required>
                @error('password_lama')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Kata sandi baru <span class="text-danger">*</span></label>
                <input type="password" name="password" autocomplete="new-password"
                       class="form-control @error('password') is-invalid @enderror" required>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Minimal 8 karakter, dan harus berbeda dari yang sekarang.</div>
            </div>

            <div class="mb-0">
                <label class="form-label">Ulangi kata sandi baru <span class="text-danger">*</span></label>
                <input type="password" name="password_confirmation" autocomplete="new-password"
                       class="form-control" required>
            </div>
        </div>
        <div class="card-body border-top text-end py-2">
            <button class="btn btn-sm btn-primary"><i class="bi bi-shield-lock me-1"></i>Ubah kata sandi</button>
        </div>
    </form>
</div>
