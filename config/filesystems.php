<?php

/*
|------------------------------------------------------------------------------
| Direktori unggahan aplikasi Data Center
|------------------------------------------------------------------------------
|
| Dipakai disk "datacenter" di bawah untuk membaca logo sekolah. Yang disetel
| di .env selalu menang. Tanpa itu, nama folder tetangga yang lazim dicoba satu
| per satu: sekolah memasang kedua aplikasi berdampingan, tetapi nama foldernya
| berbeda-beda, dan tanpa tebakan ini logonya diam-diam tidak pernah tampil.
|
| Cek hasil tebakannya dengan: php artisan sekolah:cek-logo
|
*/

$datacenterStorage = env('DATACENTER_STORAGE_PATH') ?: (function () {
    $calon = ['datacenter-msja', 'datacenter', 'datacenter_v2', 'datacenter-v2', 'dc'];

    foreach ($calon as $nama) {
        $path = dirname(__DIR__).'/../'.$nama.'/storage/app/public';

        if (is_dir($path)) {
            return realpath($path) ?: $path;
        }
    }

    return dirname(__DIR__).'/../'.$calon[0].'/storage/app/public';
})();

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Berkas unggahan aplikasi Data Center (READ-ONLY) — dipakai membaca
        // logo sekolah: kolom sekolah.logo di sana menyimpan path relatif pada
        // disk ini (mis. "sekolah/logo.png"), bukan URL. Akarnya dicari di
        // atas berkas ini; setel DATACENTER_STORAGE_PATH bila tebakannya
        // meleset.
        'datacenter' => [
            'driver' => 'local',
            'root' => $datacenterStorage,
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
