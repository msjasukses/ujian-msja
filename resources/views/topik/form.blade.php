@extends('layouts.app')
@section('title', $item->exists ? 'Ubah Topik' : 'Tambah Topik')

@section('content')
@php use App\Support\Referensi; @endphp

<form method="POST" action="{{ $item->exists ? route('topik.update', $item) : route('topik.store') }}">
    @csrf
    @if ($item->exists) @method('PUT') @endif

    @if ($item->dari_sinkron)
        <div class="alert alert-info py-2 small">
            <i class="bi bi-info-circle me-1"></i>
            Topik ini berasal dari sinkron CP-TP-ATP (terakhir {{ $item->disinkron_pada?->format('d/m/Y H:i') }}).
            Perubahan yang Anda simpan di sini akan <strong>tertimpa</strong> bila sinkron dijalankan lagi.
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">Identitas Topik</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Kode topik</label>
                            <input name="kode_topik" value="{{ old('kode_topik', $item->kode_topik) }}"
                                   class="form-control" placeholder="mis. MTK-10-01">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Nama topik <span class="text-danger">*</span></label>
                            <input name="nama_topik" value="{{ old('nama_topik', $item->nama_topik) }}"
                                   class="form-control @error('nama_topik') is-invalid @enderror" required>
                            @error('nama_topik')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label">Elemen</label>
                            <textarea name="elemen" rows="2" class="form-control">{{ old('elemen', $item->elemen) }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Capaian Pembelajaran (CP)</label>
                            <textarea name="capaian_pembelajaran" rows="3" class="form-control">{{ old('capaian_pembelajaran', $item->capaian_pembelajaran) }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Tujuan Pembelajaran (TP)</label>
                            <textarea name="tujuan_pembelajaran" rows="3" class="form-control">{{ old('tujuan_pembelajaran', $item->tujuan_pembelajaran) }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Alur Tujuan Pembelajaran (ATP)</label>
                            <textarea name="alur_tujuan_pembelajaran" rows="3" class="form-control">{{ old('alur_tujuan_pembelajaran', $item->alur_tujuan_pembelajaran) }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Indikator / KKTP</label>
                            <textarea name="indikator_kktp" rows="2" class="form-control">{{ old('indikator_kktp', $item->indikator_kktp) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">Penempatan</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Mata pelajaran</label>
                        <x-pilih-mapel :value="$item->mata_pelajaran_id" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tingkat kelas</label>
                        <x-pilih-tingkat :value="$item->tingkat_kelas_id" />
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Fase</label>
                            <input name="fase" value="{{ old('fase', $item->fase) }}" class="form-control" placeholder="E / F">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Semester</label>
                            <select name="semester" class="form-select">
                                <option value="">—</option>
                                @foreach (Referensi::semester() as $nilai => $label)
                                    <option value="{{ $nilai }}" @selected(old('semester', $item->semester) === $nilai)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-7">
                            <label class="form-label">Tahun ajaran</label>
                            <x-pilih-tahun-ajaran :value="$item->tahun_ajaran" />
                        </div>
                        <div class="col-5">
                            <label class="form-label">Urutan</label>
                            <input type="number" name="urutan" value="{{ old('urutan', $item->urutan ?? 0) }}" class="form-control" min="0">
                        </div>
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="is_aktif" value="1" id="is_aktif"
                               @checked(old('is_aktif', $item->is_aktif ?? true))>
                        <label class="form-check-label" for="is_aktif">Topik aktif</label>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary flex-grow-1"><i class="bi bi-save me-1"></i>Simpan</button>
                <a href="{{ route('topik.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </div>
    </div>
</form>
@endsection
