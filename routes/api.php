<?php

/*
|--------------------------------------------------------------------------
| API Routes — aplikasi ponsel peserta (CBT Siswa)
|--------------------------------------------------------------------------
| Awalan otomatis: /api (lihat bootstrap/app.php)
|
| Seluruh endpoint di sini melayani satu klien saja: aplikasi Android peserta
| ujian. Pengelola — admin, operator, guru — tetap memakai antarmuka web;
| tidak ada endpoint pengelolaan di sini, dan memang tidak boleh ada.
|
| Autentikasi memakai token bearer yang diterbitkan POST /api/v1/auth/login,
| dijaga middleware "siswa.api". Data induk siswa sendiri tetap milik Data
| Center — aplikasi ini tidak pernah menulis ke sana.
*/

use App\Http\Controllers\Api\AuthSiswaController;
use App\Http\Controllers\Api\UjianSiswaApiController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => [
    'ok' => true,
    'aplikasi' => config('app.name'),
    'waktu' => now()->toIso8601String(),
]);

Route::prefix('v1')->group(function () {

    // ---- Masuk (tanpa token) ----
    Route::post('auth/login', [AuthSiswaController::class, 'login'])->middleware('throttle:20,1');

    // ---- Butuh token peserta ----
    Route::middleware('siswa.api')->group(function () {

        Route::get('auth/saya', [AuthSiswaController::class, 'saya']);
        Route::post('auth/logout', [AuthSiswaController::class, 'logout']);

        Route::get('ujian', [UjianSiswaApiController::class, 'index']);
        Route::get('ujian/{peserta}', [UjianSiswaApiController::class, 'show']);
        Route::post('ujian/{peserta}/mulai', [UjianSiswaApiController::class, 'mulai']);
        Route::get('ujian/{peserta}/lembar', [UjianSiswaApiController::class, 'lembar']);
        Route::post('ujian/{peserta}/jawaban', [UjianSiswaApiController::class, 'jawaban']);
        Route::get('ujian/{peserta}/denyut', [UjianSiswaApiController::class, 'denyut']);
        Route::post('ujian/{peserta}/keluar', [UjianSiswaApiController::class, 'keluar']);
        Route::post('ujian/{peserta}/pelanggaran', [UjianSiswaApiController::class, 'pelanggaran']);
        Route::get('ujian/{peserta}/pengawasan', [UjianSiswaApiController::class, 'pengawasan']);
        Route::post('ujian/{peserta}/selesai', [UjianSiswaApiController::class, 'selesai']);
        Route::get('ujian/{peserta}/hasil', [UjianSiswaApiController::class, 'hasil']);
    });
});
