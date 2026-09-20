<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kewajiban mengerjakan lewat peramban ujian (ExamBro).
 *
 * Dipasang per ujian, seperti proteksi_ketat: ulangan harian di kelas yang
 * diawasi langsung kerap dikerjakan lewat peramban biasa, sedangkan ujian
 * sekolah mensyaratkan peramban ujian.
 *
 * Bawaannya mati. Menyalakannya secara massal akan memblokir peserta pada
 * ujian yang terlanjur berjalan di peramban biasa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ujian', function (Blueprint $table) {
            $table->boolean('wajib_exambro')->default(false)->after('maks_pelanggaran');
        });
    }

    public function down(): void
    {
        Schema::table('ujian', function (Blueprint $table) {
            $table->dropColumn('wajib_exambro');
        });
    }
};
