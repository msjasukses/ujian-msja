<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaketSoalDetail extends Model
{
    protected $table = 'paket_soal_detail';

    protected $fillable = [
        'paket_soal_id',
        'soal_id',
        'nomor_urut',
        'bobot',
    ];

    protected $casts = [
        'bobot' => 'decimal:2',
    ];

    public function paketSoal()
    {
        return $this->belongsTo(PaketSoal::class);
    }

    public function soal()
    {
        return $this->belongsTo(Soal::class);
    }
}
