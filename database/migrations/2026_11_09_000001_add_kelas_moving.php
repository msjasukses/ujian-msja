<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dukungan ujian kelas moving: peserta satu ujian boleh berupa sebagian
 * siswa dari beberapa kelas sekaligus (mis. siswa lintas rombel yang
 * mengambil mata pelajaran yang sama).
 *
 * - ujian.mode_peserta  : 'kelas'  = seluruh siswa pada kelas yang dipilih
 *                         (perilaku lama, tetap jadi bawaan),
 *                         'manual' = kelas moving, pesertanya dipilih
 *                         per-siswa di halaman Peserta.
 * - ujian_peserta.sumber: 'kelas'  = hasil tarik otomatis dari rombel,
 *                         'manual' = ditambahkan guru satu per satu, jadi
 *                         tidak boleh ikut tercabut saat sinkron peserta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ujian', function (Blueprint $table) {
            $table->string('mode_peserta', 10)->default('kelas')->after('status');
        });

        Schema::table('ujian_peserta', function (Blueprint $table) {
            $table->string('sumber', 10)->default('kelas')->after('rombongan_belajar_id');
        });
    }

    public function down(): void
    {
        Schema::table('ujian', fn (Blueprint $table) => $table->dropColumn('mode_peserta'));
        Schema::table('ujian_peserta', fn (Blueprint $table) => $table->dropColumn('sumber'));
    }
};
