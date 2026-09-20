@extends('layouts.siswa')
@section('title', 'Hasil Ujian')

@section('content')
@php use App\Models\Soal; use App\Support\TeksSoal; @endphp
@php $ujian = $peserta->ujian; @endphp

<div class="row justify-content-center">
    <div class="col-xl-9">

        <a href="{{ route('siswa.ujian.index') }}" class="btn btn-sm btn-link text-decoration-none ps-0 mb-2">
            <i class="bi bi-chevron-left me-1"></i>Kembali ke daftar ujian
        </a>

        <div class="card mb-3 overflow-hidden">
            <div class="kepala-hasil text-center px-3 py-4">
                <div class="pil pil-terang mb-2">
                    <i class="bi bi-journal-bookmark"></i>{{ $ujian->mataPelajaran->nama_mapel ?? 'Tanpa mapel' }}
                </div>
                <h1 class="h5 fw-bold mb-0">{{ $ujian->nama_ujian }}</h1>
            </div>

            <div class="card-body text-center py-4">
                @if ($ujian->tampilkan_hasil)
                    @if (! $peserta->essay_dinilai)
                        <div class="kotak-menunggu mb-3">
                            <i class="bi bi-hourglass-split"></i>
                            <span>Masih ada jawaban essay yang menunggu penilaian guru — nilai di bawah belum final.</span>
                        </div>
                    @endif

                    {{-- Nilai ditulis besar: inilah satu hal yang dicari siswa
                         begitu halaman terbuka. --}}
                    <div class="lingkar-nilai {{ $peserta->lulus ? 'lulus' : 'belum' }}">
                        <div class="angka">{{ (float) $peserta->nilai }}</div>
                        <div class="kkm">KKM {{ (float) $ujian->kkm }}</div>
                    </div>

                    <div class="mt-3 mb-4">
                        <span class="pil {{ $peserta->lulus ? 'pil-hijau' : 'pil-merah' }}">
                            <i class="bi {{ $peserta->lulus ? 'bi-check2-circle' : 'bi-exclamation-circle' }}"></i>
                            {{ $peserta->lulus ? 'Tuntas' : 'Belum Tuntas' }}
                        </span>
                    </div>

                    <div class="row g-2 justify-content-center" style="max-width:34rem;margin:0 auto">
                        @foreach ([
                            ['Benar', $peserta->jumlah_benar, 'benar', 'bi-check-lg'],
                            ['Salah', $peserta->jumlah_salah, 'salah', 'bi-x-lg'],
                            ['Kosong', $peserta->jumlah_kosong, 'kosong', 'bi-dash-lg'],
                        ] as [$label, $nilai, $kelas, $ikon])
                            <div class="col-4">
                                <div class="kotak-rekap {{ $kelas }}">
                                    <i class="bi {{ $ikon }}"></i>
                                    <div class="jumlah">{{ $nilai }}</div>
                                    <div class="label">{{ $label }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="ikon-terkumpul mb-3"><i class="bi bi-check2-circle"></i></div>
                    <h2 class="h5 fw-bold">Lembar jawaban Anda sudah dikumpulkan</h2>
                    <p class="text-muted mb-0">Nilai akan diumumkan oleh guru pengampu.</p>
                @endif

                <div class="small text-muted mt-4">
                    <i class="bi bi-clock me-1"></i>
                    Dikerjakan {{ $peserta->waktu_mulai?->format('d/m/Y H:i') }}
                    &ndash; {{ $peserta->waktu_selesai?->format('H:i') }}
                </div>
            </div>
        </div>

        @if ($bolehLihatPembahasan)
            <div class="card">
                <div class="card-header">Pembahasan Jawaban</div>
                <div class="card-body">
                    @foreach ($jawaban as $j)
                        @php
                            $soal = $j->soal;
                            $terpilih = array_map('strval', (array) ($j->jawaban ?? []));
                            // Kunci essay & penjodohan berbentuk asosiatif/bersarang,
                            // jadi daftar huruf kunci hanya disusun untuk soal pilihan.
                            $kunci = in_array($soal->jenis, [Soal::PG, Soal::PG_KOMPLEKS, Soal::BENAR_SALAH], true)
                                ? array_map('strval', (array) ($soal->kunci ?? []))
                                : [];
                        @endphp

                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold">Nomor {{ $j->nomor_urut }}</span>
                                @if ($soal->jenis === $jenisEssay)
                                    <span class="badge bg-light text-dark border">Skor {{ (float) $j->skor }}</span>
                                @elseif ($j->is_benar)
                                    <span class="badge text-bg-success"><i class="bi bi-check-lg me-1"></i>Benar</span>
                                @elseif (! $j->terisi)
                                    <span class="badge bg-light text-dark border">Tidak dijawab</span>
                                @else
                                    <span class="badge text-bg-danger"><i class="bi bi-x-lg me-1"></i>Salah</span>
                                @endif
                            </div>

                            <div class="soal-body mb-3" dir="auto">{!! TeksSoal::html($soal->pertanyaan) !!}</div>

                            @if ($soal->jenis === $jenisEssay)
                                <div class="small text-muted">Jawaban Anda</div>
                                <div class="border rounded p-2 mb-2 bg-light-subtle">
                                    {!! $j->teks_essay !== '' ? nl2br(e($j->teks_essay)) : '<span class="text-muted">— kosong —</span>' !!}
                                </div>
                                <div class="small text-muted">Jawaban acuan</div>
                                <div class="border rounded p-2 bg-success-subtle small">
                                    {!! TeksSoal::html($soal->kunci['jawaban'] ?? '-') !!}
                                </div>
                            @elseif ($soal->jenis === Soal::PENJODOHAN)
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>Pernyataan</th><th>Jawaban Anda</th><th>Kunci</th></tr></thead>
                                    <tbody>
                                        @foreach ($soal->opsiPilihan() as $kiri)
                                            @php
                                                $siswa = ((array) $j->jawaban)[$kiri['key']] ?? null;
                                                $benar = $soal->kunci[$kiri['key']] ?? null;
                                            @endphp
                                            <tr class="{{ $siswa === $benar ? 'table-success' : ($siswa ? 'table-danger' : '') }}">
                                                <td class="small">{{ $kiri['key'] }}. <span class="opsi-teks" dir="auto">{!! TeksSoal::html($kiri['text']) !!}</span></td>
                                                <td class="small">{{ $siswa ?: '—' }}</td>
                                                <td class="small fw-semibold">{{ $benar }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                @foreach ($soal->opsiPilihan() as $o)
                                    @php
                                        $key = (string) $o['key'];
                                        $dipilih = in_array($key, $terpilih, true);
                                        $adalahKunci = in_array($key, $kunci, true);
                                    @endphp
                                    <div class="d-flex gap-2 align-items-start border rounded p-2 mb-1
                                                {{ $adalahKunci ? 'border-success bg-success-subtle' : ($dipilih ? 'border-danger bg-danger-subtle' : '') }}">
                                        <strong style="min-width:1.75rem">{{ strtoupper($key) }}.</strong>
                                        <span class="flex-grow-1"><span class="opsi-teks" dir="auto">{!! TeksSoal::html($o['text']) !!}</span></span>
                                        @if ($dipilih)
                                            <span class="badge {{ $adalahKunci ? 'text-bg-success' : 'text-bg-danger' }}">jawaban Anda</span>
                                        @elseif ($adalahKunci)
                                            <span class="badge text-bg-success">kunci</span>
                                        @endif
                                    </div>
                                @endforeach
                            @endif

                            @if ($soal->pembahasan)
                                <div class="alert alert-light border mt-3 mb-0 small">
                                    <i class="bi bi-lightbulb me-1"></i><strong>Pembahasan:</strong>
                                    {!! TeksSoal::html($soal->pembahasan) !!}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @elseif ($ujian->tampilkan_hasil && ! $ujian->sudah_lewat)
            <div class="alert alert-info">
                <i class="bi bi-clock-history me-1"></i>
                Pembahasan jawaban dibuka setelah seluruh jendela waktu ujian berakhir
                pada {{ $ujian->waktu_selesai->format('d/m/Y H:i') }}.
            </div>
        @endif

        <div class="mt-3">
            <a href="{{ route('siswa.ujian.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Kembali ke daftar ujian
            </a>
        </div>
    </div>
</div>
@endsection

@push('head')
<style>
    .kepala-hasil { background:linear-gradient(120deg,#0d1f38 0%,#1b3a63 100%); color:#fff; }
    .pil-terang { background:rgba(255,255,255,.16); color:#fff; border:1px solid rgba(255,255,255,.2); }

    /* Nilai dilingkari agar terbaca sebagai satu angka utuh, bukan deretan
       teks besar yang menempel pada rekap di bawahnya. */
    .lingkar-nilai {
        width:9.5rem; height:9.5rem; margin:0 auto;
        border-radius:50%; display:grid; place-content:center;
        border:6px solid; background:#fff;
    }
    .lingkar-nilai .angka { font-size:2.8rem; font-weight:800; line-height:1; }
    .lingkar-nilai .kkm { font-size:.75rem; color:var(--teks-lembut); margin-top:.35rem; }
    .lingkar-nilai.lulus { border-color:var(--hijau); }
    .lingkar-nilai.lulus .angka { color:#046c48; }
    .lingkar-nilai.belum { border-color:var(--merah); }
    .lingkar-nilai.belum .angka { color:#9b1c28; }

    .kotak-menunggu {
        display:inline-flex; gap:.5rem; align-items:flex-start; text-align:left;
        background:#fffaf0; border:1px solid #ffe1a8; color:#8a5a00;
        border-radius:.6rem; padding:.6rem .85rem; font-size:.85rem; max-width:34rem;
    }

    .kotak-rekap {
        border:1px solid var(--garis); border-radius:.7rem;
        padding:.85rem .5rem; background:#fbfdff;
    }
    .kotak-rekap i { font-size:.95rem; }
    .kotak-rekap .jumlah { font-size:1.5rem; font-weight:700; line-height:1.1; }
    .kotak-rekap .label { font-size:.75rem; color:var(--teks-lembut); }
    .kotak-rekap.benar i, .kotak-rekap.benar .jumlah { color:#046c48; }
    .kotak-rekap.salah i, .kotak-rekap.salah .jumlah { color:#9b1c28; }
    .kotak-rekap.kosong i, .kotak-rekap.kosong .jumlah { color:#64748b; }

    .ikon-terkumpul {
        width:4.5rem; height:4.5rem; margin-inline:auto; border-radius:50%;
        display:grid; place-items:center; font-size:2.1rem;
        background:var(--hijau-lembut); color:#046c48;
    }
</style>
@endpush