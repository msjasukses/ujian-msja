@extends('layouts.siswa')
@section('title', 'Konfirmasi Ujian')

@section('content')
@php
    use App\Models\UjianPeserta;

    $ujian = $peserta->ujian;
    $lanjut = $peserta->status === UjianPeserta::MULAI;
@endphp

<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">

        <a href="{{ route('siswa.ujian.index') }}" class="btn btn-sm btn-link text-decoration-none ps-0 mb-2">
            <i class="bi bi-chevron-left me-1"></i>Kembali ke daftar ujian
        </a>

        <div class="card overflow-hidden">
            {{-- Kepala berwarna: menandai bahwa halaman ini gerbang menuju ujian. --}}
            <div class="kepala-ujian p-4">
                <div class="pil pil-terang mb-2">
                    <i class="bi bi-journal-bookmark"></i>{{ $ujian->mataPelajaran->nama_mapel ?? 'Tanpa mapel' }}
                </div>
                <h1 class="h4 fw-bold mb-1">{{ $ujian->nama_ujian }}</h1>
                <div class="small opacity-75">Kode ujian {{ $ujian->kode_ujian }}</div>
            </div>

            <div class="card-body p-4">
                {{-- Empat angka yang paling ingin diketahui siswa sebelum mulai. --}}
                <div class="row g-2 mb-4">
                    @foreach ([
                        ['bi-list-ol', 'Jumlah soal', $jumlahButir.' butir'],
                        ['bi-stopwatch', 'Durasi', $ujian->durasi_menit.' menit'],
                        ['bi-door-closed', 'Ditutup pukul', $ujian->waktu_selesai->format('H:i')],
                        ['bi-flag', 'KKM', (float) $ujian->kkm],
                    ] as [$ikon, $label, $nilai])
                        <div class="col-6 col-md-3">
                            <div class="kotak-angka">
                                <i class="bi {{ $ikon }}"></i>
                                <div class="nilai">{{ $nilai }}</div>
                                <div class="label">{{ $label }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($lanjut)
                    <div class="alert alert-warning d-flex gap-2 py-2 small">
                        <i class="bi bi-hourglass-split mt-1"></i>
                        <div>
                            Anda sudah memulai ujian ini. Hitung mundur tetap berjalan —
                            sisa waktu Anda sekitar <strong>{{ intdiv($peserta->sisaWaktuDetik(), 60) }} menit</strong>.
                        </div>
                    </div>
                @endif

                {{-- Hal-hal yang sering membuat siswa panik bila baru diketahui
                     di tengah ujian, jadi disampaikan sebelum tombol ditekan. --}}
                <div class="kotak-ingat mb-4">
                    <div class="fw-semibold mb-2"><i class="bi bi-info-circle me-1"></i>Sebelum mulai, perhatikan</div>
                    <ul class="mb-0">
                        <li>Hitung mundur berjalan sejak tombol Mulai ditekan dan <strong>tidak berhenti</strong> walau halaman ditutup.</li>
                        <li>Jawaban tersimpan otomatis setiap kali Anda memilih atau mengetik.</li>
                        <li>Berpindah tab atau jendela selama ujian tercatat pada log pengawas.</li>
                        @if ($peserta->ujian->proteksi_ketat)
                            <li>Ujian dikerjakan dalam <strong>mode layar penuh</strong>. Keluar dari layar penuh selama ujian terhitung pelanggaran.</li>
                        @endif
                        <li>Pastikan koneksi internet stabil sebelum memulai.</li>
                    </ul>
                </div>

                {{-- Kalau peramban yang dipakai salah, dikatakan di sini — bukan
                     setelah tombol ditekan dan siswa telanjur cemas. --}}
                @if ($ujian->wajib_exambro && ! $bolehPeramban)
                    <div class="alert alert-danger d-flex gap-2 align-items-start">
                        <i class="bi bi-shield-exclamation fs-5"></i>
                        <div>
                            <div class="fw-semibold">Ujian ini wajib dikerjakan lewat aplikasi ExamBro.</div>
                            Tutup peramban ini, buka <strong>ExamBro</strong>, lalu masuk kembali memakai
                            NISN dan kata sandi Anda. Bila sudah memakai ExamBro tetapi pesan ini tetap
                            muncul, laporkan kepada pengawas.
                        </div>
                    </div>
                @elseif ($ujian->wajib_exambro)
                    <div class="alert alert-success py-2 small d-flex gap-2 align-items-center">
                        <i class="bi bi-shield-check"></i>
                        <div>Peramban ujian terdeteksi. Anda boleh memulai ujian ini.</div>
                    </div>
                @endif

                <form method="POST" action="{{ route('siswa.ujian.mulai', $peserta) }}">
                    @csrf

                    @if ($ujian->token && $peserta->status === UjianPeserta::TERDAFTAR)
                        <div class="mb-4">
                            <label class="form-label fw-semibold" for="token">
                                Token ujian <span class="text-danger">*</span>
                            </label>
                            <input name="token" id="token"
                                   class="form-control form-control-lg text-center text-uppercase kolom-token @error('token') is-invalid @enderror"
                                   maxlength="10" placeholder="••••••" required autofocus
                                   autocomplete="off" autocapitalize="characters">
                            @error('token')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text text-center">Token dibacakan pengawas di ruang ujian.</div>
                        </div>
                    @endif

                    <button class="btn btn-ujian btn-lg w-100 py-3" @disabled($ujian->wajib_exambro && ! $bolehPeramban)>
                        <i class="bi bi-play-fill me-1"></i>{{ $lanjut ? 'Lanjutkan Ujian' : 'Mulai Ujian Sekarang' }}
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>
@endsection

@push('head')
<style>
    .kepala-ujian {
        background:linear-gradient(120deg,#0d1f38 0%,#1b3a63 100%);
        color:#fff;
    }
    .pil-terang {
        background:rgba(255,255,255,.16);
        color:#fff;
        border:1px solid rgba(255,255,255,.2);
    }

    .kotak-angka {
        height:100%; text-align:center; padding:.9rem .5rem;
        border:1px solid var(--garis); border-radius:.7rem; background:#fbfdff;
    }
    .kotak-angka i { font-size:1.1rem; color:var(--biru-muda); }
    .kotak-angka .nilai { font-size:1.05rem; font-weight:700; margin-top:.25rem; line-height:1.2; }
    .kotak-angka .label { font-size:.72rem; color:var(--teks-lembut); margin-top:.15rem; }

    .kotak-ingat {
        border:1px solid #ffe1a8; background:#fffaf0;
        border-radius:.7rem; padding:1rem 1.1rem; font-size:.88rem;
    }
    .kotak-ingat ul { padding-left:1.15rem; margin-bottom:0; }
    .kotak-ingat li + li { margin-top:.35rem; }

    /* Token dibacakan pengawas dan diketik cepat — hurufnya dibuat besar dan
       berjarak agar salah ketik langsung kelihatan. */
    .kolom-token {
        font-family:ui-monospace,SFMono-Regular,Consolas,monospace;
        font-size:1.6rem; font-weight:700; letter-spacing:.5rem;
        padding-left:.5rem;
    }
    .kolom-token:focus { border-color:var(--hijau); box-shadow:0 0 0 .25rem rgba(0,168,107,.18); }
</style>
@endpush
