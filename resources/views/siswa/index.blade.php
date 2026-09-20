@extends('layouts.siswa')
@section('title', 'Ruang Ujian')

@section('content')
@php
    use App\Models\UjianPeserta;

    $jam = (int) now()->format('H');
    $sapaan = match (true) {
        $jam < 11 => 'Selamat pagi',
        $jam < 15 => 'Selamat siang',
        $jam < 18 => 'Selamat sore',
        default => 'Selamat malam',
    };

    $selesai = $lainnya->where('status', UjianPeserta::SELESAI);
@endphp

<div class="row justify-content-center g-3">
    <div class="col-xl-9">

        {{-- ---------- Identitas peserta ---------- --}}
        <div class="card border-0 mb-3 kartu-sapaan">
            <div class="card-body d-flex flex-wrap align-items-center gap-3 p-4">
                <div class="avatar">{{ mb_strtoupper(mb_substr($siswa->nama_siswa, 0, 1)) }}</div>

                <div class="flex-grow-1">
                    <div class="sapaan">{{ $sapaan }},</div>
                    <div class="nama">{{ $siswa->nama_siswa }}</div>
                    <div class="rincian">
                        <span><i class="bi bi-person-vcard me-1"></i>{{ $siswa->nisn }}</span>
                        @if ($kelas = $siswa->rombelPada())
                            <span><i class="bi bi-people me-1"></i>{{ $kelas->nama_rombel }}</span>
                        @endif
                        <span><i class="bi bi-calendar3 me-1"></i>{{ now()->translatedFormat('l, d F Y') }}</span>

                        {{-- Jam ikut ditampilkan karena tombol di sebelahnya
                             baru masuk akal bila peserta tahu sekarang pukul
                             berapa: yang ditunggu adalah jadwal ujiannya
                             tiba. --}}
                        <span class="waktu-kini">
                            <i class="bi bi-clock me-1"></i><span id="jamSekarang">{{ now()->format('H:i:s') }}</span>
                            <button type="button" class="tombol-segarkan" id="tombolSegarkan"
                                    title="Segarkan daftar ujian">
                                <i class="bi bi-arrow-clockwise"></i>
                                <span class="teks">Segarkan</span>
                            </button>
                        </span>
                    </div>
                </div>

                <div class="angka-ringkas">
                    <div>
                        <div class="jumlah">{{ $berlangsung->count() }}</div>
                        <div class="label">Bisa dikerjakan</div>
                    </div>
                    <div>
                        <div class="jumlah">{{ $selesai->count() }}</div>
                        <div class="label">Sudah selesai</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ---------- Ujian yang sedang dibuka ---------- --}}
        @if ($berlangsung->isNotEmpty())
            <div class="d-flex align-items-center gap-2 mb-2 px-1">
                <span class="titik-hidup"></span>
                <h2 class="h6 mb-0 fw-semibold">Ujian yang bisa dikerjakan sekarang</h2>
            </div>

            @foreach ($berlangsung as $p)
                <div class="card kartu-aktif mb-3">
                    <div class="card-body d-flex flex-wrap gap-3 justify-content-between align-items-center">
                        <div class="flex-grow-1" style="min-width:15rem">
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                <span class="h6 mb-0 fw-bold">{{ $p->ujian->nama_ujian }}</span>
                                @if ($p->status === UjianPeserta::MULAI)
                                    <span class="pil pil-kuning">
                                        <i class="bi bi-hourglass-split"></i>
                                        Sisa {{ intdiv($p->sisaWaktuDetik(), 60) }} menit
                                    </span>
                                @endif
                            </div>

                            <div class="d-flex flex-wrap gap-3 small text-muted">
                                <span><i class="bi bi-journal-bookmark me-1"></i>{{ $p->ujian->mataPelajaran->nama_mapel ?? '-' }}</span>
                                <span><i class="bi bi-stopwatch me-1"></i>{{ $p->ujian->durasi_menit }} menit</span>
                                <span><i class="bi bi-door-closed me-1"></i>Ditutup {{ $p->ujian->waktu_selesai->format('H:i') }}</span>
                            </div>
                        </div>

                        <a href="{{ route('siswa.ujian.konfirmasi', $p) }}" class="btn btn-ujian btn-lg px-4">
                            {{ $p->status === UjianPeserta::MULAI ? 'Lanjutkan' : 'Mulai Ujian' }}
                            <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            @endforeach
        @else
            <div class="card mb-3">
                <div class="card-body text-center py-5">
                    <div class="ikon-kosong mb-3"><i class="bi bi-cup-hot"></i></div>
                    <div class="fw-semibold mb-1">Tidak ada ujian yang sedang dibuka</div>
                    <div class="small text-muted">Jadwal berikutnya akan muncul di sini saat waktunya tiba.</div>
                </div>
            </div>
        @endif

        {{-- ---------- Riwayat & jadwal lain ---------- --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-ul me-1 text-muted"></i>Ujian Lainnya</span>
                @if ($lainnya->isNotEmpty())
                    <span class="small text-muted">{{ $lainnya->count() }} jadwal</span>
                @endif
            </div>

            <div class="list-group list-group-flush">
                @forelse ($lainnya as $p)
                    <div class="list-group-item baris-ujian d-flex flex-wrap gap-3 justify-content-between align-items-center">
                        <div class="d-flex gap-3 align-items-center flex-grow-1" style="min-width:14rem">
                            @php
                                [$ikon, $warna] = match (true) {
                                    $p->status === UjianPeserta::SELESAI => ['bi-check2-circle', 'hijau'],
                                    $p->status === UjianPeserta::DIBATALKAN => ['bi-x-circle', 'merah'],
                                    $p->ujian->belum_mulai => ['bi-calendar-event', 'biru'],
                                    default => ['bi-clock-history', 'abu'],
                                };
                            @endphp
                            <span class="ikon-status ikon-{{ $warna }}"><i class="bi {{ $ikon }}"></i></span>

                            <div>
                                <div class="fw-semibold">{{ $p->ujian->nama_ujian }}</div>
                                <div class="small text-muted">
                                    {{ $p->ujian->mataPelajaran->nama_mapel ?? '-' }}
                                    &middot; {{ $p->ujian->waktu_mulai->format('d/m/Y H:i') }}
                                    — {{ $p->ujian->waktu_selesai->format('H:i') }}
                                </div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            @if ($p->status === UjianPeserta::SELESAI)
                                @if ($p->ujian->tampilkan_hasil)
                                    <span class="pil pil-hijau"><i class="bi bi-check2"></i>Selesai</span>
                                    <a href="{{ route('siswa.ujian.hasil', $p) }}" class="btn btn-sm btn-outline-primary">
                                        Lihat hasil<i class="bi bi-chevron-right ms-1"></i>
                                    </a>
                                @else
                                    <span class="pil pil-hijau"><i class="bi bi-check2"></i>Selesai</span>
                                    <span class="small text-muted">Nilai belum diumumkan</span>
                                @endif
                            @elseif ($p->status === UjianPeserta::DIBATALKAN)
                                <span class="pil pil-merah"><i class="bi bi-x-lg"></i>Dibatalkan</span>
                            @elseif ($p->ujian->belum_mulai)
                                <span class="pil pil-abu">
                                    <i class="bi bi-calendar-event"></i>Dibuka {{ $p->ujian->waktu_mulai->diffForHumans() }}
                                </span>
                            @else
                                <span class="pil pil-abu"><i class="bi bi-clock-history"></i>Waktu sudah lewat</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="list-group-item text-center text-muted py-5">
                        <div class="ikon-kosong mb-3"><i class="bi bi-inbox"></i></div>
                        Belum ada ujian lain untuk Anda.
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</div>
@endsection

@push('head')
<style>
    /* Kartu sapaan — satu-satunya blok berwarna kuat di halaman ini, sebagai
       penanda "ini ruang saya" sebelum daftar ujian yang harus tetap tenang. */
    .kartu-sapaan {
        background:linear-gradient(120deg,#0d1f38 0%,#1b3a63 55%,#2f6fb5 100%);
        color:#fff;
        overflow:hidden;
        position:relative;
    }
    .kartu-sapaan::after {
        content:''; position:absolute; right:-3rem; top:-5rem;
        width:16rem; height:16rem; border-radius:50%;
        background:rgba(255,255,255,.06);
    }
    .kartu-sapaan .avatar {
        width:3.5rem; height:3.5rem; flex:none; border-radius:1rem;
        display:grid; place-items:center; font-size:1.5rem; font-weight:700;
        background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.22);
    }
    .kartu-sapaan .sapaan { font-size:.85rem; color:rgba(255,255,255,.7); }
    .kartu-sapaan .nama { font-size:1.35rem; font-weight:700; line-height:1.25; }
    .kartu-sapaan .rincian {
        display:flex; flex-wrap:wrap; gap:.25rem 1rem;
        font-size:.82rem; color:rgba(255,255,255,.75); margin-top:.35rem;
    }
    .kartu-sapaan .waktu-kini { display:inline-flex; align-items:center; gap:.5rem; }
    .kartu-sapaan .waktu-kini #jamSekarang {
        font-family:ui-monospace,SFMono-Regular,'Cascadia Mono',Consolas,monospace;
        letter-spacing:.03em;
    }

    /* Tombol segarkan sengaja diberi tepi jelas, bukan sekadar ikon polos:
       di ExamBro tidak ada bilah alamat, jadi inilah satu-satunya cara peserta
       memuat ulang daftarnya ketika pengawas baru membuka ujian. */
    .tombol-segarkan {
        display:inline-flex; align-items:center; gap:.35rem;
        padding:.15rem .6rem; border-radius:2rem;
        font-size:.78rem; font-weight:600; line-height:1.6;
        color:#fff; background:rgba(255,255,255,.14);
        border:1px solid rgba(255,255,255,.35);
        cursor:pointer; transition:background-color .12s, border-color .12s;
    }
    .tombol-segarkan:hover  { background:rgba(255,255,255,.26); border-color:rgba(255,255,255,.6); }
    .tombol-segarkan:active { background:rgba(255,255,255,.34); }
    .tombol-segarkan:disabled { opacity:.7; cursor:default; }
    .tombol-segarkan.berputar .bi { animation:putar-segarkan .7s linear infinite; }

    @keyframes putar-segarkan { to { transform:rotate(360deg); } }

    .kartu-sapaan .angka-ringkas { display:flex; gap:1.75rem; position:relative; z-index:1; }
    .kartu-sapaan .angka-ringkas .jumlah { font-size:1.6rem; font-weight:700; line-height:1; }
    .kartu-sapaan .angka-ringkas .label { font-size:.72rem; color:rgba(255,255,255,.7); margin-top:.2rem; }

    /* Ujian yang sedang dibuka diberi tepi hijau supaya langsung terlihat
       berbeda dari daftar riwayat di bawahnya. */
    .kartu-aktif { border-color:rgba(0,168,107,.45); box-shadow:0 0 0 3px rgba(0,168,107,.08), var(--bayang); }

    .baris-ujian { padding:.9rem 1.1rem; transition:background-color .12s; }
    .baris-ujian:hover { background:#f8fbff; }

    .ikon-status {
        width:2.4rem; height:2.4rem; flex:none; border-radius:.65rem;
        display:grid; place-items:center; font-size:1.05rem;
    }
    .ikon-hijau { background:var(--hijau-lembut); color:#046c48; }
    .ikon-merah { background:#fdeaec; color:#9b1c28; }
    .ikon-biru  { background:#e8f1fb; color:#1b3a63; }
    .ikon-abu   { background:#eef2f7; color:#64748b; }

    .ikon-kosong {
        width:3.5rem; height:3.5rem; margin-inline:auto; border-radius:1rem;
        display:grid; place-items:center; font-size:1.5rem;
        background:#eef2f7; color:#94a3b8;
    }

    @media (max-width: 575.98px) {
        .kartu-sapaan .angka-ringkas { width:100%; justify-content:space-between; gap:1rem; }
        .kartu-sapaan .nama { font-size:1.15rem; }

        /* Ikonnya tetap, tulisannya yang mengalah — baris rincian di layar
           sempit sudah penuh oleh NISN, kelas, dan tanggal.

           Kotaknya justru diperbesar, bukan dikecilkan: yang menekan tombol ini
           adalah jari di layar ponsel, dan sisa ikon setinggi 27 px terlalu
           kecil untuk dikenai dengan yakin. */
        .tombol-segarkan .teks { display:none; }
        .tombol-segarkan {
            min-width:2.6rem; min-height:2.6rem; padding:.2rem;
            justify-content:center; font-size:1rem;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    /*
     * Jam berjalan dan tombol segarkan.
     *
     * Keduanya untuk satu keadaan yang sama: peserta sudah masuk sebelum
     * ujiannya dibuka, lalu menunggu. Di ExamBro tidak ada bilah alamat,
     * sehingga tanpa tombol ini satu-satunya cara memuat ulang daftar adalah
     * keluar dari peramban ujian — yang justru terhitung pelanggaran.
     */
    (function () {
        'use strict';

        var elJam = document.getElementById('jamSekarang');
        var tombol = document.getElementById('tombolSegarkan');

        if (elJam) {
            /*
             * Dimulai dari jam server, lalu berjalan sendiri — jam perangkat
             * siswa kerap meleset, sedangkan yang menentukan jadwal ujian
             * adalah jam server.
             *
             * Yang dihitung detik sejak tengah malam, bukan cap waktu epoch:
             * epoch harus diterjemahkan kembali lewat zona waktu perangkat,
             * dan perangkat yang zonanya keliru akan menampilkan jam yang
             * meleset berjam-jam.
             */
            var detik = @json((int) now()->secondsSinceMidnight());

            setInterval(function () {
                detik = (detik + 1) % 86400;

                elJam.textContent = [Math.floor(detik / 3600), Math.floor((detik % 3600) / 60), detik % 60]
                    .map(function (v) { return String(v).padStart(2, '0'); })
                    .join(':');
            }, 1000);
        }

        if (tombol) {
            tombol.addEventListener('click', function () {
                tombol.disabled = true;
                tombol.classList.add('berputar');
                window.location.reload();
            });
        }
    })();
</script>
@endpush
