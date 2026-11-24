@extends('layouts.app')
@section('title', 'Kinerja & Cache')
@section('subtitle', 'Tempat penyimpanan tembolok dan sesi login aplikasi')

@section('content')
@php
    $p = $keadaan['parameter'];
    $adaKlien = (bool) $keadaan['klien'];
    // Menyimpan tetap mungkin tanpa klien Redis — kembali ke database juga
    // disimpan lewat formulir ini. Yang mutlak perlu hanyalah .env yang bisa ditulis.
    $bisaSimpan = $keadaan['env_bisa_ditulis'];

    $lencana = fn ($nilai) => $nilai === 'redis'
        ? '<span class="badge text-bg-success">Redis</span>'
        : '<span class="badge text-bg-secondary">Database</span>';
@endphp

<div class="row g-3">
    <div class="col-lg-7">
        @unless ($adaKlien)
            <div class="alert alert-warning">
                <div class="fw-semibold mb-1"><i class="bi bi-plug me-1"></i>PHP di server ini belum punya klien Redis</div>
                <div class="small mb-2">
                    Redis boleh saja sudah berjalan, tetapi PHP belum bisa bicara dengannya. Pasang
                    ekstensinya lebih dulu lewat SSH, lalu muat ulang PHP-FPM:
                </div>
                <pre class="small bg-dark text-light rounded p-2 mb-2"><code>apt install redis-server php8.2-redis
systemctl enable --now redis-server
systemctl reload php8.2-fpm</code></pre>
                <div class="small mb-0">
                    Sesuaikan <code>8.2</code> dengan versi PHP yang dipakai domain ini. Sebelum itu beres,
                    pilihan Redis di bawah tidak bisa dipakai.
                </div>
            </div>
        @endunless

        @unless ($keadaan['env_bisa_ditulis'])
            <div class="alert alert-danger">
                <div class="fw-semibold mb-1"><i class="bi bi-lock me-1"></i>Berkas .env tidak bisa ditulis aplikasi</div>
                <div class="small mb-0">
                    Penyimpan tembolok dan sesi dibaca dari <code>{{ $keadaan['env_path'] }}</code>, bukan dari
                    database — jadi menu ini perlu izin menulisnya. Beri izin kepada pengguna web server,
                    lalu buka halaman ini lagi.
                </div>
            </div>
        @endunless

        @if ($keadaan['sedang_ujian'] > 0)
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Ada <strong>{{ $keadaan['sedang_ujian'] }} peserta</strong> yang sedang mengerjakan ujian.
                Penyimpan <strong>tembolok</strong> masih boleh diubah sekarang, tetapi penyimpan
                <strong>sesi</strong> tidak: pergantiannya melempar mereka ke halaman masuk di tengah ujian.
            </div>
        @endif

        <form method="POST" action="{{ route('kinerja.simpan') }}" id="formKinerja">
            @csrf

            <div class="card mb-3">
                <div class="card-header">Penyimpan</div>
                <div class="card-body">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tembolok (cache)</label>
                        <div class="row g-2">
                            @foreach (['database' => 'Database', 'redis' => 'Redis'] as $nilai => $label)
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="tembolok" id="tembolok-{{ $nilai }}"
                                           value="{{ $nilai }}" @checked(old('tembolok', $keadaan['tembolok']) === $nilai)
                                           @disabled($nilai === 'redis' && ! $adaKlien)>
                                    <label class="btn btn-outline-primary w-100 py-2" for="tembolok-{{ $nilai }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                        <div class="form-text">
                            Aman diubah kapan saja, termasuk saat ujian berlangsung: isinya hanya data yang
                            bisa dihitung ulang. Tidak ada sesi yang terputus.
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold">Sesi login (session)</label>
                        <div class="row g-2">
                            @foreach (['database' => 'Database', 'redis' => 'Redis'] as $nilai => $label)
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="sesi" id="sesi-{{ $nilai }}"
                                           value="{{ $nilai }}" @checked(old('sesi', $keadaan['sesi']) === $nilai)
                                           @disabled($nilai === 'redis' && ! $adaKlien)>
                                    <label class="btn btn-outline-primary w-100 py-2" for="sesi-{{ $nilai }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                        <div class="form-text">
                            Inilah yang paling besar pengaruhnya saat ujian — setiap denyut lembar peserta
                            menulis satu baris sesi. Tetapi menggantinya <strong>memutus seluruh sesi yang
                            sedang hidup</strong>, termasuk sesi Anda sendiri: semua harus masuk lagi.
                            Lakukan di luar jam ujian.
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Koneksi Redis</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-8">
                            <label class="form-label" for="host">Alamat</label>
                            <input type="text" class="form-control @error('host') is-invalid @enderror"
                                   id="host" name="host" value="{{ old('host', $p['host']) }}">
                            @error('host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="port">Porta</label>
                            <input type="number" class="form-control @error('port') is-invalid @enderror"
                                   id="port" name="port" value="{{ old('port', $p['port']) }}">
                            @error('port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="password">Kata sandi</label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror"
                                   id="password" name="password" autocomplete="new-password"
                                   placeholder="{{ $p['ada_sandi'] ? 'Tersimpan — isi hanya bila diganti' : 'Kosongkan bila Redis tanpa kata sandi' }}">
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">
                                Isian yang dibiarkan kosong berarti memakai yang sudah tersimpan. Kata sandi
                                yang tersimpan tidak pernah ditampilkan di halaman ini.
                            </div>
                        </div>

                        <div class="col-6">
                            <label class="form-label" for="database">Nomor database sesi</label>
                            <input type="number" class="form-control @error('database') is-invalid @enderror"
                                   id="database" name="database" value="{{ old('database', $p['database']) }}" min="0" max="15">
                            @error('database')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="cache_database">Nomor database tembolok</label>
                            <input type="number" class="form-control @error('cache_database') is-invalid @enderror"
                                   id="cache_database" name="cache_database" value="{{ old('cache_database', $p['cache_database']) }}" min="0" max="15">
                            @error('cache_database')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">
                                Wajib berbeda dari database sesi: mengosongkan tembolok menghapus seluruh isi
                                database itu, dan sesi peserta tidak boleh ikut terhapus.
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="prefix">Awalan kunci</label>
                            <input type="text" class="form-control @error('prefix') is-invalid @enderror"
                                   id="prefix" name="prefix" value="{{ old('prefix', $p['prefix']) }}">
                            @error('prefix')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">
                                Pembeda bila satu server Redis dipakai beberapa aplikasi sekaligus — lazim pada
                                server sekolah yang menampung beberapa domain. Saran untuk pemasangan ini:
                                <code>{{ $prefixSaran }}</code>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent d-flex gap-2">
                    <button class="btn btn-primary" @disabled(! $bisaSimpan)>
                        <i class="bi bi-save me-1"></i>Simpan
                    </button>
                    <button class="btn btn-outline-secondary" formaction="{{ route('kinerja.uji') }}" formnovalidate>
                        <i class="bi bi-plug me-1"></i>Uji koneksi
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">Keadaan sekarang</div>
            <table class="table table-sm align-middle mb-0">
                <tbody>
                    <tr>
                        <td class="small">Tembolok</td>
                        <td class="text-end">{!! $lencana($keadaan['tembolok']) !!}</td>
                    </tr>
                    <tr>
                        <td class="small">Sesi login</td>
                        <td class="text-end">{!! $lencana($keadaan['sesi']) !!}</td>
                    </tr>
                    <tr>
                        <td class="small">Klien Redis di PHP</td>
                        <td class="text-end">
                            @if ($adaKlien)
                                <span class="badge text-bg-success">{{ $keadaan['klien'] }}</span>
                            @else
                                <span class="badge text-bg-danger">belum terpasang</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="small">Berkas .env</td>
                        <td class="text-end">
                            <span class="badge {{ $keadaan['env_bisa_ditulis'] ? 'text-bg-success' : 'text-bg-danger' }}">
                                {{ $keadaan['env_bisa_ditulis'] ? 'bisa ditulis' : 'hanya baca' }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td class="small">Konfigurasi ditembolok</td>
                        <td class="text-end">
                            <span class="badge {{ $keadaan['config_tercache'] ? 'text-bg-success' : 'text-bg-warning' }}">
                                {{ $keadaan['config_tercache'] ? 'ya' : 'tidak' }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td class="small">Peserta sedang mengerjakan</td>
                        <td class="text-end">
                            <span class="badge {{ $keadaan['sedang_ujian'] > 0 ? 'text-bg-warning' : 'text-bg-secondary' }}">
                                {{ $keadaan['sedang_ujian'] }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
            @unless ($keadaan['config_tercache'])
                <div class="card-body small text-muted border-top">
                    Konfigurasi belum ditembolok, jadi setiap permintaan membaca ulang seluruh berkas config
                    dan .env. Jalankan <code>php artisan config:cache</code> di server untuk menghilangkan
                    beban itu; sesudahnya menu ini ikut membangunnya ulang setiap kali setelan disimpan.
                </div>
            @endunless
        </div>

        <div class="card">
            <div class="card-header">Yang terjadi saat disimpan</div>
            <div class="card-body small">
                <ol class="ps-3 mb-2">
                    <li>Koneksi Redis diuji lebih dulu — ditulis, lalu dibaca kembali. Bila gagal, tidak ada
                        yang berubah sama sekali.</li>
                    <li>Tembolok lama dikosongkan selagi masih terbaca, supaya barisnya tidak tertinggal di
                        database.</li>
                    <li>Baris penyimpan di <code>.env</code> ditulis ulang, lalu konfigurasi dimuat ulang.</li>
                    <li>Bila pemuatan ulang gagal, <code>.env</code> dikembalikan persis seperti semula.</li>
                </ol>
                <div class="text-muted mb-0">
                    Antrean (queue) sengaja tidak ikut disetel di sini: aplikasi ini tidak mengirim pekerjaan
                    ke antrean, sehingga memindahkannya tidak mengubah apa pun.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    /*
     * Pergantian penyimpan sesi memutus seluruh sesi yang sedang hidup. Itu
     * tidak bisa dibatalkan setelah tersimpan, dan orang yang pertama merasakan
     * akibatnya adalah admin yang menekan tombolnya sendiri — jadi dikonfirmasi
     * di sini, bukan hanya diterangkan di bawah pilihannya.
     */
    (function () {
        const sesiSekarang = @json($keadaan['sesi']);
        const formulir = document.getElementById('formKinerja');

        formulir.addEventListener('submit', function (e) {
            // Tombol "Uji koneksi" tidak mengubah apa pun; tidak perlu ditanya.
            if (e.submitter && e.submitter.hasAttribute('formaction')) return;

            const dipilih = formulir.querySelector('input[name="sesi"]:checked');
            if (!dipilih || dipilih.value === sesiSekarang) return;

            const pesan = 'Penyimpan sesi diubah dari ' + sesiSekarang + ' ke ' + dipilih.value + '.\n\n'
                + 'Seluruh pengguna yang sedang masuk — admin, guru, dan siswa — akan dikeluarkan '
                + 'dan harus masuk kembali, termasuk Anda sendiri.\n\nLanjutkan?';

            if (!confirm(pesan)) e.preventDefault();
        });
    })();
</script>
@endpush
