<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu akun siswa, satu sesi.
 *
 * Penandanya disimpan di database ujian, bukan di tabel siswa, karena
 * database Data Center hanya boleh dibaca oleh aplikasi ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesi_siswa', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('siswa_id')->unique();
            $table->string('token', 64);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesi_siswa');
    }
};
