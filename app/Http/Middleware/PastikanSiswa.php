<?php

namespace App\Http\Middleware;

use App\Services\SesiSiswaService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Ruang ujian peserta: harus masuk sebagai siswa, dan hanya dari satu perangkat. */
class PastikanSiswa
{
    public function __construct(protected SesiSiswaService $sesi) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('siswa');

        if (! $guard->check()) {
            /*
             * Permintaan JSON dari sesi yang sudah mati juga dijawab 401.
             *
             * Sesi yang kalah hanya sekali mendapat jawaban "akun masuk dari
             * perangkat lain" — pada permintaan pertama sesudahnya. Bila yang
             * pertama itu kebetulan sendBeacon atau permintaan biasa, jawaban
             * itu tak pernah terbaca lembar ujian, dan setiap denyut berikutnya
             * hanya menerima pengalihan ke halaman login yang tidak ia kenali.
             * Soalnya lalu tetap tampil selamanya.
             */
            if ($request->expectsJson()) {
                // Pesan "masuk dari perangkat lain" dititipkan pada permintaan
                // yang mengeluarkan sesi. Permintaan JSON ini bukan yang
                // membawa siswa ke halaman login, jadi titipannya diperpanjang
                // — kalau tidak, alasannya hilang sebelum sempat terbaca.
                $request->session()->reflash();

                return response()->json([
                    'ok' => false,
                    'sesi_berakhir' => true,
                    'pesan' => 'Sesi Anda sudah berakhir. Silakan masuk kembali.',
                    'alihkan' => route('login'),
                ], 401);
            }

            return redirect()->route('login');
        }

        $siswaId = (int) $guard->id();

        if ($this->sesi->sah($request, $siswaId)) {
            return $next($request);
        }

        // Akun ini sudah masuk dari perangkat lain; sesi di sini yang kalah.
        $pemegang = $this->sesi->pemegang($siswaId);
        $pesan = 'Akun Anda baru saja masuk dari perangkat lain'
            .($pemegang?->ip_address ? ' ('.$pemegang->ip_address.')' : '')
            .', jadi sesi di perangkat ini diakhiri. Jika itu bukan Anda, segera lapor ke pengawas.';

        $guard->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Dititipkan di sesi baru, supaya halaman login menampilkan alasannya
        // — baik setelah pengalihan biasa maupun setelah lembar ujian
        // mengalihkan dirinya sendiri.
        $request->session()->flash('error', $pesan);

        // Lembar ujian menyimpan jawaban lewat fetch. Balasan berupa
        // pengalihan ke halaman login akan terbaca sebagai "gagal menyimpan,
        // periksa koneksi" — menyesatkan. Balasan JSON-nya menyebut
        // keadaan sebenarnya, dan lembar ujian mengalihkan sendiri.
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'sesi_berakhir' => true,
                'pesan' => $pesan,
                'alihkan' => route('login'),
            ], 401);
        }

        return redirect()->route('login');
    }
}
