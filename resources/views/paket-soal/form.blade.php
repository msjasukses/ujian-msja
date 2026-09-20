@extends('layouts.app')
@section('title', $item->exists ? 'Ubah Paket Soal' : 'Buat Paket Soal')

@section('content')
@php use App\Models\PaketSoal; use App\Support\Referensi; @endphp

<form method="POST" action="{{ $item->exists ? route('paket-soal.update', $item) : route('paket-soal.store') }}">
    @csrf
    @if ($item->exists) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">Identitas Paket</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Kode paket <span class="text-danger">*</span></label>
                            <input name="kode_paket" value="{{ old('kode_paket', $item->kode_paket) }}"
                                   class="form-control @error('kode_paket') is-invalid @enderror" required>
                            @error('kode_paket')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Nama paket <span class="text-danger">*</span></label>
                            <input name="nama_paket" value="{{ old('nama_paket', $item->nama_paket) }}"
                                   class="form-control @error('nama_paket') is-invalid @enderror"
                                   placeholder="mis. PAS Ganjil Matematika Kelas X" required>
                            @error('nama_paket')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Mata pelajaran</label>
                            <x-pilih-mapel :value="$item->mata_pelajaran_id" />
                            <div class="form-text">Dipakai sebagai penyaring bawaan saat memilih butir soal.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tingkat kelas</label>
                            <x-pilih-tingkat :value="$item->tingkat_kelas_id" />
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Jenis ujian</label>
                            <select name="jenis_ujian" class="form-select">
                                <option value="">— pilih —</option>
                                @foreach (PaketSoal::JENIS_UJIAN as $kode => $label)
                                    <option value="{{ $kode }}" @selected(old('jenis_ujian', $item->jenis_ujian) === $kode)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Semester</label>
                            <select name="semester" class="form-select">
                                <option value="">—</option>
                                @foreach (Referensi::semester() as $nilai => $label)
                                    <option value="{{ $nilai }}" @selected(old('semester', $item->semester) === $nilai)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tahun ajaran</label>
                            <x-pilih-tahun-ajaran :value="$item->tahun_ajaran" />
                        </div>

                        <div class="col-12">
                            <label class="form-label">Deskripsi / petunjuk</label>
                            <textarea name="deskripsi" rows="3" class="form-control">{{ old('deskripsi', $item->deskripsi) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">Pengacakan Bawaan</div>
                <div class="card-body">
                    <p class="small text-muted">
                        Pengaturan ini menjadi usulan awal; jadwal ujian tetap punya saklar pengacakannya sendiri.
                    </p>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="acak_soal" value="1" id="acak_soal"
                               @checked(old('acak_soal', $item->acak_soal))>
                        <label class="form-check-label" for="acak_soal">Acak urutan soal</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="acak_opsi" value="1" id="acak_opsi"
                               @checked(old('acak_opsi', $item->acak_opsi))>
                        <label class="form-check-label" for="acak_opsi">Acak urutan opsi jawaban</label>
                    </div>
                    <hr>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_aktif" value="1" id="is_aktif"
                               @checked(old('is_aktif', $item->is_aktif ?? true))>
                        <label class="form-check-label" for="is_aktif">Paket aktif</label>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary flex-grow-1">
                    <i class="bi bi-save me-1"></i>{{ $item->exists ? 'Simpan' : 'Simpan & Pilih Soal' }}
                </button>
                <a href="{{ route('paket-soal.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </div>
    </div>
</form>
@endsection
