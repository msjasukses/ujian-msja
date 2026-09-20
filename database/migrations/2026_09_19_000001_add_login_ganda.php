<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penandaan login ganda di menu Log Login.
 *
 * Penanda sesi siswa tidak terhapus bila siswa sekadar menutup peramban
 * ujiannya, jadi "ada penanda lama" belum berarti ada dua perangkat sekaligus.
 * Karena itu dicatat pula kapan sesi itu terakhir benar-benar dipakai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesi_siswa', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('login_at');
        });

        Schema::table('login_attempts', function (Blueprint $table) {
            $table->boolean('login_ganda')->default(false)->index();
            $table->string('login_ganda_ip', 45)->nullable();
            $table->string('login_ganda_ua', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sesi_siswa', fn (Blueprint $t) => $t->dropColumn('last_seen_at'));
        Schema::table('login_attempts', fn (Blueprint $t) => $t->dropColumn(['login_ganda', 'login_ganda_ip', 'login_ganda_ua']));
    }
};
