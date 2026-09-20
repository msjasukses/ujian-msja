<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UjianKelas extends Model
{
    protected $table = 'ujian_kelas';

    protected $fillable = [
        'ujian_id',
        'rombongan_belajar_id',
    ];

    public function ujian()
    {
        return $this->belongsTo(Ujian::class);
    }

    public function rombel()
    {
        return $this->belongsTo(RombonganBelajar::class, 'rombongan_belajar_id');
    }
}
