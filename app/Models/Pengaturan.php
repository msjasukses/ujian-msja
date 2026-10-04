<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Once;

/**
 * Setelan aplikasi berbentuk pasangan kunci–nilai, diubah admin lewat menu
 * Pengaturan. Seluruh isinya dibaca sekali per permintaan, karena setelan
 * seperti jenjang ditanyakan berulang kali oleh tiap formulir di satu halaman.
 */
class Pengaturan extends Model
{
    protected $table = 'pengaturan';

    public const JENJANG = 'jenjang';

    protected $fillable = ['kunci', 'nilai'];

    public static function ambil(string $kunci, ?string $bawaan = null): ?string
    {
        $semua = once(fn () => static::query()->pluck('nilai', 'kunci')->all());

        return $semua[$kunci] ?? $bawaan;
    }

    public static function simpan(string $kunci, ?string $nilai): void
    {
        static::updateOrCreate(['kunci' => $kunci], ['nilai' => $nilai]);

        // Bacaan sebelumnya sudah basi; permintaan yang sama harus melihat
        // nilai barunya.
        Once::flush();
    }
}
