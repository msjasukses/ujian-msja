<?php

use App\Models\Guru;
use App\Models\Siswa;
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Aplikasi ini punya tiga jenis pemakai:
    |
    |  web   -> admin/operator, akun lokal di database "ujian"
    |  guru  -> guru pembuat & pengawas soal, kredensial milik database
    |           "datacenter" (login memakai NIP)
    |  siswa -> peserta ujian, juga dari database "datacenter" (login NISN)
    |
    | Guard guru & siswa membaca tabel di koneksi "datacenter" yang bersifat
    | read-only, jadi aplikasi ini tidak pernah mengubah password mereka.
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'guru' => [
            'driver' => 'session',
            'provider' => 'guru',
        ],

        'siswa' => [
            'driver' => 'session',
            'provider' => 'siswa',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],

        'guru' => [
            'driver' => 'eloquent',
            'model' => Guru::class,
        ],

        'siswa' => [
            'driver' => 'eloquent',
            'model' => Siswa::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
