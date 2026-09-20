<?php

namespace App\Http\Controllers;

use App\Models\LoginAttempt;
use App\Services\SesiSiswaService;
use App\Support\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Login untuk tiga jenis pemakai:
 *
 *   admin  -> tabel users pada database "ujian", masuk dengan email
 *   guru   -> tabel guru pada database "datacenter", masuk dengan NIP
 *   siswa  -> tabel siswa pada database "datacenter", masuk dengan NISN
 *
 * Setiap percobaan — berhasil maupun gagal — dicatat ke tabel
 * login_attempts yang menjadi isi menu Log Login.
 */
class AuthController extends Controller
{
    /** Batas percobaan per IP sebelum diblokir sementara. */
    protected const MAKS_PERCOBAAN = 10;

    protected const JEDA_DETIK = 600;

    /** Kolom username per peran. */
    protected const KOLOM_LOGIN = [
        'admin' => 'email',
        'guru' => 'nip',
        'siswa' => 'nisn',
    ];

    public function showLogin(Request $request)
    {
        if (Pengguna::guard()) {
            return $this->kePerandaSesuaiPeran();
        }

        // Cegah halaman login ter-cache browser; token CSRF basi memicu 419.
        return response()
            ->view('auth.login')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'peran' => 'nullable|in:admin,guru,siswa',
            'username' => 'required|string|max:100',
            'password' => 'required|string|max:100',
        ], [], [
            'username' => 'Username',
            'password' => 'Kata sandi',
        ]);

        // Formulir login hanya punya satu kolom pengenal, jadi peran ditebak
        // dari bentuk isiannya. Permintaan yang tetap mengirim "peran"
        // dipakai apa adanya.
        $kandidat = ! empty($data['peran'])
            ? [$data['peran']]
            : $this->kandidatPeran($data['username']);

        $kunciRate = 'login:'.$request->ip();
        if (RateLimiter::tooManyAttempts($kunciRate, self::MAKS_PERCOBAAN)) {
            $detik = RateLimiter::availableIn($kunciRate);
            throw ValidationException::withMessages([
                'username' => "Terlalu banyak percobaan login. Coba lagi dalam {$detik} detik.",
            ]);
        }

        $peran = $kandidat[0];
        $berhasil = false;
        $adaPemilik = false;

        foreach ($kandidat as $calon) {
            $guardCalon = self::guardPeran($calon);
            $kolomCalon = self::KOLOM_LOGIN[$calon];

            $cocok = Auth::guard($guardCalon)->attempt(
                [$kolomCalon => $data['username'], 'password' => $data['password']],
                $request->boolean('remember')
            );

            if ($cocok) {
                $peran = $calon;
                $berhasil = true;
                break;
            }

            // Gagal: pakai peran yang pengenalnya memang terdaftar supaya baris
            // di Log Login menunjuk guard yang benar.
            if (! $adaPemilik && Auth::guard($guardCalon)->getProvider()
                ->retrieveByCredentials([$kolomCalon => $data['username']])) {
                $peran = $calon;
                $adaPemilik = true;
            }
        }

        $guard = self::guardPeran($peran);
        $kolom = self::KOLOM_LOGIN[$peran];

        $percobaan = LoginAttempt::catat($request, $data['username'], $guard, $berhasil);
        RateLimiter::hit($kunciRate, self::JEDA_DETIK);

        if (! $berhasil) {
            throw ValidationException::withMessages([
                'username' => 'Kombinasi '.($adaPemilik || count($kandidat) === 1
                    ? strtoupper($kolom)
                    : 'Email/NIP/NISN').' dan kata sandi tidak cocok.',
            ]);
        }

        $pengguna = Auth::guard($guard)->user();

        // Akun yang dinonaktifkan di datacenter tidak boleh masuk.
        if (property_exists($pengguna, 'is_aktif') || isset($pengguna->is_aktif)) {
            if (! $pengguna->is_aktif) {
                Auth::guard($guard)->logout();
                throw ValidationException::withMessages([
                    'username' => 'Akun Anda berstatus nonaktif. Hubungi admin sekolah.',
                ]);
            }
        }

        RateLimiter::clear($kunciRate);
        $request->session()->regenerate();

        // Satu akun siswa, satu perangkat: login ini mengambil alih akunnya,
        // dan sesi di perangkat lain berakhir pada permintaan berikutnya.
        if ($guard === 'siswa') {
            $diambilAlih = app(SesiSiswaService::class)
                ->mulai($request, (int) Auth::guard('siswa')->id());

            // Baris log login ini ditandai, beserta perangkat yang tergeser,
            // supaya menu Log Login bisa menampilkannya.
            if ($diambilAlih) {
                $percobaan->update([
                    'login_ganda' => true,
                    'login_ganda_ip' => $diambilAlih->ip_address,
                    'login_ganda_ua' => $diambilAlih->user_agent,
                ]);
            }
        }

        return $this->kePerandaSesuaiPeran();
    }

    public function logout(Request $request)
    {
        // Diakhiri sebelum sesinya dihapus: penandanya dibaca dari sesi itu.
        if (Auth::guard('siswa')->check()) {
            app(SesiSiswaService::class)->akhiri($request, (int) Auth::guard('siswa')->id());
        }

        $guard = Pengguna::guard();

        if ($guard) {
            Auth::guard($guard)->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda telah keluar.');
    }

    /** Guard Laravel untuk sebuah peran. */
    protected static function guardPeran(string $peran): string
    {
        return $peran === 'admin' ? 'web' : $peran;
    }

    /**
     * Urutan peran yang dicoba untuk sebuah pengenal: email hanya dipakai
     * admin, sedangkan pengenal angka dicoba sebagai NIP guru lebih dulu,
     * baru NISN siswa.
     */
    protected function kandidatPeran(string $username): array
    {
        return str_contains($username, '@') ? ['admin'] : ['guru', 'siswa'];
    }

    protected function kePerandaSesuaiPeran()
    {
        return Pengguna::isSiswa()
            ? redirect()->route('siswa.ujian.index')
            : redirect()->intended(route('dashboard'));
    }
}
