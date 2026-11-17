<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Identitas sekolah — database "datacenter" (READ-ONLY). Dipakai pada kop laporan. */
class Sekolah extends Model
{
    protected $connection = 'datacenter';

    protected $table = 'sekolah';

    protected $fillable = [];

    public static function profil(): ?self
    {
        return static::query()->first();
    }

    /**
     * Path logo pada disk "datacenter", atau null bila sekolah belum
     * mengunggahnya di Data Center — atau berkasnya tak lagi ada di sana.
     *
     * Kolom sekolah.logo menyimpan path relatif pada disk publik aplikasi Data
     * Center (mis. "sekolah/logo.png"), bukan URL: berkasnya dilayani aplikasi
     * itu, di host yang berbeda. Karena itu di sini berkasnya dibaca langsung
     * dari direktorinya lalu dilayani ulang oleh aplikasi ini — kartu tetap
     * tercetak berlogo meski peladen Data Center sedang mati atau nama host-nya
     * tak dikenal peramban penggunanya.
     */
    public function pathLogo(): ?string
    {
        $logo = trim((string) ($this->logo ?? ''));

        // Nilai kolom ikut menentukan berkas mana yang dibuka; lintasan
        // direktori dan berkas non-gambar ditolak sebelum menyentuh disk.
        if ($logo === '' || ! preg_match('~^[\w-]+(/[\w-]+)*\.(png|jpe?g|svg|webp|gif)$~i', $logo)) {
            return null;
        }

        return Storage::disk('datacenter')->exists($logo) ? $logo : null;
    }

    /** URL logo sekolah yang dilayani aplikasi ini, bila ada logonya. */
    public function urlLogo(): ?string
    {
        return $this->pathLogo() ? route('logo-sekolah') : null;
    }
}
