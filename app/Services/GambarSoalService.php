<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Penyimpan gambar yang menyertai butir soal.
 *
 * Gambar disimpan sebagai berkas di disk publik, bukan ditanam sebagai data URI
 * di dalam kolom pertanyaan. Satu foto grafik dari ponsel gampang berukuran 2 MB;
 * sebagai data URI ia membengkak sepertiga lagi, ikut terbawa pada setiap query
 * bank soal, dan ikut tersalin ke tiap lembar jawaban saat naskah ditampilkan.
 * Sebagai berkas, ia diunduh sekali lalu ditembolok peramban.
 *
 * Nama berkas diambil dari sidik jari isinya, jadi gambar yang sama — misalnya
 * satu peta yang dipakai lima butir sekaligus, atau naskah Word yang diimpor
 * dua kali — hanya menempati ruang sekali.
 */
class GambarSoalService
{
    /** Jenis gambar yang diterima beserta akhiran berkasnya. */
    public const JENIS = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];

    /** Batas ukuran berkas yang diterima (5 MB). */
    public const MAKS_BYTE = 5 * 1024 * 1024;

    /**
     * Lebar maksimal yang disimpan. Gambar yang lebih lebar diperkecil —
     * lebar badan soal di layar hanya sekitar 700 px, sehingga foto 4000 px
     * dari ponsel hanya memperlambat pemuatan tanpa menambah kejelasan.
     */
    public const MAKS_LEBAR = 1280;

    protected const FOLDER = 'soal';

    /** Gambar dari unggahan formulir atau tempelan papan klip. */
    public function dariUnggahan(UploadedFile $berkas): string
    {
        if ($berkas->getSize() > self::MAKS_BYTE) {
            throw new RuntimeException('Ukuran gambar melebihi '.$this->batasTerbaca().'.');
        }

        $biner = @file_get_contents($berkas->getRealPath());

        if ($biner === false) {
            throw new RuntimeException('Berkas gambar gagal dibaca.');
        }

        return $this->simpan($biner)
            ?? throw new RuntimeException('Berkas yang diunggah bukan gambar yang dikenali (JPG, PNG, GIF, atau WebP).');
    }

    /**
     * Gambar dari isi biner — dipakai saat menarik gambar yang tertanam di
     * dalam berkas Word atau Excel.
     *
     * Mengembalikan null bila isinya bukan gambar yang dikenali; pemanggilnya
     * yang memutuskan apakah itu perlu dilaporkan sebagai galat. Saat impor,
     * satu gambar rusak sebaiknya tidak menggagalkan seluruh naskah.
     */
    public function dariBiner(?string $biner): ?string
    {
        if ($biner === null || $biner === '' || strlen($biner) > self::MAKS_BYTE) {
            return null;
        }

        return $this->simpan($biner);
    }

    /** Gambar dari data URI, mis. hasil tempelan "data:image/png;base64,...". */
    public function dariDataUri(string $uri): ?string
    {
        if (! preg_match('#^data:image/[\w.+-]+;base64,#i', $uri)) {
            return null;
        }

        $biner = base64_decode(substr($uri, strpos($uri, ',') + 1), true);

        return $biner === false ? null : $this->dariBiner($biner);
    }

    /**
     * Alamat publik seluruh gambar yang dirujuk sepotong teks soal. Dipakai
     * perintah pembersih untuk mengetahui berkas mana yang masih terpakai.
     *
     * @return array<int, string>
     */
    public function rujukanDalam(?string $html): array
    {
        preg_match_all('#src\s*=\s*["\'](/storage/'.self::FOLDER.'/[^"\']+)["\']#i', (string) $html, $m);

        return array_values(array_unique($m[1] ?? []));
    }

    // =====================================================================

    /** Simpan isi biner sebagai berkas gambar, kembalikan alamat publiknya. */
    protected function simpan(string $biner): ?string
    {
        $info = @getimagesizefromstring($biner);

        // Pemeriksaan isi, bukan nama berkas maupun header unggahan: keduanya
        // ditentukan pengirim dan gampang dipalsukan.
        if ($info === false || ! isset(self::JENIS[$info[2]])) {
            return null;
        }

        [$lebar, , $jenis] = $info;

        if ($lebar > self::MAKS_LEBAR) {
            $biner = $this->perkecil($biner, $jenis) ?? $biner;
            $info = @getimagesizefromstring($biner);
            $jenis = $info === false ? $jenis : $info[2];
        }

        $akhiran = self::JENIS[$jenis];
        $sidik = sha1($biner);

        // Dibagi ke subfolder dua huruf supaya satu folder tidak menampung
        // puluhan ribu berkas sekaligus.
        $jalur = self::FOLDER.'/'.substr($sidik, 0, 2).'/'.$sidik.'.'.$akhiran;
        $disk = Storage::disk('public');

        if (! $disk->exists($jalur)) {
            $disk->put($jalur, $biner);
        }

        return '/storage/'.$jalur;
    }

    /** Perkecil gambar sampai selebar MAKS_LEBAR dengan menjaga rasionya. */
    protected function perkecil(string $biner, int $jenis): ?string
    {
        if (! extension_loaded('gd')) {
            return null;   // tanpa GD gambar disimpan apa adanya
        }

        $asal = @imagecreatefromstring($biner);

        if ($asal === false) {
            return null;
        }

        $lebarAsal = imagesx($asal);
        $tinggiAsal = imagesy($asal);
        $lebar = self::MAKS_LEBAR;
        $tinggi = max(1, (int) round($tinggiAsal * ($lebar / $lebarAsal)));

        $baru = imagecreatetruecolor($lebar, $tinggi);

        // PNG, GIF dan WebP bisa punya bagian tembus pandang; tanpa dua baris
        // ini bagian itu berubah menjadi hitam pekat setelah diperkecil.
        imagealphablending($baru, false);
        imagesavealpha($baru, true);
        imagecopyresampled($baru, $asal, 0, 0, 0, 0, $lebar, $tinggi, $lebarAsal, $tinggiAsal);

        ob_start();

        match ($jenis) {
            IMAGETYPE_JPEG => imagejpeg($baru, null, 85),
            IMAGETYPE_PNG => imagepng($baru, null, 6),
            IMAGETYPE_GIF => imagegif($baru),
            IMAGETYPE_WEBP => imagewebp($baru, null, 85),
            default => imagepng($baru),
        };

        $hasil = ob_get_clean();

        imagedestroy($asal);
        imagedestroy($baru);

        return $hasil === '' ? null : $hasil;
    }

    protected function batasTerbaca(): string
    {
        return round(self::MAKS_BYTE / 1024 / 1024, 1).' MB';
    }
}
