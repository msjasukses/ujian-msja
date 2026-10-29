<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Token masuk aplikasi ponsel peserta.
 *
 * Aturannya sama dengan sesi peramban (lihat SesiSiswaService): satu akun
 * siswa hanya sah di satu perangkat, dan login terbaru yang menang. Yang
 * berbeda hanya wadahnya — peramban memakai sesi, aplikasi memakai token.
 */
class SiswaApiToken extends Model
{
    protected $table = 'siswa_api_token';

    /**
     * Umur token. Dibuat jauh lebih panjang dari sesi peramban: aplikasi
     * ponsel dipasang sekali lalu dipakai berhari-hari, dan memaksa siswa
     * mengetik NISN tiap pagi hanya menambah antrean di depan ruang ujian.
     */
    public const UMUR_HARI = 30;

    /**
     * Sesudah selama ini tanpa dipakai, sebuah token dianggap milik perangkat
     * yang sudah ditinggalkan — bukan perangkat kedua yang sedang aktif.
     * Disamakan dengan SesiSiswaService::MENIT_AKTIF.
     */
    public const MENIT_AKTIF = 10;

    protected $fillable = [
        'siswa_id',
        'token',
        'perangkat',
        'ip_address',
        'user_agent',
        'terakhir_dipakai_at',
        'kedaluwarsa_at',
    ];

    protected $casts = [
        'terakhir_dipakai_at' => 'datetime',
        'kedaluwarsa_at' => 'datetime',
    ];

    protected $hidden = ['token'];

    /** Yang masuk database hanyalah sidiknya; mentahnya hanya sekali dikirim. */
    public static function sidik(string $mentah): string
    {
        return hash('sha256', $mentah);
    }

    public static function acak(): string
    {
        return Str::random(64);
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }

    public function sudahKedaluwarsa(): bool
    {
        return $this->kedaluwarsa_at !== null && $this->kedaluwarsa_at->isPast();
    }

    /** Masih dipegang perangkat yang sedang aktif, bukan sisa kemarin. */
    public function masihAktif(): bool
    {
        $terakhir = $this->terakhir_dipakai_at ?? $this->created_at;

        return $terakhir !== null && $terakhir->gt(now()->subMinutes(self::MENIT_AKTIF));
    }
}
