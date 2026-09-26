<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportSoalController;
use App\Http\Controllers\Laporan\AnalisisButirController;
use App\Http\Controllers\Laporan\NilaiController;
use App\Http\Controllers\Laporan\PengayaanController;
use App\Http\Controllers\Laporan\RemidialController;
use App\Http\Controllers\Laporan\StatistikController;
use App\Http\Controllers\LogLoginController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\PaketSoalController;
use App\Http\Controllers\PenggunaController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\ReferensiController as Ref;
use App\Http\Controllers\Siswa\UjianSiswaController;
use App\Http\Controllers\SoalController;
use App\Http\Controllers\TopikController;
use App\Http\Controllers\UjianController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

// ---------------------------------------------------------------------------
// Autentikasi
// ---------------------------------------------------------------------------
Route::get('login', [AuthController::class, 'showLogin'])->name('login');
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:20,1');
Route::post('logout', [AuthController::class, 'logout'])->name('logout');

// ---------------------------------------------------------------------------
// Area pengelola: admin/operator dan guru
// ---------------------------------------------------------------------------
Route::middleware('pengelola')->group(function () {

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // --- Profil akun yang sedang masuk ---
    Route::get('profil', [ProfilController::class, 'index'])->name('profil.index');
    Route::patch('profil', [ProfilController::class, 'update'])->name('profil.update');
    Route::put('profil/sandi', [ProfilController::class, 'ubahSandi'])->name('profil.sandi');

    // --- 1. Topik (+ Sinkron CP-TP-ATP dari database kurikulum) ---
    Route::get('topik/sinkron', [TopikController::class, 'sinkron'])->name('topik.sinkron');
    Route::post('topik/sinkron', [TopikController::class, 'sinkronJalankan'])->name('topik.sinkron.jalankan');
    Route::get('topik/export', [TopikController::class, 'export'])->name('topik.export');
    Route::delete('topik/hapus-massal', [TopikController::class, 'hapusMassal'])->name('topik.hapus-massal');
    Route::resource('topik', TopikController::class)->except('show');

    // --- 2. Bank soal (+ import Word & Excel) ---
    Route::get('soal/import', [ImportSoalController::class, 'form'])->name('soal.import.form');
    Route::post('soal/import/pratinjau', [ImportSoalController::class, 'pratinjau'])->name('soal.import.pratinjau');
    Route::post('soal/import/simpan', [ImportSoalController::class, 'simpan'])->name('soal.import.simpan');
    Route::post('soal/import/batal', [ImportSoalController::class, 'batal'])->name('soal.import.batal');
    Route::get('soal/import/template-excel', [ImportSoalController::class, 'templateExcel'])->name('soal.import.template-excel');
    Route::get('soal/import/template-word', [ImportSoalController::class, 'templateWord'])->name('soal.import.template-word');
    Route::post('soal/gambar', [SoalController::class, 'unggahGambar'])->name('soal.gambar');
    Route::get('soal/export', [SoalController::class, 'export'])->name('soal.export');
    Route::patch('soal/{soal}/toggle', [SoalController::class, 'toggle'])->name('soal.toggle');
    Route::delete('soal/hapus-massal', [SoalController::class, 'hapusMassal'])->name('soal.hapus-massal');
    Route::resource('soal', SoalController::class);

    // --- 3. Pemilihan soal yang diujikan (paket soal) ---
    Route::get('paket-soal/{paket_soal}/kelola', [PaketSoalController::class, 'kelola'])->name('paket-soal.kelola');
    Route::post('paket-soal/{paket_soal}/soal', [PaketSoalController::class, 'tambahSoal'])->name('paket-soal.tambah-soal');
    Route::delete('paket-soal/{paket_soal}/soal', [PaketSoalController::class, 'lepasSoalMassal'])->name('paket-soal.lepas-massal');
    Route::delete('paket-soal/{paket_soal}/soal/{detail}', [PaketSoalController::class, 'hapusSoal'])->name('paket-soal.hapus-soal');
    Route::put('paket-soal/{paket_soal}/urutan', [PaketSoalController::class, 'simpanUrutan'])->name('paket-soal.urutan');
    Route::post('paket-soal/{paket_soal}/acak', [PaketSoalController::class, 'ambilAcak'])->name('paket-soal.acak');
    Route::get('paket-soal/{paket_soal}/export', [PaketSoalController::class, 'export'])->name('paket-soal.export');
    Route::delete('paket-soal/hapus-massal', [PaketSoalController::class, 'hapusMassal'])->name('paket-soal.hapus-massal');
    Route::resource('paket-soal', PaketSoalController::class)->except('show');

    // --- 4. Registrasi ujian ---
    Route::get('ujian/{ujian}/peserta', [UjianController::class, 'peserta'])->name('ujian.peserta');
    Route::post('ujian/{ujian}/peserta/sinkron', [UjianController::class, 'sinkronPeserta'])->name('ujian.peserta.sinkron');
    Route::patch('ujian/{ujian}/peserta/{peserta}/toggle', [UjianController::class, 'togglePeserta'])->name('ujian.peserta.toggle');
    Route::get('ujian/{ujian}/peserta/export', [UjianController::class, 'exportPeserta'])->name('ujian.peserta.export');
    Route::patch('ujian/{ujian}/status', [UjianController::class, 'ubahStatus'])->name('ujian.status');
    Route::patch('ujian/{ujian}/token', [UjianController::class, 'tokenBaru'])->name('ujian.token');
    Route::delete('ujian/hapus-massal', [UjianController::class, 'hapusMassal'])->name('ujian.hapus-massal');
    Route::resource('ujian', UjianController::class)->except('show');

    // --- 5. Hasil & laporan ujian ---
    Route::prefix('laporan')->name('laporan.')->group(function () {

        // 5a. Daftar nilai ujian
        Route::get('nilai', [NilaiController::class, 'index'])->name('nilai.index');
        Route::get('nilai/{ujian}', [NilaiController::class, 'show'])->name('nilai.show');
        Route::get('nilai/{ujian}/export', [NilaiController::class, 'export'])->name('nilai.export');
        Route::post('nilai/{ujian}/koreksi-ulang', [NilaiController::class, 'koreksiUlang'])->name('nilai.koreksi-ulang');
        Route::get('nilai/{ujian}/peserta/{peserta}', [NilaiController::class, 'detail'])->name('nilai.detail');
        Route::post('nilai/{ujian}/peserta/{peserta}/essay', [NilaiController::class, 'nilaiEssay'])->name('nilai.essay');

        // 5b. Statistik ujian
        Route::get('statistik', [StatistikController::class, 'index'])->name('statistik.index');
        Route::get('statistik/{ujian}', [StatistikController::class, 'show'])->name('statistik.show');
        Route::get('statistik/{ujian}/export', [StatistikController::class, 'export'])->name('statistik.export');

        // 5c. Analisis butir soal
        Route::get('analisis-butir', [AnalisisButirController::class, 'index'])->name('analisis.index');
        Route::get('analisis-butir/{ujian}', [AnalisisButirController::class, 'show'])->name('analisis.show');
        Route::get('analisis-butir/{ujian}/export', [AnalisisButirController::class, 'export'])->name('analisis.export');

        // 5d. Remidial
        Route::get('remidial', [RemidialController::class, 'index'])->name('remidial.index');
        Route::get('remidial/{ujian}', [RemidialController::class, 'show'])->name('remidial.show');
        Route::get('remidial/{ujian}/export', [RemidialController::class, 'export'])->name('remidial.export');
        Route::post('remidial/{ujian}', [RemidialController::class, 'simpan'])->name('remidial.simpan');
        Route::post('remidial/{ujian}/nilai-akhir', [RemidialController::class, 'nilaiAkhir'])->name('remidial.nilai-akhir');
        Route::post('remidial/{ujian}/buat-ujian', [RemidialController::class, 'buatUjian'])->name('remidial.buat-ujian');
        Route::delete('remidial/{ujian}/{tindak_lanjut}', [RemidialController::class, 'hapus'])->name('remidial.hapus');

        // 5e. Pengayaan
        Route::get('pengayaan', [PengayaanController::class, 'index'])->name('pengayaan.index');
        Route::get('pengayaan/{ujian}', [PengayaanController::class, 'show'])->name('pengayaan.show');
        Route::get('pengayaan/{ujian}/export', [PengayaanController::class, 'export'])->name('pengayaan.export');
        Route::post('pengayaan/{ujian}', [PengayaanController::class, 'simpan'])->name('pengayaan.simpan');
        Route::post('pengayaan/{ujian}/nilai-akhir', [PengayaanController::class, 'nilaiAkhir'])->name('pengayaan.nilai-akhir');
        Route::delete('pengayaan/{ujian}/{tindak_lanjut}', [PengayaanController::class, 'hapus'])->name('pengayaan.hapus');
    });

    // --- 6. Monitoring ujian ---
    Route::get('monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');
    Route::get('monitoring/{ujian}', [MonitoringController::class, 'show'])->name('monitoring.show');
    Route::get('monitoring/{ujian}/data', [MonitoringController::class, 'data'])->name('monitoring.data');
    Route::get('monitoring/{ujian}/jejak', [MonitoringController::class, 'jejak'])->name('monitoring.jejak');
    Route::get('monitoring/{ujian}/export', [MonitoringController::class, 'export'])->name('monitoring.export');
    Route::post('monitoring/{ujian}/peserta/{peserta}/reset', [MonitoringController::class, 'reset'])->name('monitoring.reset');
    Route::post('monitoring/{ujian}/peserta/{peserta}/selesai', [MonitoringController::class, 'selesaikan'])->name('monitoring.selesai');
    Route::post('monitoring/{ujian}/peserta/{peserta}/buka-kunci', [MonitoringController::class, 'bukaKunci'])->name('monitoring.buka-kunci');
    Route::post('monitoring/{ujian}/buka-kunci-semua', [MonitoringController::class, 'bukaKunciSemua'])->name('monitoring.buka-kunci-semua');
    Route::post('monitoring/{ujian}/selesai-semua', [MonitoringController::class, 'selesaikanSemua'])->name('monitoring.selesai-semua');

    // --- Data referensi dari Data Center (read-only, khusus admin/operator) ---
    Route::middleware('admin')->prefix('referensi')->name('referensi.')->group(function () {
        Route::get('siswa', [Ref::class, 'siswa'])->name('siswa');
        Route::get('siswa/export', [Ref::class, 'exportSiswa'])->name('siswa.export');
        Route::get('guru', [Ref::class, 'guru'])->name('guru');
        Route::get('wali-kelas', [Ref::class, 'waliKelas'])->name('wali-kelas');
        Route::get('guru-mapel', [Ref::class, 'guruMapel'])->name('guru-mapel');
        Route::get('mata-pelajaran', [Ref::class, 'mapel'])->name('mapel');
        Route::get('tingkat-kelas', [Ref::class, 'tingkatKelas'])->name('tingkat-kelas');
        Route::get('tahun-ajaran', [Ref::class, 'tahunAjaran'])->name('tahun-ajaran');
    });

    // --- 7. Log login & pengguna (khusus admin) ---
    Route::middleware('admin')->group(function () {
        Route::get('log-login', [LogLoginController::class, 'index'])->name('log-login.index');
        Route::get('log-login/export', [LogLoginController::class, 'export'])->name('log-login.export');
        Route::delete('log-login', [LogLoginController::class, 'bersihkan'])->name('log-login.bersihkan');

        Route::delete('pengguna/hapus-massal', [PenggunaController::class, 'hapusMassal'])->name('pengguna.hapus-massal');
        Route::resource('pengguna', PenggunaController::class)->except('show');
    });
});

// ---------------------------------------------------------------------------
// Ruang ujian peserta
// ---------------------------------------------------------------------------
Route::middleware('siswa')->prefix('siswa')->name('siswa.')->group(function () {
    Route::get('profil', [ProfilController::class, 'siswa'])->name('profil');
    Route::get('ujian', [UjianSiswaController::class, 'index'])->name('ujian.index');
    Route::get('ujian/{peserta}', [UjianSiswaController::class, 'konfirmasi'])->name('ujian.konfirmasi');
    Route::post('ujian/{peserta}/mulai', [UjianSiswaController::class, 'mulai'])->name('ujian.mulai');
    Route::get('ujian/{peserta}/kerjakan', [UjianSiswaController::class, 'kerjakan'])->name('ujian.kerjakan');
    Route::post('ujian/{peserta}/simpan', [UjianSiswaController::class, 'simpan'])->name('ujian.simpan');
    Route::post('ujian/{peserta}/keluar', [UjianSiswaController::class, 'catatKeluar'])->name('ujian.keluar');
    Route::post('ujian/{peserta}/pelanggaran', [UjianSiswaController::class, 'pelanggaran'])->name('ujian.pelanggaran');
    Route::get('ujian/{peserta}/pengawasan', [UjianSiswaController::class, 'statusPengawasan'])->name('ujian.pengawasan');
    Route::post('ujian/{peserta}/selesai', [UjianSiswaController::class, 'selesai'])->name('ujian.selesai');
    Route::get('ujian/{peserta}/hasil', [UjianSiswaController::class, 'hasil'])->name('ujian.hasil');
});
