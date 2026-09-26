<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lampiran audio/video pada butir soal — untuk soal menyimak (listening) dan
 * soal yang bertumpu pada tayangan.
 *
 * Bukan jenis soal tersendiri, melainkan lampiran: satu rekaman percakapan
 * bisa dipasang pada butir pilihan ganda, essay, maupun benar/salah, dan
 * koreksi otomatisnya tetap mengikuti jenis soalnya.
 *
 * Yang disimpan hanya jalur berkas di disk publik; berkasnya sendiri ada di
 * storage/app/public/soal-media, seperti gambar soal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soal', function (Blueprint $table) {
            $table->string('media_path')->nullable()->after('pertanyaan');
            $table->string('media_tipe', 10)->nullable()->after('media_path');
        });
    }

    public function down(): void
    {
        Schema::table('soal', function (Blueprint $table) {
            $table->dropColumn(['media_path', 'media_tipe']);
        });
    }
};
