<?php

use App\Http\Middleware\PastikanAdmin;
use App\Http\Middleware\PastikanPengelola;
use App\Http\Middleware\PastikanSiswa;
use App\Http\Middleware\PastikanSiswaApi;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // Hanya melayani aplikasi ponsel peserta — lihat routes/api.php.
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            // Pengelola aplikasi: admin/operator (guard web) maupun guru.
            'pengelola' => PastikanPengelola::class,
            // Khusus admin/operator.
            'admin' => PastikanAdmin::class,
            // Peserta ujian.
            'siswa' => PastikanSiswa::class,
            // Peserta ujian yang masuk lewat aplikasi ponsel (token bearer).
            'siswa.api' => PastikanSiswaApi::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
