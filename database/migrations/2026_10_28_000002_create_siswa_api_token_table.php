<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token masuk aplikasi ponsel peserta.
 *
 * Peramban memakai sesi (lihat tabel sesi_siswa); aplikasi ponsel tidak punya
 * sesi, jadi tiap login melahirkan satu token acak yang dikirim kembali pada
 * setiap permintaan sebagai "Authorization: Bearer ...".
 *
 * Yang disimpan hanya sidik SHA-256-nya. Baris tabel ini terbaca oleh siapa
 * pun yang memegang cadangan database, dan token mentah di sana sama saja
 * dengan password siswa yang berlaku sampai kedaluwarsa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siswa_api_token', function (Blueprint $table) {
            $table->id();

            // Tanpa foreign key: tabel siswa tinggal di database "datacenter"
            // yang bersifat read-only bagi aplikasi ini.
            $table->unsignedBigInteger('siswa_id')->index();

            $table->string('token', 64)->unique();
            $table->string('perangkat', 120)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('terakhir_dipakai_at')->nullable();
            $table->timestamp('kedaluwarsa_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siswa_api_token');
    }
};
