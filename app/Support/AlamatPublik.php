<?php

namespace App\Support;

/**
 * Alamat publik berkas pada disk "public" — gambar, audio, dan video soal.
 *
 * Aplikasi bisa dipasang di akar domain (mis. Laragon) maupun di subfolder
 * (mis. https://sekolah.sch.id/cbt di VPS). Alamat yang ditulis mati sebagai
 * "/storage/..." hanya benar pada yang pertama; di subfolder peramban
 * memintanya dari akar domain, mendapat 403/404, dan gambar soal tampil rusak.
 *
 * Karena itu yang tersimpan di database tetap bentuk netral "/storage/...",
 * dan awalan subfolder baru dipasang saat ditampilkan. Isi bank soal pun tetap
 * utuh ketika aplikasi dipindah dari satu alamat ke alamat lain — dari
 * Laragon ke VPS, atau dari subfolder ke subdomain.
 *
 * Awalannya diambil dari permintaan yang sedang dilayani, bukan dari APP_URL:
 * itulah alamat yang benar-benar dipakai peramban, dan aplikasi tetap benar
 * walau APP_URL lupa disesuaikan saat dipindahkan.
 */
final class AlamatPublik
{
    /** Awalan subfolder aplikasi, mis. "/cbt"; kosong bila di akar domain. */
    public static function dasar(): string
    {
        return app()->bound('request') ? rtrim(request()->getBasePath(), '/') : '';
    }

    /** "soal-media/a.mp3" → "/cbt/storage/soal-media/a.mp3". */
    public static function berkas(string $jalur): string
    {
        return self::dasar().'/storage/'.ltrim($jalur, '/');
    }

    /** "/storage/soal/a.png" → "/cbt/storage/soal/a.png"; alamat lain dibiarkan. */
    public static function tampil(string $alamat): string
    {
        return str_starts_with($alamat, '/storage/') ? self::dasar().$alamat : $alamat;
    }

    /** Pasang awalan subfolder pada setiap src="/storage/..." di sepotong HTML. */
    public static function htmlTampil(string $html): string
    {
        $dasar = self::dasar();

        if ($dasar === '' || stripos($html, 'src=') === false) {
            return $html;
        }

        return (string) preg_replace('#(\ssrc\s*=\s*["\'])/storage/#i', '${1}'.$dasar.'/storage/', $html);
    }

    /**
     * Kebalikan htmlTampil(): kembalikan src="/cbt/storage/..." ke bentuk
     * netral sebelum disimpan. Editor menampilkan gambar dengan alamat
     * bersubfolder, dan alamat itulah yang ikut terkirim bersama formulir.
     */
    public static function htmlSimpan(string $html): string
    {
        $dasar = self::dasar();

        if ($dasar === '' || stripos($html, 'src=') === false) {
            return $html;
        }

        return (string) preg_replace(
            '#(\ssrc\s*=\s*["\'])'.preg_quote($dasar, '#').'/storage/#i',
            '${1}/storage/',
            $html
        );
    }
}
