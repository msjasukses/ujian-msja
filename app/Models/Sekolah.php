<?php

namespace App\Models;

use App\Support\BerkasDataCenter;
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
     * Pencarian letaknya diserahkan ke App\Support\BerkasDataCenter, yang
     * tahu berbagai kemungkinan penempatan Data Center.
     */
    public function pathLogo(): ?string
    {
        $logo = $this->logoTercatat();

        return $logo ? BerkasDataCenter::path($logo) : null;
    }

    /** URL logo sekolah yang dilayani aplikasi ini, bila ada logonya. */
    public function urlLogo(): ?string
    {
        return $this->pathLogo() ? route('logo-sekolah') : null;
    }

    /** Letak berkas tanda tangan kepala sekolah, atau null bila tidak ada. */
    public function pathTtd(): ?string
    {
        $ttd = $this->ttdTercatat();

        return $ttd ? BerkasDataCenter::path($ttd) : null;
    }

    /**
     * URL tanda tangan kepala sekolah, bila ada berkasnya.
     *
     * Berbeda dari logo, rutenya hanya untuk pengelola: tanda tangan kepala
     * sekolah bukan lambang yang boleh diunduh siapa saja.
     */
    public function urlTtd(): ?string
    {
        return $this->pathTtd() ? route('ttd-kepala-sekolah') : null;
    }

    /** Isi kolom sekolah.logo yang aman dipakai sebagai path, atau null. */
    public function logoTercatat(): ?string
    {
        return static::pathAman($this->logo);
    }

    /** Isi kolom sekolah.ttd_kepala_sekolah yang aman dipakai sebagai path, atau null. */
    public function ttdTercatat(): ?string
    {
        return static::pathAman($this->ttd_kepala_sekolah);
    }

    /**
     * Nilai kolom yang aman dipakai sebagai path berkas, atau null.
     *
     * Nilainya ikut menentukan berkas mana yang dibuka dan datangnya dari
     * aplikasi lain, jadi lintasan direktori dan berkas non-gambar ditolak
     * sebelum menyentuh disk.
     */
    protected static function pathAman(mixed $nilai): ?string
    {
        $path = trim((string) ($nilai ?? ''));

        return $path !== '' && preg_match('~^[\w-]+(/[\w-]+)*\.(png|jpe?g|svg|webp|gif)$~i', $path)
            ? $path
            : null;
    }
}
