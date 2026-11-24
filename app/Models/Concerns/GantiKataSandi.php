<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Hash;

/**
 * Pemilik akun mengganti kata sandinya sendiri, untuk model yang tabelnya
 * ada di database "datacenter" (Siswa dan Guru).
 *
 * Tabel-tabel itu milik aplikasi Data Center dan aplikasi ujian hanya
 * membacanya — $fillable keduanya sengaja dikosongkan. Kolom password adalah
 * satu-satunya perkecualian, dengan dua alasan:
 *
 *  - yang mengubahnya pemilik akun itu sendiri, bukan pengelola aplikasi ini;
 *  - nilainya harus mendarat di tabel yang sama dengan yang dibaca saat masuk,
 *    supaya satu akun tetap satu kata sandi di kedua aplikasi. Menyimpannya
 *    terpisah di database ujian akan membuat dua kata sandi untuk satu orang.
 *
 * Penulisannya lewat query builder, bukan save(), supaya $fillable tetap
 * kosong dan tidak ada kolom lain yang bisa ikut terbawa.
 */
trait GantiKataSandi
{
    /** Apakah kata sandi yang diketik cocok dengan yang tersimpan. */
    public function kataSandiCocok(string $kataSandi): bool
    {
        return filled($this->password) && Hash::check($kataSandi, $this->password);
    }

    public function gantiKataSandi(string $kataSandiBaru): void
    {
        $hash = Hash::make($kataSandiBaru);

        $this->getConnection()
            ->table($this->getTable())
            ->where($this->getKeyName(), $this->getKey())
            ->update(['password' => $hash, 'updated_at' => now()]);

        // Atribut di memori ikut disegarkan: dalam permintaan yang sama, objek
        // ini masih yang dipegang guard autentikasi.
        $this->forceFill(['password' => $hash])->syncOriginal();
    }
}
