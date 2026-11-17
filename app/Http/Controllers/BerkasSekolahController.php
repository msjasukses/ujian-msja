<?php

namespace App\Http\Controllers;

use App\Models\Sekolah;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Melayani berkas identitas sekolah milik Data Center: logo pada kop, dan
 * tanda tangan kepala sekolah pada kartu peserta.
 *
 * Berkasnya ada di direktori unggahan aplikasi Data Center, jadi aplikasi ini
 * membacanya dari sana dan melayaninya di host-nya sendiri: satu berkas untuk
 * ratusan kartu, tanpa bergantung pada peladen Data Center saat kartu dicetak.
 */
class BerkasSekolahController extends Controller
{
    /**
     * Logo sekolah — tanpa autentikasi: lambang sekolah bukan rahasia, dan
     * halaman masuk pun memakainya sebelum siapa pun masuk.
     */
    public function logo(): BinaryFileResponse
    {
        return $this->layani(fn (Sekolah $sekolah) => $sekolah->pathLogo());
    }

    /**
     * Tanda tangan kepala sekolah — hanya untuk pengelola (lihat routes/web.php).
     *
     * Tanda tangan dipakai mengesahkan dokumen. Melayaninya seterbuka logo
     * berarti menyediakan contoh tanda tangan kepala sekolah dalam resolusi
     * penuh kepada siapa saja yang menebak alamatnya.
     */
    public function ttd(): BinaryFileResponse
    {
        return $this->layani(fn (Sekolah $sekolah) => $sekolah->pathTtd());
    }

    protected function layani(callable $ambil): BinaryFileResponse
    {
        // Data Center tak terjangkau diperlakukan sama seperti tak berberkas:
        // halaman yang memuat gambar ini tidak boleh ikut gagal.
        $berkas = rescue(function () use ($ambil) {
            $sekolah = Sekolah::profil();

            return $sekolah ? $ambil($sekolah) : null;
        }, null, false);

        abort_if($berkas === null, 404);

        return response()->file($berkas)->setPublic()->setMaxAge(3600);
    }
}
