<?php

use App\Http\Middleware\PastikanAdmin;
use App\Http\Middleware\PastikanPengelola;
use App\Http\Middleware\PastikanSiswa;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
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
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
