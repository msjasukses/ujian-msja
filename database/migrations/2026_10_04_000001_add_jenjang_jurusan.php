<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jenjang sekolah dan jurusan.
 *
 * Tabel pengaturan menyimpan setelan aplikasi yang diubah admin lewat menu
 * Pengaturan — saat ini jenjang sekolah. Data Center punya kolom
 * sekolah.jenjang, tetapi database itu hanya bisa dibaca dari sini, jadi
 * setelan aplikasi ini disimpan sendiri dan Data Center hanya menjadi nilai
 * awalnya.
 *
 * Kolom jurusan_id menunjuk ke tabel jurusan di database "datacenter", jadi
 * sengaja tanpa foreign key, sama seperti tingkat_kelas_id. Kosong berarti
 * berlaku untuk semua jurusan (mapel umum), dan begitulah seluruh data lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan', function (Blueprint $table) {
            $table->id();
            $table->string('kunci', 60)->unique();
            $table->text('nilai')->nullable();
            $table->timestamps();
        });

        foreach (['topik', 'soal', 'paket_soal'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->unsignedBigInteger('jurusan_id')->nullable()->index()->after('tingkat_kelas_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['topik', 'soal', 'paket_soal'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->dropIndex(['jurusan_id']);
                $table->dropColumn('jurusan_id');
            });
        }

        Schema::dropIfExists('pengaturan');
    }
};
