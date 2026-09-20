<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seluruh data contoh untuk uji coba aplikasi:
 *
 *   php artisan db:seed --class=ContohSeeder
 *
 * Sengaja dipisah dari DatabaseSeeder supaya data contoh tidak pernah ikut
 * masuk saat menyiapkan lingkungan sebenarnya. Perintah ini aman diulang —
 * topik & soal diperbarui berdasarkan kodenya, sedangkan paket dan jadwal
 * ujian contoh dibuat ulang dari awal.
 *
 * Prasyarat: database Data Center sudah berisi siswa, guru, mata pelajaran,
 * tingkat kelas dan rombongan belajar pada tahun ajaran yang aktif.
 */
class ContohSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ContohBankSoalSeeder::class,
            ContohUjianSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('Data contoh siap dipakai. Masuk sebagai admin@ujian.test / password.');
    }
}
