@extends('layouts.app')
@section('title', 'Pilih Soal — '.$paket->nama_paket)
@section('subtitle', $paket->kode_paket.' · '.($paket->mataPelajaran->nama_mapel ?? 'semua mapel').' · '.($paket->tingkatKelas->nama ?? 'semua tingkat'))

@section('content')
@php use App\Models\Soal; use App\Support\TeksSoal; use App\Support\Referensi; @endphp

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-stat label="Butir dalam paket" :value="$terpilih->count()" icon="bi-list-check" /></div>
    <div class="col-6 col-lg-3"><x-stat label="Total bobot" :value="rtrim(rtrim(number_format($terpilih->sum('bobot'), 2, ',', '.'), '0'), ',')" icon="bi-sliders" warna="info" /></div>
    <div class="col-6 col-lg-3"><x-stat label="Soal essay" :value="$terpilih->filter(fn($d) => $d->soal?->jenis === 'essay')->count()" icon="bi-pencil" warna="warning" keterangan="dinilai manual" /></div>
    <div class="col-6 col-lg-3"><x-stat label="Tersedia di bank" :value="$tersedia->total()" icon="bi-journal-text" warna="secondary" /></div>
</div>

<div class="row g-3">

    {{-- ---------- Butir yang sudah masuk paket ---------- --}}
    <div class="col-xl-6">
        <form method="POST" action="{{ route('paket-soal.urutan', $paket) }}">
            @csrf @method('PUT')
            <div class="card h-100">
                <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <span><i class="bi bi-check2-square me-1"></i>Soal dalam Paket</span>
                    <div class="d-flex flex-wrap gap-2">
                        <x-hapus-massal bagian="tombol" aksi="Lepas" />
                        <a href="{{ route('paket-soal.export', $paket) }}" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-file-earmark-excel me-1"></i>Export
                        </a>
                        <button class="btn btn-sm btn-primary" @disabled($terpilih->isEmpty())>
                            <i class="bi bi-save me-1"></i>Simpan urutan &amp; bobot
                        </button>
                    </div>
                </div>

                <div class="table-responsive" style="max-height:36rem">
                    <table class="table table-sm table-hover table-sticky align-middle mb-0">
                        <thead>
                            <tr>
                                <x-pilih semua />
                                <th style="width:4.5rem">Urut</th>
                                <th>Soal</th>
                                <th style="width:5.5rem">Bobot</th>
                                <th style="width:3rem"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($terpilih as $d)
                                <tr>
                                    <x-pilih :value="$d->id" />
                                    <td>
                                        <input type="number" name="urut[{{ $d->id }}]" value="{{ $d->nomor_urut }}"
                                               class="form-control form-control-sm" min="1">
                                    </td>
                                    <td>
                                        <div class="text-wrap-2 small">{{ Str::limit(TeksSoal::polos($d->soal?->pertanyaan) ?: '-', 110) }}</div>
                                        <div class="mt-1">
                                            <x-jenis-soal :jenis="$d->soal?->jenis ?? 'pg'" />
                                            @if ($d->soal?->tingkat_kesukaran)
                                                <span class="badge badge-soft">{{ ucfirst($d->soal->tingkat_kesukaran) }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" step="0.5" min="0.5" name="bobot[{{ $d->id }}]"
                                               value="{{ (float) $d->bobot }}" class="form-control form-control-sm">
                                    </td>
                                    <td>
                                        <button type="submit" form="lepas-{{ $d->id }}" class="btn btn-sm btn-outline-danger" title="Lepas dari paket">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <x-kosong kolom="5" icon="bi-inbox"
                                          pesan="Paket ini belum berisi soal. Pilih dari bank soal di sebelah kanan." />
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </form>

        {{-- Form pelepasan ditaruh di luar form urutan agar tidak bersarang. --}}
        <x-hapus-massal bagian="form" :action="route('paket-soal.lepas-massal', $paket)"
                        label="butir soal" aksi="Lepas"
                        catatan="Butirnya tetap ada di bank soal, hanya dikeluarkan dari paket ini." />
        @foreach ($terpilih as $d)
            <form method="POST" action="{{ route('paket-soal.hapus-soal', [$paket, $d]) }}" id="lepas-{{ $d->id }}"
                  data-konfirmasi="Lepas butir nomor {{ $d->nomor_urut }} dari paket ini?">
                @csrf @method('DELETE')
            </form>
        @endforeach
    </div>

    {{-- ---------- Bank soal yang tersedia ---------- --}}
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-journal-text me-1"></i>Bank Soal Tersedia</span>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#panelAcak">
                    <i class="bi bi-shuffle me-1"></i>Ambil acak
                </button>
            </div>

            <div class="collapse" id="panelAcak">
                <form method="POST" action="{{ route('paket-soal.acak', $paket) }}" class="card-body border-bottom bg-light-subtle">
                    @csrf
                    <p class="small text-muted mb-2">
                        Ambil butir secara acak dari bank soal sesuai komposisi tingkat kesukaran yang diinginkan.
                    </p>
                    <div class="row g-2 align-items-end">
                        <div class="col-3">
                            <label class="form-label">Mudah</label>
                            <input type="number" name="jumlah_mudah" value="0" min="0" class="form-control form-control-sm">
                        </div>
                        <div class="col-3">
                            <label class="form-label">Sedang</label>
                            <input type="number" name="jumlah_sedang" value="0" min="0" class="form-control form-control-sm">
                        </div>
                        <div class="col-3">
                            <label class="form-label">Sukar</label>
                            <input type="number" name="jumlah_sukar" value="0" min="0" class="form-control form-control-sm">
                        </div>
                        <div class="col-3">
                            <label class="form-label">Jenis</label>
                            <select name="jenis_acak" class="form-select form-select-sm">
                                <option value="">Semua</option>
                                @foreach (Soal::JENIS as $nilai => $label)
                                    <option value="{{ $nilai }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-sm btn-primary w-100"><i class="bi bi-shuffle me-1"></i>Ambil</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card-body border-bottom bg-light-subtle py-2">
                <form class="row g-2 align-items-end">
                    <div class="col-6 col-md-4">
                        <label class="form-label">Cari</label>
                        <input name="q" value="{{ request('q') }}" class="form-control form-control-sm">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Jenis</label>
                        <select name="jenis" class="form-select form-select-sm">
                            <option value="">Semua</option>
                            @foreach (Soal::JENIS as $nilai => $label)
                                <option value="{{ $nilai }}" @selected(request('jenis') === $nilai)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Kesukaran</label>
                        <select name="tingkat_kesukaran" class="form-select form-select-sm">
                            <option value="">Semua</option>
                            @foreach (Soal::KESUKARAN as $nilai => $label)
                                <option value="{{ $nilai }}" @selected(request('tingkat_kesukaran') === $nilai)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <button class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel"></i></button>
                    </div>
                    <div class="col-12">
                        <select name="topik_id" class="form-select form-select-sm">
                            <option value="">Semua topik</option>
                            @foreach ($daftarTopik as $t)
                                <option value="{{ $t->id }}" @selected(request('topik_id') == $t->id)>{{ Str::limit($t->nama_topik, 70) }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>

            <form method="POST" action="{{ route('paket-soal.tambah-soal', $paket) }}">
                @csrf
                <div class="table-responsive" style="max-height:30rem">
                    <table class="table table-sm table-hover table-sticky align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:2.5rem"><input type="checkbox" class="form-check-input" id="pilihSemua"></th>
                                <th>Soal</th>
                                <th style="width:4rem" class="text-center">Bobot</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tersedia as $s)
                                <tr>
                                    <td><input type="checkbox" name="soal_id[]" value="{{ $s->id }}" class="form-check-input pilih-soal"></td>
                                    <td>
                                        <div class="text-wrap-2 small">{{ Str::limit(TeksSoal::polos($s->pertanyaan), 110) }}</div>
                                        <div class="mt-1">
                                            <x-jenis-soal :jenis="$s->jenis" />
                                            @if ($s->tingkat_kesukaran)
                                                <span class="badge badge-soft">{{ ucfirst($s->tingkat_kesukaran) }}</span>
                                            @endif
                                            @if ($s->topik)
                                                <span class="small text-muted">{{ Str::limit($s->topik->nama_topik, 35) }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-center small">{{ (float) $s->bobot }}</td>
                                </tr>
                            @empty
                                <x-kosong kolom="3" icon="bi-journal-x"
                                          pesan="Tidak ada soal tersedia yang cocok. Longgarkan filter atau tambah soal baru ke bank." />
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="card-body border-top d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <div>{{ $tersedia->links() }}</div>
                    <button class="btn btn-sm btn-primary" @disabled($tersedia->isEmpty())>
                        <i class="bi bi-plus-lg me-1"></i>Tambahkan yang dicentang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="mt-3">
    <a href="{{ route('paket-soal.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali ke daftar paket
    </a>
    <a href="{{ route('ujian.create') }}" class="btn btn-success">
        <i class="bi bi-calendar-check me-1"></i>Jadwalkan ujian dengan paket ini
    </a>
</div>

@push('scripts')
<script>
    document.getElementById('pilihSemua')?.addEventListener('change', function () {
        document.querySelectorAll('.pilih-soal').forEach(cb => cb.checked = this.checked);
    });
</script>
@endpush
@endsection
