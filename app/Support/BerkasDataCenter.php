<?php

namespace App\Support;

use FilesystemIterator;
use Illuminate\Support\Facades\Cache;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Mencari berkas unggahan milik Data Center — logo sekolah dan tanda tangan
 * kepala sekolah — di mana pun aplikasi itu dipasang.
 *
 * Kolom di database Data Center menyimpan path relatif ("sekolah/logo.png"),
 * bukan letak sebenarnya. Letak itu berbeda di tiap sekolah: nama foldernya
 * bebas, aplikasinya bisa berada satu atau dua tingkat di atas, dan berkasnya
 * kadang mendarat di public/storage alih-alih storage/app/public. Menebaknya
 * selalu meleset di suatu tempat, dan gejalanya diam: kartu tetap tercetak,
 * hanya tanpa logo atau tanpa tanda tangan.
 *
 * Karena itu urutannya: coba calon yang murah dulu, lalu sisir direktori
 * sekitar sampai berkasnya ketemu — dan ingat hasilnya, supaya penyisiran
 * terjadi sekali saja, bukan pada setiap halaman.
 *
 * Yang diingat ada dua: letak tiap berkas, dan akar direktori tempat ia
 * ditemukan. Akar itu dipakai lebih dulu pada pencarian berikutnya, sehingga
 * berkas kedua (dan berkas yang baru diganti) langsung ketemu tanpa menyisir.
 */
class BerkasDataCenter
{
    /** Direktori yang tidak pernah berisi unggahan, tetapi berisi puluhan ribu berkas. */
    protected const LEWATI = ['vendor', 'node_modules', '.git', '.svn', 'tmp', 'temp', 'cache', 'logs'];

    /** Unggahan tidak pernah terkubur sedalam ini; batas penjaga agar penyisiran tidak liar. */
    protected const KEDALAMAN = 6;

    protected const KUNCI_AKAR = 'datacenter-berkas:akar';

    /**
     * Letak sebuah berkas, atau null bila tidak ada di mana pun.
     *
     * $relatif adalah isi kolom Data Center yang sudah lolos pemeriksaan di
     * App\Models\Sekolah: path relatif tanpa lintasan direktori.
     */
    public static function path(string $relatif): ?string
    {
        $kunci = static::kunci($relatif);
        $diingat = static::diingat($kunci);

        if ($diingat === '') {
            return null;
        }

        // Berkas yang sudah pindah atau terhapus dicari ulang, bukan dipercaya
        // begitu saja sampai tenggang ingatannya habis.
        if ($diingat !== null && static::terbaca($diingat)) {
            return $diingat;
        }

        $path = static::cari($relatif);

        static::ingat($kunci, (string) $path, (int) (
            // Ketiadaan berkas diingat sebentar saja: begitu operator
            // mengunggahnya di Data Center, kartu harus segera memakainya.
            $path ? config('datacenter.ingat_menit', 10080) : 10
        ));

        return $path;
    }

    /** Lupakan letak yang diingat, mis. setelah berkasnya diganti. */
    public static function lupakan(string $relatif): void
    {
        rescue(function () use ($relatif) {
            Cache::forget(static::kunci($relatif));
            Cache::forget(static::KUNCI_AKAR);
        }, null, false);
    }

    /**
     * Cari berkasnya: akar yang pernah berhasil, lalu calon di config, lalu
     * penyisiran direktori sekitar.
     */
    public static function cari(string $relatif): ?string
    {
        foreach (static::calon($relatif) as $calon) {
            if (static::terbaca($calon)) {
                return $calon;
            }
        }

        return static::sisir($relatif);
    }

    /**
     * Seluruh tempat berkas itu mungkin berada tanpa menyisir, berurutan.
     *
     * @return array<int, string>
     */
    public static function calon(string $relatif): array
    {
        $akar = array_merge(
            array_filter([static::diingat(static::KUNCI_AKAR)]),
            (array) config('datacenter.akar_unggahan', []),
        );

        return array_values(array_unique(array_map(
            fn ($satu) => static::gabung($satu, $relatif),
            array_filter($akar, 'is_string'),
        )));
    }

    /**
     * Sisir direktori sekitar sampai menemukan berkas yang path-nya berakhir
     * dengan $relatif, lalu ingat akar direktorinya.
     */
    public static function sisir(string $relatif): ?string
    {
        $nama = basename($relatif);
        $ekor = DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relatif);

        foreach ((array) config('datacenter.akar_pencarian', []) as $akar) {
            foreach (static::berkasDi($akar) as $temuan) {
                if ($temuan->getFilename() !== $nama) {
                    continue;
                }

                $path = $temuan->getPathname();

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

        return $akar !== null && $akar !== '' ? $akar : null;
    }

    protected static function kunci(string $relatif): string
    {
        return 'datacenter-berkas:'.md5($relatif);
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

    protected static function gabung(string $akar, string $relatif): string
    {
        return rtrim($akar, '/'.DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .str_replace('/', DIRECTORY_SEPARATOR, $relatif);
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
