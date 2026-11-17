<?php

namespace App\Http\Controllers;

use App\Models\Sekolah;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Melayani logo sekolah milik Data Center.
 *
 * Logonya dipakai pada kop kartu peserta. Berkasnya ada di direktori unggahan
 * aplikasi Data Center (lihat disk "datacenter"), jadi aplikasi ini membacanya
 * dari sana dan melayaninya di host-nya sendiri: satu berkas untuk ratusan
 * kartu, tanpa bergantung pada peladen Data Center saat kartu dicetak.
 *
 * Tanpa autentikasi — logo sekolah bukan rahasia, dan halaman cetak kartu
 * memuatnya ratusan kali.
 */
class LogoSekolahController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        // Data Center tak terjangkau diperlakukan sama seperti tak berlogo:
        // halaman yang memuat gambar ini tidak boleh ikut gagal.
        $berkas = rescue(fn () => Sekolah::profil()?->pathLogo(), null, false);

        abort_if($berkas === null, 404);

        return response()->file($berkas)->setPublic()->setMaxAge(3600);
    }
}
