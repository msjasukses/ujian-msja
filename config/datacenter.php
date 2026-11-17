<?php

/*
|------------------------------------------------------------------------------
| Berkas unggahan aplikasi Data Center (READ-ONLY)
|------------------------------------------------------------------------------
|
| Dipakai membaca logo sekolah untuk kop kartu peserta dan kepala halaman.
| Kolom sekolah.logo di database Data Center menyimpan path relatif seperti
| "sekolah/logo.png", bukan URL, jadi aplikasi ini perlu tahu direktori mana
| yang menjadi akarnya.
|
| Tidak ada satu jawaban yang benar untuk semua pemasangan: nama foldernya
| berbeda-beda, letaknya berbeda-beda, dan pada sebagian server berkasnya
| berakhir di public/storage alih-alih storage/app/public. Karena itu daftar
| di bawah tidak menebak nama: setiap folder tetangga diperiksa apakah ia
| sebuah aplikasi Laravel berisi direktori unggahan, berapa pun jumlahnya.
|
| Bila tetap tidak ketemu, App\Support\LogoSekolah menyisir "akar_pencarian"
| sampai menemukan berkasnya, lalu mengingat letaknya. Jadi penempatan yang
| tidak terduga pun tetap terbaca tanpa menyetel apa pun.
|
| Periksa dengan: php artisan sekolah:cek-logo
|
*/

$pisah = DIRECTORY_SEPARATOR;
$aplikasi = base_path();
$tetangga = dirname($aplikasi);

/** Direktori unggahan di dalam sebuah folder aplikasi, yang benar-benar ada. */
$unggahanDi = function (string $folder) use ($pisah): array {
    return array_values(array_filter([
        $folder.$pisah.'storage'.$pisah.'app'.$pisah.'public',
        $folder.$pisah.'public'.$pisah.'storage',
    ], 'is_dir'));
};

// Setiap folder tetangga, bukan daftar nama tertentu: pemasangan bernama
// "datacenter-msja", "dc", "datainduk" atau apa pun sama-sama terjaring.
// Yang namanya menyinggung data center didahulukan supaya pencarian berhenti
// di tempat yang paling mungkin, bukan di aplikasi lain yang kebetulan punya
// berkas bernama sama.
$folderTetangga = array_values(array_filter(
    (array) glob($tetangga.$pisah.'*', GLOB_ONLYDIR),
    fn ($folder) => $folder !== $aplikasi
));

usort($folderTetangga, function ($a, $b) {
    $skor = fn ($folder) => preg_match('~data.?center|datainduk|^dc$~i', basename($folder)) ? 0 : 1;

    return $skor($a) <=> $skor($b);
});

$unggahan = array_merge(
    array_filter([env('DATACENTER_STORAGE_PATH')]),
    ...array_map($unggahanDi, $folderTetangga),
);

return [

    /*
    | Calon akar direktori unggahan, dicoba berurutan sebelum menyisir.
    */
    'akar_unggahan' => array_values(array_unique($unggahan)),

    /*
    | Direktori yang disisir ketika berkas logonya tidak ada di calon mana pun:
    | folder tetangga, satu tingkat di atasnya, dan rumah pengguna. Hasil
    | penyisiran diingat, jadi ongkosnya hanya sekali.
    */
    'akar_pencarian' => array_values(array_unique(array_filter([
        $tetangga,
        dirname($tetangga),
        env('HOME') ?: env('USERPROFILE'),
    ], fn ($folder) => $folder && is_dir($folder) && dirname($folder) !== $folder))),

    /*
    | Berapa lama letak logo diingat. Nilai kolom sekolah.logo ikut menjadi
    | kunci ingatan, jadi logo yang diganti di Data Center langsung dicari
    | ulang tanpa menunggu tenggang ini habis.
    */
    'ingat_menit' => (int) env('DATACENTER_LOGO_INGAT_MENIT', 60 * 24 * 7),

];
