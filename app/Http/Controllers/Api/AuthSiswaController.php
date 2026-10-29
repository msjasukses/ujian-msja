<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoginAttempt;
use App\Models\Sekolah;
use App\Models\Siswa;
use App\Models\SiswaApiToken;
use App\Models\UjianLog;
use App\Models\UjianPeserta;
use App\Support\ApiSiswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Masuk dan keluar untuk aplikasi ponsel peserta.
 *
 * Kredensialnya sama persis dengan halaman masuk di peramban: NISN dan kata
 * sandi yang tersimpan di database Data Center. Yang berbeda hanya penanda
 * sesinya — peramban memakai cookie, aplikasi memegang token.
 */
class AuthSiswaController extends Controller
{
    /** Batas percobaan per IP sebelum diblokir sementara; sama dengan web. */
    protected const MAKS_PERCOBAAN = 10;

    protected const JEDA_DETIK = 600;

    public function login(Request $request)
    {
        $data = $request->validate([
            'nisn' => 'required|string|max:100',
            'password' => 'required|string|max:100',
            'perangkat' => 'nullable|string|max:120',
        ], [], [
            'nisn' => 'NISN',
            'password' => 'Kata sandi',
        ]);

        $kunciRate = 'login-api:'.$request->ip();

        if (RateLimiter::tooManyAttempts($kunciRate, self::MAKS_PERCOBAAN)) {
            return response()->json([
                'pesan' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '
                    .RateLimiter::availableIn($kunciRate).' detik.',
            ], 429);
        }

        $provider = Auth::guard('siswa')->getProvider();
        $siswa = $provider->retrieveByCredentials(['nisn' => $data['nisn']]);
        $berhasil = $siswa !== null
            && $provider->validateCredentials($siswa, ['password' => $data['password']]);

        $percobaan = LoginAttempt::catat($request, $data['nisn'], 'siswa', $berhasil);
        RateLimiter::hit($kunciRate, self::JEDA_DETIK);

        if (! $berhasil) {
            return response()->json(['pesan' => 'Kombinasi NISN dan kata sandi tidak cocok.'], 422);
        }

        if (! $siswa->is_aktif) {
            return response()->json(['pesan' => 'Akun Anda berstatus nonaktif. Hubungi admin sekolah.'], 403);
        }

        RateLimiter::clear($kunciRate);

        [$mentah, $diambilAlih] = $this->terbitkanToken($request, $siswa, $data['perangkat'] ?? null);

        // Baris log login ikut ditandai, beserta perangkat yang tergeser,
        // supaya menu Log Login menampilkannya seperti login lewat peramban.
        if ($diambilAlih) {
            $percobaan->update([
                'login_ganda' => true,
                'login_ganda_ip' => $diambilAlih->ip_address,
                'login_ganda_ua' => $diambilAlih->user_agent,
            ]);
        }

        return response()->json([
            'token' => $mentah,
            'kedaluwarsa_at' => now()->addDays(SiswaApiToken::UMUR_HARI)->toIso8601String(),
            'siswa' => ApiSiswa::siswa($siswa),
            'diambil_alih' => (bool) $diambilAlih,
        ]);
    }

    /** Profil peserta yang sedang masuk, untuk halaman Profil di aplikasi. */
    public function saya()
    {
        $sekolah = Sekolah::profil();

        return response()->json([
            'siswa' => ApiSiswa::siswa(Auth::guard('siswa')->user()),
            'sekolah' => $sekolah ? [
                'nama' => $sekolah->nama_sekolah,
                'npsn' => $sekolah->npsn,
                'jenjang' => $sekolah->jenjang,
                'alamat' => $sekolah->alamat,
                'kepala_sekolah' => $sekolah->kepala_sekolah,
            ] : null,
        ]);
    }

    public function logout(Request $request)
    {
        // Hanya token yang sedang dipegang perangkat ini — keluar dari ponsel
        // lama tidak boleh ikut mengakhiri sesi di ponsel yang baru.
        $request->attributes->get('siswa_api_token')?->delete();

        return response()->json(['pesan' => 'Anda telah keluar.']);
    }

    /**
     * Terbitkan token baru, sekaligus menyingkirkan token perangkat lain.
     *
     * Aturannya dipinjam dari SesiSiswaService: satu akun satu perangkat, dan
     * login terbaru yang menang. Menolak login baru selama token lama hidup
     * justru menjebak siswa jujur yang ponselnya mati di tengah ujian.
     *
     * @return array{0: string, 1: SiswaApiToken|null} token mentah, dan token
     *                                                 perangkat lain yang baru saja diambil alih
     */
    protected function terbitkanToken(Request $request, Siswa $siswa, ?string $perangkat): array
    {
        $lama = SiswaApiToken::where('siswa_id', $siswa->id)->latest('terakhir_dipakai_at')->first();
        $ganda = $lama && $this->termasukGanda($lama, $request) ? clone $lama : null;

        SiswaApiToken::where('siswa_id', $siswa->id)->delete();

        $mentah = SiswaApiToken::acak();

        SiswaApiToken::create([
            'siswa_id' => $siswa->id,
            'token' => SiswaApiToken::sidik($mentah),
            'perangkat' => $perangkat,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
            'terakhir_dipakai_at' => now(),
            'kedaluwarsa_at' => now()->addDays(SiswaApiToken::UMUR_HARI),
        ]);

        if ($ganda) {
            $this->catatUntukPengawas($siswa->id, $ganda, $request);
        }

        return [$mentah, $ganda];
    }

    /**
     * Benarkah ini dua perangkat memakai satu akun?
     *
     * Token lama tidak terhapus saat aplikasi sekadar ditutup, jadi
     * keberadaannya saja bukan bukti. Dua syarat: tokennya masih dipakai
     * belakangan ini, dan perangkatnya memang lain — aplikasi yang dipasang
     * ulang di ponsel yang sama masuk lagi dengan IP dan user agent serupa.
     */
    protected function termasukGanda(SiswaApiToken $lama, Request $request): bool
    {
        if (! $lama->masihAktif()) {
            return false;
        }

        return ! ($lama->ip_address === $request->ip()
            && $lama->user_agent === Str::limit((string) $request->userAgent(), 490, ''));
    }

    protected function catatUntukPengawas(int $siswaId, SiswaApiToken $lama, Request $request): void
    {
        $sedang = UjianPeserta::where('siswa_id', $siswaId)
            ->where('status', UjianPeserta::MULAI)
            ->get();

        foreach ($sedang as $peserta) {
            UjianLog::catat($peserta->ujian_id, $peserta->id, 'sesi_ganda',
                'Masuk dari aplikasi '.($request->ip() ?: '?').'; sesi di '.($lama->ip_address ?: '?').' diakhiri.');
        }
    }
}
