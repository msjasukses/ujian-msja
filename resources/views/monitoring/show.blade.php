@extends('layouts.app')
@section('title', 'Monitoring — '.$ujian->nama_ujian)
@section('subtitle', $ujian->kode_ujian.' · '.$ujian->waktu_mulai->format('d/m/Y H:i').' — '.$ujian->waktu_selesai->format('H:i').' · '.$jumlahButir.' butir')

@section('content')
@php use App\Models\UjianPeserta; use App\Support\Referensi; @endphp

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
        <div class="d-flex flex-wrap gap-3 align-items-center">
            <span class="badge badge-soft fs-6">Token: <code class="user-select-all">{{ $ujian->token ?: '—' }}</code></span>
            <span class="badge {{ $ujian->sedang_berlangsung ? 'text-bg-success' : 'badge-soft' }} fs-6">
                {{ $ujian->sedang_berlangsung ? 'Sedang berlangsung' : $ujian->status_label }}
            </span>
            <span class="small text-muted">
                Diperbarui otomatis tiap 15 detik &middot; terakhir <span id="waktuUpdate">{{ now()->format('H:i:s') }}</span>
            </span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('monitoring.export', ['ujian' => $ujian, 'status' => $statusTerpilih, 'rombongan_belajar_id' => request('rombongan_belajar_id')]) }}" class="btn btn-sm btn-outline-success">
                <i class="bi bi-file-earmark-excel me-1"></i>Export
            </a>
            <form method="POST" action="{{ route('monitoring.selesai-semua', $ujian) }}"
                  data-konfirmasi="Kumpulkan paksa lembar jawaban SEMUA peserta yang masih mengerjakan?">
                @csrf
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-stop-circle me-1"></i>Kumpulkan Semua</button>
            </form>
        </div>
    </div>
</div>

@php $jumlahTerkunci = $ringkasan['terkunci']; @endphp

<span id="bilahTerkunci" data-jumlah="{{ $jumlahTerkunci }}" hidden></span>

@if ($ujian->proteksi_ketat && $jumlahTerkunci)
    @php $pesertaTerkunci = $semuaPeserta->filter(fn ($p) => (bool) $p->dikunci_at); @endphp

    <div class="alert alert-danger d-flex flex-wrap align-items-start gap-2">
        {{-- Ikon dan teks satu kelompok, supaya ikonnya tidak tertinggal
             sendirian di baris atas saat tombol turun di layar sempit. --}}
        <div class="d-flex gap-2 flex-grow-1" style="min-width:16rem">
        <i class="bi bi-lock-fill fs-5"></i>
        <div class="flex-grow-1">
            <strong>{{ $jumlahTerkunci }} lembar jawaban terkunci</strong> karena pelanggaran berulang.
            Peserta tidak dapat melanjutkan sampai diizinkan pengawas — satu per satu lewat tombol
            <i class="bi bi-unlock"></i> <strong>Aktifkan kembali</strong> pada barisnya, atau sekaligus.

            {{-- Nama disebut sebelum tombolnya ditekan: izin serentak tidak
                 boleh diberikan tanpa tahu kepada siapa. --}}
            <div class="mt-1">
                @foreach ($pesertaTerkunci as $pt)
                    <span class="badge text-bg-light border me-1 mb-1">
                        {{ $pt->siswa->nama_siswa ?? '—' }}
                        <span class="text-muted fw-normal">· {{ $pt->rombel->nama_rombel ?? '-' }} · {{ $pt->pelanggaran }}x</span>
                    </span>
                @endforeach
            </div>
        </div>
        </div>

        <form method="POST" action="{{ route('monitoring.buka-kunci-semua', $ujian) }}" class="ms-auto"
              data-konfirmasi="Izinkan {{ $jumlahTerkunci }} peserta yang terkunci melanjutkan ujian? Hitungan pelanggaran mereka dinolkan; rincian pelanggarannya tetap tercatat.">
            @csrf
            @if (request('rombongan_belajar_id'))
                <input type="hidden" name="rombongan_belajar_id" value="{{ request('rombongan_belajar_id') }}">
            @endif
            <button class="btn btn-danger fw-semibold text-nowrap">
                <i class="bi bi-unlock-fill me-1"></i>Izinkan semua lanjut ({{ $jumlahTerkunci }})
            </button>
        </form>
    </div>
@endif

@php $pesertaGanda = $semuaPeserta->filter(fn ($p) => $p->login_ganda > 0); @endphp

<span id="bilahGanda" data-jumlah="{{ $pesertaGanda->count() }}" hidden></span>

{{-- Nama disebut langsung, bukan hanya jumlahnya: yang harus dilakukan
     pengawas adalah mendatangi anak-anak itu dan memeriksa siapa yang
     memegang perangkatnya. --}}
@if ($pesertaGanda->isNotEmpty())
    <div class="alert alert-warning d-flex align-items-start gap-2">
        <i class="bi bi-people-fill fs-5"></i>
        <div>
            <strong>{{ $pesertaGanda->count() }} peserta login ganda</strong> selama ujian —
            akunnya dipakai masuk dari perangkat lain. Periksa langsung siapa yang memegang perangkatnya:
            <div class="mt-1">
                @foreach ($pesertaGanda as $pg)
                    <span class="badge text-bg-light border me-1 mb-1" title="{{ $pg->login_ganda_ket }}">
                        {{ $pg->siswa->nama_siswa ?? '—' }}
                        <span class="text-muted fw-normal">· {{ $pg->rombel->nama_rombel ?? '-' }} · {{ $pg->login_ganda }}x</span>
                    </span>
                @endforeach
            </div>
        </div>
    </div>
@endif

{{-- Setiap kartu sekaligus penyaring: diklik, tabel di bawah hanya
     menampilkan peserta yang dihitung kartu itu. Angka di kartu selalu dari
     seluruh peserta, jadi tidak ikut berubah saat tabelnya disaring. --}}
@php
    $kartu = [
        ['kunci' => null,        'label' => 'Terdaftar',          'ikon' => 'bi-people',               'warna' => 'primary',   'id' => 'statTerdaftar', 'nilai' => $ringkasan['terdaftar']],
        ['kunci' => 'belum',     'label' => 'Belum mulai',        'ikon' => 'bi-hourglass',            'warna' => 'secondary', 'id' => 'statBelum',     'nilai' => $ringkasan['belum']],
        ['kunci' => 'sedang',    'label' => 'Sedang mengerjakan', 'ikon' => 'bi-pencil-square',        'warna' => 'warning',   'id' => 'statSedang',    'nilai' => $ringkasan['sedang']],
        ['kunci' => 'selesai',   'label' => 'Selesai',            'ikon' => 'bi-check2-all',           'warna' => 'success',   'id' => 'statSelesai',   'nilai' => $ringkasan['selesai']],
        ['kunci' => 'melanggar', 'label' => 'Melanggar',          'ikon' => 'bi-exclamation-triangle', 'warna' => 'danger',    'id' => 'statMelanggar', 'nilai' => $ringkasan['melanggar']],
    ];
@endphp

<div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3 mb-3">
    @foreach ($kartu as $k)
        @php $aktif = $statusTerpilih === $k['kunci']; @endphp
        <div class="col">
            <a href="{{ route('monitoring.show', ['ujian' => $ujian, 'status' => $k['kunci'], 'rombongan_belajar_id' => request('rombongan_belajar_id')]) }}"
               class="kartu-saring {{ $aktif ? 'aktif' : '' }}"
               title="Tampilkan daftar: {{ $k['label'] }}"
               @if ($aktif) aria-current="true" @endif>
                <div class="card stat-card h-100 {{ $aktif ? 'border border-2 border-'.$k['warna'] : '' }}">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <div class="rounded-3 d-flex align-items-center justify-content-center bg-{{ $k['warna'] }}-subtle text-{{ $k['warna'] }}"
                             style="width:2.75rem;height:2.75rem;flex:none"><i class="bi {{ $k['ikon'] }} fs-5"></i></div>
                        <div>
                            <div class="stat-value" id="{{ $k['id'] }}">{{ $k['nilai'] }}</div>
                            <div class="stat-label">{{ $k['label'] }}</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <span>
                    Peserta
                    @if ($statusTerpilih)
                        <span class="badge text-bg-dark ms-1">{{ \App\Http\Controllers\MonitoringController::SARING_STATUS[$statusTerpilih] }} · {{ $peserta->count() }}</span>
                        <a href="{{ route('monitoring.show', ['ujian' => $ujian, 'rombongan_belajar_id' => request('rombongan_belajar_id')]) }}"
                           class="small ms-1">tampilkan semua</a>
                    @endif
                </span>
                <form class="d-flex gap-2">
                    <select name="status" class="form-select form-select-sm" data-kirim-otomatis aria-label="Saring status">
                        <option value="">Semua status</option>
                        @foreach (\App\Http\Controllers\MonitoringController::SARING_STATUS as $nilai => $label)
                            <option value="{{ $nilai }}" @selected($statusTerpilih === $nilai)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="rombongan_belajar_id" class="form-select form-select-sm" data-kirim-otomatis aria-label="Saring kelas">
                        <option value="">Semua kelas</option>
                        @foreach (Referensi::rombel() as $k)
                            <option value="{{ $k->id }}" @selected(request('rombongan_belajar_id') == $k->id)>{{ $k->nama_rombel }}</option>
                        @endforeach
                    </select>

                    {{-- Jejak aktivitas punya halaman sendiri: di layar ini yang
                         dibutuhkan pengawas keadaan terkini, bukan riwayatnya. --}}
                    <a href="{{ route('monitoring.jejak', [$ujian] + request()->only('rombongan_belajar_id')) }}"
                       class="btn btn-sm btn-outline-secondary text-nowrap">
                        <i class="bi bi-clock-history me-1"></i>Jejak Aktivitas
                    </a>
                </form>
            </div>

            <div class="table-responsive" style="max-height:38rem">
                <table class="table table-hover table-sticky align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:3rem">#</th>
                            <th>Siswa</th>
                            <th>Kelas</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Terjawab</th>
                            <th class="text-center">Sisa Waktu</th>
                            <th class="text-center">Nilai</th>
                            <th class="text-center">Pelanggaran</th>
                            <th>Perangkat</th>
                            <th class="text-end" style="width:9rem">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tabelPeserta">
                        @forelse ($peserta as $i => $p)
                            <tr class="{{ $p->login_ganda ? 'table-warning' : '' }}">
                                <td class="text-muted small">{{ $i + 1 }}</td>
                                <td>
                                    {{ $p->siswa->nama_siswa ?? '—' }}
                                    <div class="small text-muted">{{ $p->siswa->nisn ?? '-' }}</div>
                                </td>
                                <td class="small">{{ $p->rombel->nama_rombel ?? '-' }}</td>
                                <td class="text-center">
                                    <span class="badge {{ ['terdaftar' => 'badge-soft', 'mulai' => 'text-bg-warning',
                                                           'selesai' => 'text-bg-success', 'dibatalkan' => 'text-bg-danger'][$p->status] }}">
                                        {{ $p->status_label }}
                                    </span>
                                </td>
                                <td class="text-center small">{{ $p->terjawab }} / {{ $jumlahButir }}</td>
                                <td class="text-center small font-monospace">
                                    {{ $p->status === UjianPeserta::MULAI
                                        ? sprintf('%02d:%02d', intdiv($p->sisaWaktuDetik(), 60), $p->sisaWaktuDetik() % 60)
                                        : '-' }}
                                </td>
                                <td class="text-center">{{ $p->status === UjianPeserta::SELESAI ? (float) $p->nilai : '-' }}</td>
                                <td class="text-center">
                                    @if ($p->dikunci_at)
                                        <span class="badge text-bg-danger" title="Dikunci {{ $p->dikunci_at->format('H:i:s') }}">
                                            <i class="bi bi-lock-fill me-1"></i>{{ $p->pelanggaran }}x
                                        </span>
                                    @elseif ($p->pelanggaran)
                                        <span class="badge text-bg-warning">{{ $p->pelanggaran }}x</span>
                                    @elseif (! $p->melanggar)
                                        <span class="text-muted small">—</span>
                                    @endif
                                    {{-- Rincian dari jejak, jadi tetap terbaca walau hitungannya
                                         sudah dinolkan karena kunci dibuka atau direset. --}}
                                    @if ($p->melanggar)
                                        <div class="rincian-langgar">
                                            @foreach ($p->rincian_pelanggaran as $x)
                                                <span>{{ $x['label'] }} {{ $x['jumlah'] }}×</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="small text-muted">
                                    {{ $p->browser ?: '-' }}
                                    <div>{{ $p->ip_address ?: '' }}</div>
                                    @if ($p->reset_count)
                                        <span class="badge text-bg-warning">{{ $p->reset_count }}x reset</span>
                                    @endif
                                    @if ($p->login_ganda)
                                        <span class="badge text-bg-danger" title="{{ $p->login_ganda_ket }}">
                                            <i class="bi bi-people-fill me-1"></i>Login ganda {{ $p->login_ganda }}x
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @if ($p->dikunci_at)
                                        <form method="POST" action="{{ route('monitoring.buka-kunci', [$ujian, $p]) }}" class="d-inline"
                                              data-konfirmasi="Aktifkan kembali lembar {{ $p->siswa->nama_siswa ?? 'siswa ini' }}? Hitungan pelanggarannya dinolkan dan peserta dapat melanjutkan ujian.">
                                            @csrf
                                            <button class="btn btn-sm btn-danger" title="Aktifkan kembali lembar terblokir">
                                                <i class="bi bi-unlock"></i>
                                            </button>
                                        </form>
                                    @endif
                                    @if ($p->status === UjianPeserta::MULAI)
                                        <form method="POST" action="{{ route('monitoring.selesai', [$ujian, $p]) }}" class="d-inline"
                                              data-konfirmasi="Kumpulkan paksa lembar jawaban {{ $p->siswa->nama_siswa ?? 'siswa ini' }}?">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger" title="Kumpulkan paksa">
                                                <i class="bi bi-stop-circle"></i>
                                            </button>
                                        </form>
                                    @endif
                                    @if (in_array($p->status, [UjianPeserta::MULAI, UjianPeserta::SELESAI]))
                                        <form method="POST" action="{{ route('monitoring.reset', [$ujian, $p]) }}" class="d-inline"
                                              data-konfirmasi="Reset pengerjaan {{ $p->siswa->nama_siswa ?? 'siswa ini' }}? Seluruh jawabannya dihapus dan peserta mengulang ujian dari awal.">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-warning" title="Reset pengerjaan">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-kosong kolom="10" icon="bi-people" :pesan="$statusTerpilih ? 'Tidak ada peserta dengan status ini.' : 'Belum ada peserta pada ujian ini.'" />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div class="mt-3">
    <a href="{{ route('monitoring.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
    <a href="{{ route('laporan.nilai.show', $ujian) }}" class="btn btn-outline-primary">
        <i class="bi bi-card-checklist me-1"></i>Lihat Daftar Nilai
    </a>
</div>

@push('head')
<style>
    .kartu-saring { display:block; height:100%; color:inherit; text-decoration:none; }
    .kartu-saring .stat-card { transition:box-shadow .12s, transform .12s; }
    .kartu-saring:hover .stat-card { box-shadow:0 .35rem 1rem rgba(16,32,64,.12); transform:translateY(-1px); }

    .rincian-langgar { margin-top:.25rem; font-size:.72rem; line-height:1.35; color:var(--bs-danger-text-emphasis); }
    .rincian-langgar span { display:inline-block; white-space:nowrap; }
    .rincian-langgar span + span::before { content:'· '; color:var(--bs-secondary-color); }
</style>
@endpush

@push('scripts')
<script>
    // Menyegarkan tabel peserta tanpa memuat ulang halaman, supaya pengawas
    // bisa membiarkan layar ini terbuka selama ujian berlangsung.
    const urlData = @json(route('monitoring.data', $ujian)) + window.location.search;
    const jumlahButir = @json($jumlahButir);
    const csrfPengawas = document.querySelector('meta[name=csrf-token]').content;

    // Pola alamat tiap aksi; nomor pesertanya disisipkan saat baris dibangun.
    // Penanda dipakai, bukan angka, karena nomor ujiannya juga ada di alamat
    // yang sama dan bisa ikut tertimpa.
    const polaBukaKunci = @json(route('monitoring.buka-kunci', [$ujian, '__PESERTA__']));
    const polaReset     = @json(route('monitoring.reset', [$ujian, '__PESERTA__']));
    const polaSelesai   = @json(route('monitoring.selesai', [$ujian, '__PESERTA__']));

    /**
     * Nama siswa berasal dari Data Center dan masuk ke dalam HTML maupun ke
     * dalam atribut konfirmasi, jadi tanda kutip dan kurung sudutnya dijinakkan
     * lebih dulu.
     */
    function aman(teks) {
        return String(teks === null || teks === undefined ? '' : teks)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    const kelasStatus = {
        terdaftar: 'badge-soft', mulai: 'text-bg-warning',
        selesai: 'text-bg-success', dibatalkan: 'text-bg-danger',
    };

    async function segarkan() {
        try {
            const respons = await fetch(urlData, { headers: { 'Accept': 'application/json' } });
            if (!respons.ok) return;
            const data = await respons.json();

            document.getElementById('waktuUpdate').textContent = data.diperbarui;
            document.getElementById('statBelum').textContent   = data.ringkasan.belum;
            document.getElementById('statSedang').textContent  = data.ringkasan.sedang;
            document.getElementById('statSelesai').textContent = data.ringkasan.selesai;
            document.getElementById('statTerdaftar').textContent = data.ringkasan.terdaftar;
            document.getElementById('statMelanggar').textContent = data.ringkasan.melanggar;

            // Kunci baru harus segera terlihat, jadi halaman dimuat ulang untuk
            // memunculkan peringatannya. Hanya saat jumlahnya bertambah:
            // berkurang berarti pengawas sendiri yang baru membukanya, dan
            // memuat ulang di situ justru menghapus pesan berhasilnya.
            const bilahKunci = document.getElementById('bilahTerkunci');
            if (bilahKunci && data.ringkasan.terkunci > Number(bilahKunci.dataset.jumlah)) {
                window.location.reload();
            }
            if (bilahKunci) bilahKunci.dataset.jumlah = data.ringkasan.terkunci;

            // Sama untuk login ganda: bilah peringatannya menyebut nama, dan
            // nama itu hanya bisa dirakit ulang oleh halaman yang dimuat.
            const bilahGanda = document.getElementById('bilahGanda');
            if (bilahGanda && data.ringkasan.login_ganda > Number(bilahGanda.dataset.jumlah)) {
                window.location.reload();
            }
            if (bilahGanda) bilahGanda.dataset.jumlah = data.ringkasan.login_ganda;

            if (data.peserta.length === 0) {
                document.getElementById('tabelPeserta').innerHTML =
                    '<tr><td colspan="10" class="text-center text-muted py-4">Tidak ada peserta dengan status ini.</td></tr>';
                return;
            }

            document.getElementById('tabelPeserta').innerHTML = data.peserta.map(function (p, i) {
                const reset = p.reset_count ? `<span class="badge text-bg-warning">${p.reset_count}x reset</span>` : '';
                const ganda = p.login_ganda
                    ? ` <span class="badge text-bg-danger" title="${aman(p.login_ganda_ket)}"><i class="bi bi-people-fill me-1"></i>Login ganda ${p.login_ganda}x</span>`
                    : '';

                // Lembar terkunci ditandai merah supaya pengawas melihatnya
                // tanpa harus memuat ulang halaman lebih dulu.
                const langgar = p.terkunci
                    ? `<span class="badge text-bg-danger"><i class="bi bi-lock-fill me-1"></i>${p.pelanggaran}x</span>`
                    : (p.pelanggaran ? `<span class="badge text-bg-warning">${p.pelanggaran}x</span>`
                        : (p.melanggar ? '' : '<span class="text-muted small">—</span>'));
                const rincian = p.melanggar
                    ? `<div class="rincian-langgar">${p.rincian.map(x => `<span>${aman(x.label)} ${x.jumlah}×</span>`).join('')}</div>`
                    : '';
                return `<tr class="${p.login_ganda ? 'table-warning' : ''}">
                    <td class="text-muted small">${i + 1}</td>
                    <td>${aman(p.nama)}<div class="small text-muted">${aman(p.nisn)}</div></td>
                    <td class="small">${aman(p.kelas)}</td>
                    <td class="text-center"><span class="badge ${kelasStatus[p.status] || 'badge-soft'}">${aman(p.status_label)}</span></td>
                    <td class="text-center small">${p.terjawab} / ${jumlahButir}</td>
                    <td class="text-center small font-monospace">${p.sisa_waktu}</td>
                    <td class="text-center">${p.nilai !== null ? p.nilai : '-'}</td>
                    <td class="text-center">${langgar}${rincian}</td>
                    <td class="small text-muted">${aman(p.browser || '-')}<div>${aman(p.ip || '')}</div>${reset}${ganda}</td>
                    <td class="text-end text-nowrap">${selAksi(p)}</td>
                </tr>`;
            }).join('');
        } catch (e) {
            // Gagal menyegarkan (mis. jaringan putus) dibiarkan diam —
            // tabel tetap menampilkan data terakhir yang berhasil diambil.
        }
    }

    function tombolAksi(pola, p, kelas, ikon, judul, pesan) {
        return `<form method="POST" action="${pola.replace('__PESERTA__', p.id)}" class="d-inline"
                      data-konfirmasi="${aman(pesan)}">
                    <input type="hidden" name="_token" value="${csrfPengawas}">
                    <button class="btn btn-sm ${kelas}" title="${aman(judul)}"><i class="bi ${ikon}"></i></button>
                </form>`;
    }

    /**
     * Sel aksi dibangun ulang tiap penyegaran, bukan digantikan tulisan "muat
     * ulang untuk aksi" seperti sebelumnya.
     *
     * Pengawas membutuhkan tombol-tombol ini justru pada saat tabelnya sedang
     * hidup: peserta yang lembarnya terblokir atau harus direset sedang
     * berhenti mengerjakan sementara hitung mundurnya terus berjalan, dan
     * menunggu penyegaran berikutnya untuk memuat ulang halaman adalah waktu
     * yang hilang percuma.
     */
    function selAksi(p) {
        const nama = p.nama || 'siswa ini';
        const tombol = [];

        // Mengaktifkan kembali didahulukan; inilah yang paling mendesak.
        if (p.terkunci) {
            tombol.push(tombolAksi(polaBukaKunci, p, 'btn-danger', 'bi-unlock',
                'Aktifkan kembali lembar terblokir',
                `Aktifkan kembali lembar ${nama}? Hitungan pelanggarannya dinolkan dan peserta dapat melanjutkan ujian.`));
        }

        if (p.status === 'mulai') {
            tombol.push(tombolAksi(polaSelesai, p, 'btn-outline-danger', 'bi-stop-circle',
                'Kumpulkan paksa',
                `Kumpulkan paksa lembar jawaban ${nama}?`));
        }

        if (p.status === 'mulai' || p.status === 'selesai') {
            tombol.push(tombolAksi(polaReset, p, 'btn-outline-warning', 'bi-arrow-counterclockwise',
                'Reset pengerjaan',
                `Reset pengerjaan ${nama}? Seluruh jawabannya dihapus dan peserta mengulang ujian dari awal.`));
        }

        return tombol.length ? tombol.join(' ') : '<span class="small text-muted">—</span>';
    }

    setInterval(segarkan, 15000);
</script>
@endpush
@endsection
