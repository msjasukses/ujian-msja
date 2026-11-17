<?php

namespace App\Support;

use FilesystemIterator;
use Illuminate\Support\Facades\Cache;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Mencari berkas logo sekolah milik Data Center, di mana pun ia dipasang.
 *
 * Kolom sekolah.logo menyimpan path relatif ("sekolah/logo.png"), bukan letak
 * sebenarnya. Letak itu berbeda di tiap sekolah — nama foldernya bebas,
 * aplikasinya bisa berada satu atau dua tingkat di atas, dan berkasnya kadang
 * mendarat di public/storage alih-alih storage/app/public. Menebaknya selalu
 * meleset di suatu tempat, dan gejalanya diam: kartu tetap tercetak, hanya
 * tanpa logo.
 *
 * Karena itu urutannya: coba calon yang murah dulu, lalu sisir direktori
 * sekitar sampai berkasnya ketemu — dan ingat hasilnya, supaya penyisiran
 * terjadi sekali saja, bukan pada setiap halaman.
 *
 * Yang diingat ada dua: letak berkasnya, dan akar direktori tempat ia
 * ditemukan. Akar itu dipakai lebih dulu pada pencarian berikutnya, sehingga
 * logo yang diganti di Data Center langsung terbaca tanpa menyisir lagi.
 */
class LogoSekolah
{
    /** Direktori yang tidak pernah berisi unggahan, tetapi berisi puluhan ribu berkas. */
    protected const LEWATI = ['vendor', 'node_modules', '.git', '.svn', 'tmp', 'temp', 'cache', 'logs'];

    /** Unggahan tidak pernah terkubur sedalam ini; batas penjaga agar penyisiran tidak liar. */
    protected const KEDALAMAN = 6;

    protected const KUNCI_AKAR = 'logo-sekolah:akar';

    /**
     * Letak berkas logo, atau null bila tidak ada di mana pun.
     *
     * $logo adalah isi kolom sekolah.logo yang sudah lolos pemeriksaan di
     * App\Models\Sekolah: path relatif tanpa lintasan direktori.
     */
    public static function path(string $logo): ?string
    {
        $kunci = 'logo-sekolah:'.md5($logo);

        $diingat = static::diingat($kunci);

        if (is_string($diingat)) {
            // Berkas yang sudah pindah atau terhapus dicari ulang, bukan
            // dipercaya begitu saja sampai tenggangnya habis.
            if ($diingat !== '' && static::terbaca($diingat)) {
                return $diingat;
            }

            if ($diingat === '') {
                return null;
            }
        }

        $path = static::cari($logo);

        static::ingat($kunci, (string) $path, (int) (
            // Ketiadaan logo diingat sebentar saja: begitu operator
            // mengunggahnya di Data Center, kartu harus segera berlogo.
            $path ? config('datacenter.ingat_menit', 10080) : 10
        ));

        return $path;
    }

    /** Lupakan letak yang diingat, mis. setelah logonya diganti. */
    public static function lupakan(string $logo): void
    {
        rescue(function () use ($logo) {
            Cache::forget('logo-sekolah:'.md5($logo));
            Cache::forget(static::KUNCI_AKAR);
        }, null, false);
    }

    /**
     * Cari berkasnya: akar yang pernah berhasil, lalu calon di config, lalu
     * penyisiran direktori sekitar.
     */
    public static function cari(string $logo): ?string
    {
        foreach (static::calon($logo) as $berkas) {
            if (static::terbaca($berkas)) {
                return $berkas;
            }
        }

        return static::sisir($logo);
    }

    /**
     * Seluruh tempat berkas logo mungkin berada tanpa menyisir, berurutan.
     *
     * @return array<int, string>
     */
    public static function calon(string $logo): array
    {
        $akar = array_merge(
            array_filter([static::diingat(static::KUNCI_AKAR)]),
            (array) config('datacenter.akar_unggahan', []),
        );

        return array_values(array_unique(array_map(
            fn ($satu) => static::gabung($satu, $logo),
            array_filter($akar, 'is_string'),
        )));
    }

    /**
     * Sisir direktori sekitar sampai menemukan berkas yang path-nya berakhir
     * dengan $logo, lalu ingat akar direktorinya.
     */
    public static function sisir(string $logo): ?string
    {
        $nama = basename($logo);
        $ekor = DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $logo);

        foreach ((array) config('datacenter.akar_pencarian', []) as $akar) {
            foreach (static::berkasDi($akar) as $berkas) {
                if ($berkas->getFilename() !== $nama) {
                    continue;
                }

                $path = $berkas->getPathname();

                // Dicocokkan sampai ke nama foldernya, bukan nama berkas saja:
                // aplikasi lain di server yang sama bisa punya berkas bernama
                // persis sama tanpa ada hubungannya dengan Data Center.
                if (! str_ends_with($path, $ekor) || ! static::terbaca($path)) {
                    continue;
                }

                static::ingat(static::KUNCI_AKAR, substr($path, 0, -strlen($ekor)),
                    (int) config('datacenter.ingat_menit', 10080));

                return $path;
            }
        }

        return null;
    }

    /** Akar direktori yang terakhir kali berhasil, bila masih diingat. */
    public static function akarDiingat(): ?string
    {
        $akar = static::diingat(static::KUNCI_AKAR);

        return is_string($akar) && $akar !== '' ? $akar : null;
    }

    /**
     * Baca ingatan. Penyimpan cache yang rusak atau belum bermigrasi tidak
     * boleh menjatuhkan halaman: paling buruk pencariannya diulang.
     */
    protected static function diingat(string $kunci): ?string
    {
        $nilai = rescue(fn () => Cache::get($kunci), null, false);

        return is_string($nilai) ? $nilai : null;
    }

    protected static function ingat(string $kunci, string $nilai, int $menit): void
    {
        rescue(fn () => Cache::put($kunci, $nilai, now()->addMinutes($menit)), null, false);
    }

    protected static function gabung(string $akar, string $logo): string
    {
        return rtrim($akar, '/'.DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .str_replace('/', DIRECTORY_SEPARATOR, $logo);
    }

    protected static function terbaca(string $berkas): bool
    {
        return is_file($berkas) && is_readable($berkas);
    }

    /** @return iterable<SplFileInfo> */
    protected static function berkasDi(string $akar): iterable
    {
        if (! is_dir($akar) || ! is_readable($akar)) {
            return [];
        }

        $isi = new RecursiveDirectoryIterator($akar, FilesystemIterator::SKIP_DOTS);

        $saring = new RecursiveCallbackFilterIterator($isi, function (SplFileInfo $berkas) {
            if (! $berkas->isDir()) {
                return true;
            }

            $nama = $berkas->getFilename();

            return ! in_array(strtolower($nama), static::LEWATI, true) && ! str_starts_with($nama, '.');
        });

        $jalan = new RecursiveIteratorIterator($saring, RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD);

        $jalan->setMaxDepth(static::KEDALAMAN);

        return $jalan;
    }
}
