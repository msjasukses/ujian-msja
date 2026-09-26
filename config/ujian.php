<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Penanda peramban ujian pada user agent
    |--------------------------------------------------------------------------
    |
    | Menu Log Login menandai sesi yang dibuka lewat peramban ujian. Bawaannya
    | mengenali penanda WebView Android klasik — "; wv)" dan "Version/4.0".
    |
    | Sebagian build ExamBro tidak lagi memakai penanda itu. Perangkat yang
    | diuji 10 September 2026 mengirim user agent Chrome yang sudah direduksi:
    |
    |     Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like
    |     Gecko) Chrome/152.0.0.0 Mobile Safari/537.36
    |
    | Tidak ada satu pun bagian di sana yang membedakannya dari Chrome Android
    | biasa. Selama build seperti itu dipakai, penandaan lewat user agent
    | memang tidak mungkin — dan menebaknya akan salah menandai setiap siswa
    | yang memakai Chrome sungguhan.
    |
    | Jalan keluarnya: bila peramban ujian sekolah dapat disetel menambahkan
    | potongan teks pada user agent-nya (banyak yang bisa), isikan potongan itu
    | di sini. Beberapa penanda dipisahkan koma. Lihat user agent apa adanya di
    | menu Log Login untuk menemukannya.
    |
    |     UJIAN_PENANDA_EXAMBRO="ExamBro,SEB/"
    |
    */

    'penanda_exambro' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('UJIAN_PENANDA_EXAMBRO', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Batas ukuran lampiran audio/video pada butir soal (MB)
    |--------------------------------------------------------------------------
    |
    | Berkasnya dilayani server ujian sendiri dan diunduh setiap peserta saat
    | butirnya tampil. Pada jaringan sekolah, video 50 MB yang dibuka 30 siswa
    | sekaligus berarti 1,5 GB melintas — perkecil dulu berkasnya sebelum
    | menaikkan batas ini.
    |
    | Batas di sini juga harus muat pada batas unggah peladen web:
    | client_max_body_size pada nginx, dan upload_max_filesize/post_max_size
    | pada PHP.
    |
    */

    'maks_media_mb' => [
        'audio' => (int) env('UJIAN_MAKS_AUDIO_MB', 20),
        'video' => (int) env('UJIAN_MAKS_VIDEO_MB', 50),
    ],

];
