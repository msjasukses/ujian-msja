{{--
    Tabel siswa kandidat remidial / pengayaan beserta form perencanaan dan
    pencatatan nilai akhirnya. Dipakai bersama oleh kedua sub menu.

    $ujian, $daftar, $bentuk, $jenis, $routeDasar
--}}
@php $labelJenis = ucfirst($jenis); @endphp

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <x-stat :label="'Siswa '.$labelJenis" :value="$daftar->count()" icon="bi-people"
                :warna="$jenis === 'remidial' ? 'danger' : 'success'" />
    </div>
    <div class="col-6 col-lg-3">
        <x-stat label="Sudah direncanakan" :value="$daftar->filter(fn ($d) => $d->rencana)->count()"
                icon="bi-calendar-check" warna="info" />
    </div>
    <div class="col-6 col-lg-3">
        <x-stat label="Sudah ada nilai akhir" :value="$daftar->filter(fn ($d) => $d->rencana?->nilai_akhir !== null)->count()"
                icon="bi-check2-circle" warna="success" />
    </div>
    <div class="col-6 col-lg-3">
        <x-stat label="KKM ujian" :value="(float) $ujian->kkm" icon="bi-bullseye" warna="secondary" />
    </div>
</div>

@if ($daftar->isEmpty())
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-1"></i>
        @if ($jenis === 'remidial')
            Tidak ada siswa yang nilainya di bawah KKM pada ujian ini — tidak perlu remidial.
        @else
            Belum ada siswa yang mencapai KKM pada ujian ini, sehingga belum ada kandidat pengayaan.
        @endif
    </div>
@else

{{-- ---------- Langkah 1: rencanakan tindak lanjut ---------- --}}
<form method="POST" action="{{ route($routeDasar.'.simpan', $ujian) }}" class="mb-3">
    @csrf
    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <span>Rencanakan {{ $labelJenis }}</span>
            <a href="{{ route($routeDasar.'.export', $ujian) }}" class="btn btn-sm btn-outline-success">
                <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
            </a>
        </div>

        <div class="card-body border-bottom bg-light-subtle">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Bentuk {{ $jenis }} <span class="text-danger">*</span></label>
                    <select name="bentuk" class="form-select form-select-sm" required>
                        @foreach ($bentuk as $nilai => $label)
                            <option value="{{ $nilai }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tanggal pelaksanaan</label>
                    <input type="date" name="tanggal" value="{{ now()->addWeek()->toDateString() }}"
                           class="form-control form-control-sm">
                </div>
                <div class="col-md-5">
                    <label class="form-label">Keterangan</label>
                    <input name="keterangan" class="form-control form-control-sm"
                           placeholder="mis. materi yang diulang / tugas yang diberikan">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:2.5rem"><input type="checkbox" class="form-check-input" id="pilihSemua" checked></th>
                        <th style="width:3rem">#</th>
                        <th>NISN</th>
                        <th>Nama Siswa</th>
                        <th>Kelas</th>
                        <th class="text-center">Nilai</th>
                        <th class="text-center">Selisih KKM</th>
                        <th>Rencana Tersimpan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($daftar as $i => $d)
                        <tr>
                            <td><input type="checkbox" name="pilih[]" value="{{ $d->siswa_id }}" class="form-check-input pilih-siswa" checked></td>
                            <td class="text-muted small">{{ $i + 1 }}</td>
                            <td class="small">{{ $d->nisn }}</td>
                            <td>{{ $d->nama }}</td>
                            <td class="small">{{ $d->kelas }}</td>
                            <td class="text-center fw-semibold">{{ $d->nilai }}</td>
                            <td class="text-center small {{ $d->selisih < 0 ? 'text-danger' : 'text-success' }}">
                                {{ $d->selisih > 0 ? '+' : '' }}{{ $d->selisih }}
                            </td>
                            <td class="small">
                                @if ($d->rencana)
                                    <span class="badge badge-soft">{{ $d->rencana->bentuk_label }}</span>
                                    <span class="text-muted">{{ $d->rencana->tanggal?->format('d/m/Y') }}</span>
                                @else
                                    <span class="text-muted">belum</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="card-body border-top">
            <button class="btn btn-primary">
                <i class="bi bi-save me-1"></i>Simpan Rencana untuk Siswa Terpilih
            </button>
        </div>
    </div>
</form>

{{-- ---------- Langkah 2: catat nilai akhir ---------- --}}
@php $sudahAdaRencana = $daftar->filter(fn ($d) => $d->rencana); @endphp

@if ($sudahAdaRencana->isNotEmpty())
    <form method="POST" action="{{ route($routeDasar.'.nilai-akhir', $ujian) }}">
        @csrf
        <div class="card">
            <div class="card-header">Catat Hasil {{ $labelJenis }}</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:3rem">#</th>
                            <th>Nama Siswa</th>
                            <th>Bentuk</th>
                            <th>Tanggal</th>
                            <th class="text-center">Nilai Awal</th>
                            <th style="width:8rem" class="text-center">Nilai Akhir</th>
                            <th class="text-center">Status</th>
                            <th style="width:3rem"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sudahAdaRencana->values() as $i => $d)
                            <tr>
                                <td class="text-muted small">{{ $i + 1 }}</td>
                                <td>{{ $d->nama }} <span class="small text-muted d-block">{{ $d->kelas }}</span></td>
                                <td class="small">{{ $d->rencana->bentuk_label }}</td>
                                <td class="small">{{ $d->rencana->tanggal?->format('d/m/Y') ?? '-' }}</td>
                                <td class="text-center">{{ (float) $d->rencana->nilai_awal }}</td>
                                <td>
                                    <input type="number" step="0.5" min="0" max="100"
                                           name="nilai_akhir[{{ $d->rencana->id }}]"
                                           value="{{ $d->rencana->nilai_akhir !== null ? (float) $d->rencana->nilai_akhir : '' }}"
                                           class="form-control form-control-sm text-center">
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $d->rencana->status === 'selesai' ? 'text-bg-success' : 'badge-soft' }}">
                                        {{ ucfirst($d->rencana->status) }}
                                    </span>
                                </td>
                                <td>
                                    <button type="submit" form="hapus-tl-{{ $d->rencana->id }}"
                                            class="btn btn-sm btn-outline-danger" title="Hapus rencana">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body border-top">
                <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Nilai Akhir</button>
            </div>
        </div>
    </form>

    @foreach ($sudahAdaRencana as $d)
        <form method="POST" action="{{ route($routeDasar.'.hapus', [$ujian, $d->rencana]) }}"
              id="hapus-tl-{{ $d->rencana->id }}" data-konfirmasi="Hapus rencana {{ $jenis }} untuk {{ $d->nama }}?">
            @csrf @method('DELETE')
        </form>
    @endforeach
@endif

@push('scripts')
<script>
    document.getElementById('pilihSemua')?.addEventListener('change', function () {
        document.querySelectorAll('.pilih-siswa').forEach(cb => cb.checked = this.checked);
    });
</script>
@endpush

@endif
