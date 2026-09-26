<?php

namespace App\Support;

use App\Models\Guru;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Pembantu kecil untuk membaca "siapa yang sedang login" tanpa harus
 * mengingat tiga guard di setiap controller dan view.
 */
class Pengguna
{
    /** Guard yang sedang aktif: web | guru | siswa | null. */
    public static function guard(): ?string
    {
        foreach (['web', 'guru', 'siswa'] as $guard) {
            if (Auth::guard($guard)->check()) {
                return $guard;
            }
        }

        return null;
    }

    public static function user(): User|Guru|Siswa|null
    {
        $guard = static::guard();

        return $guard ? Auth::guard($guard)->user() : null;
    }

    public static function nama(): string
    {
        $u = static::user();

        return match (true) {
            $u instanceof User => $u->name,
            $u instanceof Guru => $u->nama_ptk,
            $u instanceof Siswa => $u->nama_siswa,
            default => 'Tamu',
        };
    }

    /** Label peran untuk ditampilkan di header. */
    public static function peran(): string
    {
        return match (static::guard()) {
            'web' => ucfirst((string) (static::user()->role ?? 'admin')),
            'guru' => 'Guru',
            'siswa' => 'Siswa',
            default => '-',
        };
    }

    public static function isAdmin(): bool
    {
        return Auth::guard('web')->check();
    }

    public static function isGuru(): bool
    {
        return Auth::guard('guru')->check();
    }

    public static function isSiswa(): bool
    {
        return Auth::guard('siswa')->check();
    }

    /**
     * Id guru yang sedang login, atau null bila yang login admin. Dipakai
     * untuk membatasi bank soal & ujian milik guru yang bersangkutan.
     */
    public static function guruId(): ?int
    {
        // Satu peramban bisa memegang sesi admin dan guru sekaligus — operator
        // sekolah kerap masuk sebagai guru untuk memeriksa tampilannya. Dalam
        // keadaan itu yang berlaku sesi admin, mengikuti urutan pada guard(),
        // supaya halaman pengelola tidak diam-diam tersaring seperti milik guru.
        return static::guard() === 'guru' ? (int) Auth::guard('guru')->id() : null;
    }

    public static function siswaId(): ?int
    {
        return static::isSiswa() ? (int) Auth::guard('siswa')->id() : null;
    }

    /**
     * Peserta yang sedang masuk, diambil langsung dari guard siswa.
     *
     * Berbeda dengan user(), yang mendahulukan guard web. Satu peramban
     * bisa memegang sesi admin dan sesi siswa sekaligus — operator sekolah
     * kerap masuk sebagai siswa untuk memeriksa tampilannya — dan di situ
     * user() mengembalikan si admin, bukan pesertanya.
     */
    public static function siswa(): ?Siswa
    {
        return static::isSiswa() ? Auth::guard('siswa')->user() : null;
    }
}
