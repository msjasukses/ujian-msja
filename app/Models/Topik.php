<?php

namespace App\Models;

use App\Support\Pengguna;
use App\Support\Referensi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Topik / materi yang menjadi payung soal. Sumbernya dua: diinput manual,
 * atau ditarik dari pemetaan CP-TP-ATP database kurikulum lewat fitur
 * Sinkron (lihat App\Services\SinkronCpTpAtpService).
 */
class Topik extends Model
{
    protected $table = 'topik';

    public const SUMBER_MANUAL = 'manual';

    public const SUMBER_SINKRON = 'sinkron';

    protected $fillable = [
        'kode_topik',
        'nama_topik',
        'mata_pelajaran_id',
        'tingkat_kelas_id',
        'fase',
        'semester',
        'tahun_ajaran',
        'elemen',
        'capaian_pembelajaran',
        'tujuan_pembelajaran',
        'alur_tujuan_pembelajaran',
        'indikator_kktp',
        'sumber',
        'sumber_ref_id',
        'disinkron_pada',
        'urutan',
        'is_aktif',
    ];

    protected $casts = [
        'disinkron_pada' => 'datetime',
        'is_aktif' => 'boolean',
    ];

    /**
     * Topik yang relevan bagi pengguna yang sedang masuk.
     *
     * Guru hanya melihat topik pada mata pelajaran yang diampunya dan tingkat
     * kelas yang diajarnya; admin/operator melihat seluruhnya. Topik yang
     * mapel atau tingkatnya belum diisi ikut ditampilkan — topik semacam itu
     * tidak menunjuk mapel siapa pun, dan bila disembunyikan, topik yang baru
     * dibuat guru tanpa mengisi kedua kolom itu akan lenyap dari daftarnya
     * sendiri.
     */
    public function scopeMilikPengguna(Builder $q): Builder
    {
        if (! Pengguna::guruId()) {
            return $q;
        }

        return $q
            ->where(fn ($w) => $w
                ->whereIn('mata_pelajaran_id', Referensi::mapel()->pluck('id'))
                ->orWhereNull('mata_pelajaran_id'))
            ->where(fn ($w) => $w
                ->whereIn('tingkat_kelas_id', Referensi::tingkat()->pluck('id'))
                ->orWhereNull('tingkat_kelas_id'));
    }

    public function mataPelajaran()
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function tingkatKelas()
    {
        return $this->belongsTo(TingkatKelas::class);
    }

    public function soal(): HasMany
    {
        return $this->hasMany(Soal::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', true);
    }

    public function getDariSinkronAttribute(): bool
    {
        return $this->sumber === self::SUMBER_SINKRON;
    }
}
