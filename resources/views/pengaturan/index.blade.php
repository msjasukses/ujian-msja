@extends('layouts.app')
@section('title', 'Pengaturan')
@section('subtitle', 'Setelan aplikasi yang berlaku untuk seluruh pengguna')

@section('content')
@php use App\Support\Referensi; @endphp

<div class="row g-3">
    <div class="col-lg-7">
        <form method="POST" action="{{ route('pengaturan.update') }}">
            @csrf @method('PUT')

            <div class="card">
                <div class="card-header">Jenjang Sekolah</div>
                <div class="card-body">
                    <div class="row g-2">
                        @foreach (Referensi::daftarJenjang() as $kode => $label)
                            <div class="col-6 col-md-3">
                                <input type="radio" class="btn-check" name="jenjang" id="jenjang-{{ $kode }}"
                                       value="{{ $kode }}" @checked(old('jenjang', $jenjang) === $kode)>
                                <label class="btn btn-outline-primary w-100 py-2" for="jenjang-{{ $kode }}">
                                    <span class="d-block fw-semibold">{{ $kode }}</span>
                                    <span class="small">{{ $label }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('jenjang')<div class="text-danger small mt-2">{{ $message }}</div>@enderror

                    <div class="form-text mt-3">
                        Pada <strong>SMA</strong> dan <strong>SMK</strong>, setiap isian tingkat kelas disertai
                        pilihan <strong>jurusan</strong>: menu Topik, Bank Soal (input, import, dan penyaring),
                        serta Pemilihan Soal Diujikan. Jurusan yang dibiarkan kosong berarti berlaku untuk semua
                        jurusan, seperti mapel umum.
                    </div>

                    <div class="small text-muted mt-2">
                        <i class="bi bi-database me-1"></i>
                        Jenjang di Data Center: <strong>{{ $jenjangDataCenter ?? 'tidak tercatat' }}</strong>.
                        @unless ($tersimpan)
                            Pengaturan ini belum pernah disimpan, jadi aplikasi masih mengikuti Data Center.
                        @endunless
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
                </div>
            </div>
        </form>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Jurusan di Data Center</span>
                <span class="badge badge-soft">{{ $jurusan->count() }}</span>
            </div>
            @if ($jurusan->isEmpty())
                <div class="card-body small">
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Data Center belum berisi jurusan. Pilihan jurusan tetap tampil pada SMA/SMK, tetapi
                        isinya baru ada setelah jurusan ditambahkan di aplikasi Data Center.
                    </div>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr><th>Kode</th><th>Jurusan</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($jurusan as $j)
                                <tr>
                                    <td class="small font-monospace">{{ $j->kode_jurusan }}</td>
                                    <td class="small">{{ $j->label }}</td>
                                    <td>
                                        <span class="badge {{ $j->is_aktif ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $j->is_aktif ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-body small text-muted border-top">
                    Hanya jurusan aktif yang bisa dipilih. Perubahan daftar jurusan dilakukan di aplikasi Data Center.
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
