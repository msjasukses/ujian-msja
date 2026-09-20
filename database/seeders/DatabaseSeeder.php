<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Hanya akun pengelola yang di-seed. Data siswa, guru, mapel, kelas dan
     * tahun ajaran tidak pernah dibuat dari sini — semuanya milik aplikasi
     * Data Center dan dibaca lewat koneksi "datacenter".
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@ujian.test'],
            [
                'name' => 'Administrator Ujian',
                'password' => 'password',
                'role' => User::ROLE_ADMIN,
                'is_aktif' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'operator@ujian.test'],
            [
                'name' => 'Operator Ujian',
                'password' => 'password',
                'role' => User::ROLE_OPERATOR,
                'is_aktif' => true,
            ]
        );
    }
}
