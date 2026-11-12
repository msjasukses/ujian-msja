<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu baris = satu kelas moving yang dipasang pada sebuah ujian. */
class UjianKelasMoving extends Model
{
    protected $table = 'ujian_kelas_moving';

    protected $fillable = [
        'ujian_id',
        'kelas_moving_id',
    ];

    public function ujian()
    {
        return $this->belongsTo(Ujian::class);
    }

    public function kelasMoving()
    {
        return $this->belongsTo(KelasMoving::class, 'kelas_moving_id');
    }
}
