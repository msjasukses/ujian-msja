@extends('layouts.app')
@section('title', 'Cadangan & Pemulihan')
@section('subtitle', 'Backup dan restore seluruh data aplikasi ujian')

@section('content')
@php
    $ukuran = function (?float $bita): string {
        if ($bita === null) return '-';
        foreach (['B', 'KB', 'MB', 'GB'] as $satuan) {
            if ($bita < 1024 || $satuan === 'GB') {
                return number_format($bita, $satuan === 'B' ? 0 : 1, ',', '.').' '.$satuan;
            }
            $bita /= 1024;
        }
    };
    $labelJenis = [
        'manual' => ['Manual', 'badge-soft'],
        'otomatis' => ['Otomatis', 'text-bg-info-subtle text-info-emphasis border border-info-subtle'],
        'pengaman' => ['Pengaman', 'text-bg-warning-subtle text-warning-emphasis border border-warning-subtle'],
        'unggahan' => ['Unggahan', 'text-bg-primary-subtle text-primary-emphasis border border-primary-subtle'],
    ];
@endphp

@unless ($zipTersedia)
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-octagon me-1"></i>
        Ekstensi PHP <strong>zip</strong> belum aktif di server ini, sehingga cadangan belum bisa dibuat maupun dipulihkan.
        Aktifkan lewat panel hosting (mis. Virtualmin → PHP Options) atau minta bantuan pengelola server.
    </div>
@endunless

<div class="alert alert-light border small">
    <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-1"></i>Isi cadangan</div>
    Bank soal, topik, paket soal, jadwal & peserta ujian, lembar jawaban dan nilai, remidial & pengayaan,
    log login, pengaturan, serta akun admin/operator — ditambah gambar dan audio/video soal bila dicentang.
    <span class="text-muted">
        Data siswa, guru, kelas, dan CP-TP-ATP tidak termasuk: semuanya milik aplikasi Data Center dan
        SIM Kurikulum yang punya cadangan sendiri.
    </span>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-database-down me-1"></i>Buat Cadangan</div>
            <form method="POST" action="{{ route('cadangan.buat') }}" class="card-body d-flex flex-column">
                @csrf
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="sertakan_berkas" value="1" id="sertakan_berkas" checked>
                    <label class="form-check-label" for="sertakan_berkas">
                        Sertakan gambar &amp; audio/video soal
                        <span class="text-muted small">(saat ini {{ $ukuran($ukuranMedia) }})</span>
                    </label>
                </div>
                <p class="small text-muted mb-3">
                    Cadangan tersimpan di server pada folder yang tidak bisa dibuka dari web.
                    Unduh dan simpan salinannya di tempat lain — cadangan yang hanya ada di server
                    ikut hilang bila servernya rusak.
                    @if ($sisaDisk)
                        Sisa ruang disk server: <strong>{{ $ukuran($sisaDisk) }}</strong>.
                    @endif
                </p>
                <div class="mt-auto">
                    <button class="btn btn-primary" @disabled(! $zipTersedia)>
                        <i class="bi bi-database-down me-1"></i>Buat Cadangan Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-upload me-1"></i>Unggah Berkas Cadangan</div>
            <form method="POST" action="{{ route('cadangan.unggah') }}" enctype="multipart/form-data" class="card-body">
                @csrf
                <input type="file" name="berkas" accept=".zip"
                       class="form-control mb-2 @error('berkas') is-invalid @enderror" required>
                @error('berkas')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <p class="small text-muted mb-3">
                    Berkas diperiksa lalu masuk ke daftar di bawah — <strong>belum dipulihkan</strong>.
                    Batas unggah server ini {{ $batasUnggah }}. Berkas yang lebih besar dapat disalin lewat
                    SFTP/File Manager ke folder <code>storage/app/private/cadangan</code>, lalu akan muncul di daftar.
                </p>
                <button class="btn btn-outline-primary" @disabled(! $zipTersedia)>
                    <i class="bi bi-upload me-1"></i>Unggah &amp; Periksa
                </button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Daftar Cadangan</span>
        <span class="badge badge-soft">{{ $daftar->count() }} berkas &middot; {{ $ukuran($daftar->sum('ukuran')) }}</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Cadangan</th>
                    <th>Dibuat</th>
                    <th>Isi</th>
                    <th class="text-end">Ukuran</th>
                    <th style="width:13rem"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftar as $c)
                    @php
                        $m = $c['manifest'];
                        [$label, $warna] = $labelJenis[$c['jenis'] ?? ''] ?? [ucfirst($c['jenis'] ?? '?'), 'badge-soft'];
                        $id = 'pulihkan-'.md5($c['nama']);
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold text-break">{{ $c['nama'] }}</div>
                            @if ($m)
                                <span class="badge {{ $warna }}">{{ $label }}</span>
                                @if (! empty($m['sekolah']))
                                    <span class="small text-muted">{{ $m['sekolah'] }}</span>
                                @endif
                            @else
                                <span class="badge text-bg-danger">Tidak sah</span>
                                <span class="small text-danger">{{ $c['galat'] }}</span>
                            @endif
                        </td>
                        <td class="small">
                            {{ $c['waktu']->translatedFormat('d M Y, H:i') }}
                            @if ($m)<div class="text-muted">{{ $m['oleh'] ?? '-' }}</div>@endif
                        </td>
                        <td class="small">
                            @if ($m)
                                {{ number_format($m['tabel']['soal'] ?? 0, 0, ',', '.') }} soal &middot;
                                {{ number_format($m['tabel']['ujian'] ?? 0, 0, ',', '.') }} ujian &middot;
                                {{ number_format($m['tabel']['ujian_peserta'] ?? 0, 0, ',', '.') }} peserta
                                <div class="text-muted">
                                    {{ number_format(array_sum($m['tabel'] ?? []), 0, ',', '.') }} baris data
                                    &middot; {{ ($m['berkas']['jumlah'] ?? 0) ? number_format($m['berkas']['jumlah'], 0, ',', '.').' berkas media' : 'tanpa media' }}
                                </div>
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-end small">{{ $ukuran($c['ukuran']) }}</td>
                        <td class="text-end">
                            <a href="{{ route('cadangan.unduh', $c['nama']) }}" class="btn btn-sm btn-outline-secondary" title="Unduh">
                                <i class="bi bi-download"></i>
                            </a>
                            @if ($m)
                                <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#{{ $id }}" @disabled(! $zipTersedia)>
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Pulihkan
                                </button>
                            @endif
                            <form method="POST" action="{{ route('cadangan.hapus', $c['nama']) }}" class="d-inline"
                                  data-konfirmasi="Hapus berkas cadangan {{ $c['nama'] }}? Berkas yang dihapus tidak bisa dikembalikan.">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <x-kosong kolom="5" icon="bi-database" pesan="Belum ada cadangan. Buat cadangan pertama sebelum ujian dimulai." />
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body small">
        <div class="fw-semibold mb-1"><i class="bi bi-clock-history me-1"></i>Cadangan otomatis setiap malam</div>
        Aplikasi membuat cadangan data (tanpa media) setiap pukul 01.30 dan menyimpan tujuh yang terbaru —
        asalkan penjadwal Laravel dijalankan oleh cron server. Di Virtualmin: <em>Scheduled Cron Jobs</em> →
        tambahkan perintah berikut untuk dijalankan setiap menit:
        <pre class="bg-light border rounded p-2 mt-2 mb-0"><code>cd {{ base_path() }} &amp;&amp; php artisan schedule:run &gt;&gt; /dev/null 2&gt;&amp;1</code></pre>
    </div>
</div>

{{-- Modal konfirmasi pemulihan, satu per cadangan. Diletakkan di luar tabel
     supaya formulirnya tidak bersarang di dalam baris. --}}
@foreach ($daftar->filter(fn ($c) => $c['manifest']) as $c)
    @php $m = $c['manifest']; $id = 'pulihkan-'.md5($c['nama']); @endphp
    <div class="modal fade" id="{{ $id }}" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('cadangan.pulihkan', $c['nama']) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-counterclockwise me-1"></i>Pulihkan cadangan?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        Seluruh data aplikasi saat ini akan <strong>diganti</strong> dengan isi
                        <code class="text-break">{{ $c['nama'] }}</code>
                        ({{ $c['waktu']->translatedFormat('d M Y, H:i') }}).
                    </p>
                    <ul class="small mb-3 ps-3">
                        <li>Data sekarang dicadangkan lebih dulu sebagai cadangan <em>pengaman</em>, jadi masih bisa dikembalikan.</li>
                        <li>Akun admin/operator ikut diganti — setelah selesai Anda diminta masuk kembali dengan akun yang ada di cadangan.</li>
                        @if (($m['berkas']['jumlah'] ?? 0) === 0)
                            <li>Cadangan ini tanpa media; gambar dan audio/video yang ada di server tetap dipakai.</li>
                        @endif
                    </ul>
                    @if ($sedangUjian > 0)
                        <div class="alert alert-danger py-2 small">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            <strong>{{ $sedangUjian }} siswa sedang mengerjakan ujian.</strong>
                            Jawaban mereka akan tertimpa isi cadangan. Sebaiknya tunggu sampai ujian selesai.
                        </div>
                    @endif
                    <label class="form-label" for="konfirmasi-{{ $id }}">Ketik <strong>PULIHKAN</strong> untuk melanjutkan</label>
                    <input type="text" name="konfirmasi" id="konfirmasi-{{ $id }}" class="form-control"
                           autocomplete="off" required pattern="PULIHKAN">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-warning"><i class="bi bi-arrow-counterclockwise me-1"></i>Pulihkan Sekarang</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
@endsection
