<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas moving yang dipasang pada sebuah ujian.
 *
 * Sejajar dengan tabel ujian_kelas, hanya saja rombongannya diambil dari
 * tabel kelas_moving di database "datacenter" — karena itu tanpa foreign key
 * (relasi lintas database, lihat catatan di migration tabel inti).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ujian_kelas_moving', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ujian_id')->constrained('ujian')->cascadeOnDelete();
            $table->unsignedBigInteger('kelas_moving_id')->index();
            $table->timestamps();

            $table->unique(['ujian_id', 'kelas_moving_id'], 'ujian_kelas_moving_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ujian_kelas_moving');
    }
};
