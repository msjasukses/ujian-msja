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

];
