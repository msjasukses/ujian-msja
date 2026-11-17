<?php

namespace App\Models;

use App\Support\LogoSekolah;
use Illuminate\Database\Eloquent\Model;

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
     * Letak berkas logo sekolah, atau null bila tidak ada.
     *
     * Kolom sekolah.logo menyimpan path relatif pada direktori unggahan
     * aplikasi Data Center (mis. "sekolah/logo.png"), bukan URL: berkasnya
     * dilayani aplikasi itu, di host yang berbeda. Karena itu di sini
     * berkasnya dibaca langsung dari direktorinya lalu dilayani ulang oleh
     * aplikasi ini — kartu tetap tercetak berlogo meski peladen Data Center
     * sedang mati atau nama host-nya tak dikenal peramban penggunanya.
     *
     * Pencarian letaknya diserahkan ke App\Support\LogoSekolah, yang tahu
     * berbagai kemungkinan penempatan Data Center.
     */
    public function pathLogo(): ?string
    {
        $logo = $this->logoTercatat();

        return $logo ? LogoSekolah::path($logo) : null;
    }

    /**
     * Isi kolom sekolah.logo yang aman dipakai sebagai path, atau null.
     *
     * Nilainya ikut menentukan berkas mana yang dibuka dan datangnya dari
     * aplikasi lain, jadi lintasan direktori dan berkas non-gambar ditolak
     * sebelum menyentuh disk.
     */
    public function logoTercatat(): ?string
    {
        $logo = trim((string) ($this->logo ?? ''));

        return $logo !== '' && preg_match('~^[\w-]+(/[\w-]+)*\.(png|jpe?g|svg|webp|gif)$~i', $logo)
            ? $logo
            : null;
    }

    /** URL logo sekolah yang dilayani aplikasi ini, bila ada logonya. */
    public function urlLogo(): ?string
    {
        return $this->pathLogo() ? route('logo-sekolah') : null;
    }
}
