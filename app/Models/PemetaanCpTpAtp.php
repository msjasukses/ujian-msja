<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pemetaan CP-TP-ATP milik aplikasi SIM Kurikulum — dibaca dari database
 * "kurikulum" (READ-ONLY). Menjadi sumber fitur "Sinkron CP-TP-ATP" pada
 * menu Topik: tiap baris pemetaan di sini dapat ditarik menjadi satu topik.
 *
 * Relasi mata pelajaran & tingkat kelas sengaja tidak didefinisikan sebagai
 * belongsTo Eloquent karena tabel tujuannya ada di koneksi "datacenter",
 * bukan "kurikulum" — join lintas koneksi dilakukan manual di service.
 */
class PemetaanCpTpAtp extends Model
{
    protected $connection = 'kurikulum';

    protected $table = 'pemetaan_cp_tp_atp';

    protected $fillable = [];

    protected $casts = [
        'model_pembelajaran' => 'array',
        'sumber_belajar' => 'array',
        'karakter_dpl' => 'array',
    ];
}
