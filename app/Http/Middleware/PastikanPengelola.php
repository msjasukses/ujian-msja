<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman pengelolaan (topik, bank soal, paket, registrasi, laporan,
 * monitoring) boleh diakses admin/operator maupun guru.
 */
class PastikanPengelola
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('web')->check() || Auth::guard('guru')->check()) {
            return $next($request);
        }

        // Siswa yang tersasar ke area pengelola dikembalikan ke ruang ujiannya.
        if (Auth::guard('siswa')->check()) {
            return redirect()->route('siswa.ujian.index');
        }

        return redirect()->route('login');
    }
}
