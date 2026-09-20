<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proteksi kecurangan: tab baru, layar terbelah, dan jendela mengambang.
 *
 * Aturannya dipasang per ujian, bukan global, karena ulangan harian di kelas
 * yang diawasi langsung tidak perlu diperlakukan seketat ujian sekolah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ujian', function (Blueprint $table) {
            $table->boolean('proteksi_ketat')->default(true)->after('acak_opsi');

            // Batas pelanggaran sebelum lembar dikunci. 0 = hanya diperingatkan
            // dan dicatat, lembarnya tidak pernah dikunci sendiri oleh sistem.
            $table->unsignedTinyInteger('maks_pelanggaran')->default(3)->after('proteksi_ketat');
        });

        Schema::table('ujian_peserta', function (Blueprint $table) {
            $table->unsignedSmallInteger('pelanggaran')->default(0)->after('reset_count');
            $table->timestamp('dikunci_at')->nullable()->after('pelanggaran');
        });
    }

    public function down(): void
    {
        Schema::table('ujian', function (Blueprint $table) {
            $table->dropColumn(['proteksi_ketat', 'maks_pelanggaran']);
        });

        Schema::table('ujian_peserta', function (Blueprint $table) {
            $table->dropColumn(['pelanggaran', 'dikunci_at']);
        });
    }
};
